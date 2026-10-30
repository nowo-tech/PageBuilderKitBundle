<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use Dom\HTMLDocument;
use Nowo\PageBuilderKitBundle\Content\ContentFieldDefinitionProviderInterface;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessCheckerInterface;
use Nowo\PageBuilderKitBundle\Service\PublicBindHydrator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

use function htmlspecialchars;
use function str_starts_with;
use function strtolower;

use const ENT_QUOTES;
use const LIBXML_NOERROR;

final class PublicBindHydratorTest extends TestCase
{
    #[Test]
    public function visitorsKeepHtmlWithoutRendering(): void
    {
        $html = '<p><span data-pbk-bind="hero_title">Hola</span></p>';

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::never())->method('render');

        self::assertSame($html, $this->hydrator($twig, false)->hydrate($html, 'home'));
    }

    #[Test]
    public function htmlWithoutBindsIsReturnedUntouched(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::never())->method('render');

        $hydrator = $this->hydrator($twig, true);

        self::assertSame('', $hydrator->hydrate('', 'home'));
        self::assertSame('<p>x</p>', $hydrator->hydrate('<p>x</p>', 'home'));
    }

    #[Test]
    public function editorsGetFieldTemplatePerSlotWithAssetsOnce(): void
    {
        $html = '<span data-pbk-bind="hero_title">Hola</span><span data-pbk-bind="hero_body" class="x">Body</span>';

        $form = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $forms = $this->createMock(FormFactoryInterface::class);
        $forms->expects(self::once())->method('create')->willReturn($form);

        $contexts = [];
        $twig     = $this->createMock(Environment::class);
        $twig->expects(self::exactly(2))
            ->method('render')
            ->willReturnCallback(static function (string $template, array $ctx) use (&$contexts): string {
                self::assertSame('@NowoPageBuilderKitBundle/public/_editable_field.html.twig', $template);
                $contexts[$ctx['field_key']] = $ctx;

                return 'F:' . $ctx['field_key'] . ':' . $ctx['value'];
            });

        $stack = new RequestStack();
        $stack->push(Request::create('/'));

        $out = $this->hydrator($twig, true, $forms, $stack, [
            ['key' => 'hero_title', 'type' => 'string', 'label' => 'field.hero.title'],
            ['key' => 'hero_body', 'type' => 'html', 'label' => 'field.hero.body'],
        ])->hydrate($html, 'home');

        self::assertSame('F:hero_title:HolaF:hero_body:Body', $out);
        self::assertSame('home', $contexts['hero_title']['page_key']);
        self::assertSame('t:field.hero.title', $contexts['hero_title']['label']);
        self::assertSame('string', $contexts['hero_title']['type']);
        self::assertFalse($contexts['hero_title']['is_html_output']);
        self::assertTrue($contexts['hero_title']['include_assets']);
        self::assertInstanceOf(FormView::class, $contexts['hero_title']['modal_form']);
        self::assertSame('html', $contexts['hero_body']['type']);
        self::assertTrue($contexts['hero_body']['is_html_output']);
        self::assertFalse($contexts['hero_body']['include_assets']);
        self::assertNull($contexts['hero_body']['modal_form']);
        self::assertSame('/save', $contexts['hero_title']['save_url']);
        self::assertSame('csrf', $contexts['hero_title']['csrf_token']);
        self::assertSame('/assets', $contexts['hero_title']['asset_base']);
        self::assertTrue($contexts['hero_title']['editable']);
    }

    #[Test]
    public function unknownFieldsFallBackToStringTypeAndKeyLabel(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->willReturnCallback(static function (string $template, array $ctx): string {
                self::assertSame('string', $ctx['type']);
                self::assertSame('t:mystery', $ctx['label']);
                self::assertSame('en', $ctx['locale']);

                return 'OK';
            });

        $form = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $forms = $this->createMock(FormFactoryInterface::class);
        $forms->method('create')->willReturn($form);

        // No request: locale falls back to BuilderLocales default, assets always included.
        self::assertSame('OK', $this->hydrator($twig, true, $forms)->hydrate('<span data-pbk-bind="mystery">v</span>', 'home'));
    }

    /**
     * Appendix BO M1: a `</span>` inside an attribute value or a comment must never end the slot early.
     *
     * @return iterable<string, array{string}>
     */
    public static function breakoutPayloads(): iterable
    {
        yield 'attribute' => ['<span data-pbk-bind="hero_title"><span title="</span><img src=x onerror=alert(document.domain)>">Hi</span></span>'];
        yield 'comment' => ['<span data-pbk-bind="hero_title">Hi<!-- </span><img src=x onerror=alert(document.domain)> --></span>'];
        yield 'nested spans' => ['<span data-pbk-bind="hero_title"><span>a</span><img src=x onerror=alert(1)></span>'];
        yield 'bind in an attribute' => ['<span title=\'<span data-pbk-bind="hero_title">\'>x</span><img src=x onerror=alert(1)></span>'];
        yield 'comment-only close' => ['<span data-pbk-bind="hero_title"><!--></span><img src=x onerror=alert(1)>--></span>'];
    }

    #[Test]
    #[DataProvider('breakoutPayloads')]
    public function slotsAreFoundOnTheHtml5TreeSoInertMarkupStaysInert(string $payload): void
    {
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            static fn (string $template, array $ctx): string => '<span data-pbk-field>' . htmlspecialchars((string) $ctx['value'], ENT_QUOTES) . '</span>',
        );
        $form = $this->createStub(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $forms = $this->createStub(FormFactoryInterface::class);
        $forms->method('create')->willReturn($form);

        foreach ([true, false] as $canContent) {
            $out = $this->hydrator($twig, $canContent, $forms, null, [['key' => 'hero_title', 'type' => 'html', 'label' => 'x']])->hydrate($payload, 'home');

            self::assertSame([], $this->liveHandlers($out), $out);
            self::assertStringNotContainsString('<!--', $out);
        }
    }

    #[Test]
    public function htmlSlotsKeepTheirMarkupAndTextSlotsTheirText(): void
    {
        $values = [];
        $twig   = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(static function (string $template, array $ctx) use (&$values): string {
            $values[$ctx['field_key']] = $ctx['value'];

            return '[' . $ctx['field_key'] . ']';
        });
        $form = $this->createStub(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $forms = $this->createStub(FormFactoryInterface::class);
        $forms->method('create')->willReturn($form);

        $out = $this->hydrator($twig, true, $forms, null, [
            ['key' => 'body', 'type' => 'html', 'label' => 'b'],
            ['key' => 'title', 'type' => 'string', 'label' => 't'],
        ])->hydrate('<p><span class="a" data-pbk-bind="body"><em>Hi</em> &amp; <span data-pbk-bind="title">x</span></span></p><span data-pbk-bind="title">Tom &amp; Jerry</span><span data-pbk-bind="bad key">k</span>', 'home');

        self::assertSame('<p>[body]</p>[title]<span data-pbk-bind="bad key">k</span>', $out);
        self::assertSame('<em>Hi</em> &amp; <span data-pbk-bind="title">x</span>', $values['body']);
        self::assertSame('Tom & Jerry', $values['title']);
    }

    #[Test]
    public function inputWithoutValidSlotsIsOnlyHardened(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::never())->method('render');

        self::assertSame('<b data-pbk-bind="x">x</b><img src="y">', $this->hydrator($twig, true)->hydrate('<b data-pbk-bind="x">x</b><img src=y onerror=alert(1)>', 'home'));
    }

    /**
     * @return list<string>
     */
    private function liveHandlers(string $html): array
    {
        $document = HTMLDocument::createFromString('<!DOCTYPE html><body>' . $html, LIBXML_NOERROR, 'UTF-8');
        $live     = [];
        foreach ($document->querySelectorAll('*') as $element) {
            foreach ($element->attributes as $attribute) {
                if (str_starts_with(strtolower($attribute->name), 'on')) {
                    $live[] = $element->localName . '@' . $attribute->name;
                }
            }
        }

        return $live;
    }

    /**
     * @param list<array{key: string, type: string, label: string}> $definitions
     */
    private function hydrator(
        Environment $twig,
        bool $canContent,
        ?FormFactoryInterface $forms = null,
        ?RequestStack $stack = null,
        array $definitions = [],
    ): PublicBindHydrator {
        $access = $this->createStub(PageBuilderKitAccessCheckerInterface::class);
        $access->method('canContent')->willReturn($canContent);

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/save');

        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('getToken')->willReturn(new CsrfToken('page_builder_content', 'csrf'));

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => 't:' . $id);

        $provider = $this->createStub(ContentFieldDefinitionProviderInterface::class);
        $provider->method('definitions')->willReturn($definitions);

        return new PublicBindHydrator(
            $twig,
            $access,
            $urls,
            $csrf,
            $stack ?? new RequestStack(),
            $forms ?? $this->createStub(FormFactoryInterface::class),
            $translator,
            $provider,
            new BuilderLocales('en', ['en']),
            '/assets/',
        );
    }
}
