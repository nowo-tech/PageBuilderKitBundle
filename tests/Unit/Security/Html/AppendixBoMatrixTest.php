<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Security\Html;

use Dom\HTMLDocument;
use Nowo\PageBuilderKitBundle\Content\ContentFieldDefinitionProviderInterface;
use Nowo\PageBuilderKitBundle\Html\PublicHtmlHardener;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Security\Html\AllowlistPageBuilderHtmlSanitizer;
use Nowo\PageBuilderKitBundle\Security\Html\HtmlNodePurger;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessCheckerInterface;
use Nowo\PageBuilderKitBundle\Service\GrapesDocumentSanitizer;
use Nowo\PageBuilderKitBundle\Service\PublicBindHydrator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

use function in_array;
use function preg_match;
use function preg_replace;
use function str_replace;
use function str_starts_with;
use function strtolower;

use const LIBXML_NOERROR;

/**
 * REQ-SEC-004 Appendix BO (2026-10-30): the auditor's 130-payload corpus through every sanitizer layer on its own and
 * through the bind hydrator (M1). Executable output is checked after two HTML5 parses (mXSS): script-capable
 * elements, event handlers, `srcdoc` / `formaction`, script or non-image `data:` URLs, declarative shadow roots; the
 * HTML5 gate and the hydrator must also drop framework gadget attributes and `form=` (L3).
 */
#[CoversClass(PublicHtmlHardener::class)]
#[CoversClass(AllowlistPageBuilderHtmlSanitizer::class)]
#[CoversClass(GrapesDocumentSanitizer::class)]
#[CoversClass(PublicBindHydrator::class)]
#[CoversClass(HtmlNodePurger::class)]
final class AppendixBoMatrixTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function payloads(): iterable
    {
        /** @var array<string, string> $payloads */
        $payloads = require __DIR__ . '/../../../Fixtures/xss-payloads-appendix-bo.php';
        foreach ($payloads as $name => $payload) {
            yield $name => [$payload];
            // Same payload inside a bind slot, as a GrapesJS page or an html field placed in [[fields.x]].
            yield $name . ' (bind slot)' => ['<span data-pbk-bind="hero_title">' . $payload . '</span>'];
        }
    }

    #[Test]
    #[DataProvider('payloads')]
    public function noLayerLeavesExecutableMarkup(string $payload): void
    {
        $hardener = new PublicHtmlHardener();
        $outputs  = [
            'allowlist' => new AllowlistPageBuilderHtmlSanitizer()->sanitize($payload),
            'grapes'    => new GrapesDocumentSanitizer()->sanitizeHtml($payload, false),
            'hardener'  => $hardener->harden($payload),
            'hydrated'  => $this->hydrator($hardener)->hydrate($payload, 'home'),
        ];

        foreach ($outputs as $layer => $html) {
            self::assertSame([], $this->executable($html, in_array($layer, ['hardener', 'hydrated'], true)), $layer . ': ' . $html);
        }
    }

    /**
     * @return list<string>
     */
    private function executable(string $html, bool $strict): array
    {
        $found = [];
        $pass  = $html;
        for ($i = 0; $i < 2; ++$i) {
            $document = HTMLDocument::createFromString('<!DOCTYPE html><html><body>' . $pass . '</body></html>', LIBXML_NOERROR, 'UTF-8');
            foreach ($document->body?->querySelectorAll('*') ?? [] as $element) {
                $name = strtolower($element->localName);
                if (in_array($name, ['script', 'iframe', 'object', 'embed', 'frame', 'frameset', 'base', 'meta', 'portal', 'animate', 'set', 'animatemotion', 'animatetransform', 'applet', 'link'], true)) {
                    $found[] = '<' . $name . '>';
                }
                if ($name === 'template' && $element->hasAttribute('shadowrootmode')) {
                    $found[] = 'dsd';
                }
                foreach ($element->attributes as $attribute) {
                    $attr = strtolower($attribute->name);
                    if (str_starts_with($attr, 'on') || in_array($attr, ['srcdoc', 'formaction'], true)) {
                        $found[] = $name . '@' . $attr;
                    }
                    if (in_array($attr, ['href', 'src', 'xlink:href', 'action', 'data', 'poster', 'background', 'formaction', 'xml:base'], true)) {
                        $value  = strtolower(str_replace(["\t", "\n", "\r"], '', (string) preg_replace('/^[\x00-\x20]+/', '', $attribute->value)));
                        $scheme = preg_match('/^([a-z][a-z0-9+.-]*):/', $value, $m) === 1 ? $m[1] : '';
                        if (in_array($scheme, ['javascript', 'vbscript'], true) || ($scheme === 'data' && preg_match('#^data:image/(png|jpe?g|gif|webp|avif)[;,]#', $value) !== 1)) {
                            $found[] = $name . '@' . $attr . '=' . $scheme;
                        }
                    }
                    if ($strict && (preg_match('/^(x-|hx-|v-|ng-|@|:|data-turbo)/', $attr) === 1 || $attr === 'form' || $attr === 'xml:base')) {
                        $found[] = 'gadget ' . $name . '@' . $attr;
                    }
                }
            }
            $pass = (string) $document->body?->innerHTML;
        }

        return $found;
    }

    private function hydrator(PublicHtmlHardener $hardener): PublicBindHydrator
    {
        $access = $this->createStub(PageBuilderKitAccessCheckerInterface::class);
        $access->method('canContent')->willReturn(true);
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/save');
        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('getToken')->willReturn(new CsrfToken('page_builder_content', 'REDACTED-TEST'));
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $provider = $this->createStub(ContentFieldDefinitionProviderInterface::class);
        $provider->method('definitions')->willReturn([['key' => 'hero_title', 'type' => 'html', 'label' => 'Hero']]);
        $form = $this->createStub(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $forms = $this->createStub(FormFactoryInterface::class);
        $forms->method('create')->willReturn($form);
        // The editable-field partial prints an html value through |pbk_harden_html: mirror that.
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            static fn (string $template, array $context): string => '<span data-pbk-field><span data-pbk-value>' . $hardener->harden((string) $context['value']) . '</span></span>',
        );

        return new PublicBindHydrator($twig, $access, $urls, $csrf, new RequestStack(), $forms, $translator, $provider, new BuilderLocales('en', ['en']), '/assets', $hardener);
    }
}
