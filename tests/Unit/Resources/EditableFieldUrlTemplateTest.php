<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Resources;

use Nowo\PageBuilderKitBundle\Security\Html\SafeUrl;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

use function dirname;

/**
 * Stored URL / image field values are printed in href / src only when their scheme is safe (pre-fix rows included).
 */
#[CoversNothing]
final class EditableFieldUrlTemplateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function cases(): iterable
    {
        // e('html_attr') encodes ':' and '/', so the markers only check that the element is (not) rendered.
        yield 'javascript url' => ['url', 'javascript:alert(1)', '<a href=', 'javascript:alert(1)'];
        yield 'https url' => ['url', 'https://example.com', '<a href="https&#x3A;&#x2F;&#x2F;example.com">', ''];
        yield 'data html image' => ['image', 'data:text/html;base64,PHNjcmlwdD4=', '<img', 'data:text/html;base64,PHNjcmlwdD4='];
        yield 'png image' => ['image', '/media/a.png', '<img src="&#x2F;media&#x2F;a.png"', ''];
    }

    #[DataProvider('cases')]
    public function testUnsafeSchemesAreNotLinked(string $type, string $value, string $marker, string $printedAsText): void
    {
        $loader = new FilesystemLoader();
        $loader->addPath(dirname(__DIR__, 3) . '/src/Resources/views', 'NowoPageBuilderKitBundle');
        $twig = new Environment($loader);
        $twig->addExtension(new TranslationExtension());
        $twig->addFilter(new TwigFilter('pbk_harden_html', static fn (string $html): string => $html, ['is_safe' => ['html']]));
        $twig->addFilter(new TwigFilter('pbk_safe_url', SafeUrl::filter(...)));
        // The editable branch renders a form; only the read-only branch is under test here.
        foreach (['form_start', 'form_end', 'form_widget'] as $name) {
            $twig->addFunction(new TwigFunction($name, static fn (): string => '', ['is_safe' => ['html']]));
        }

        $html = $twig->render('@NowoPageBuilderKitBundle/public/_editable_field.html.twig', [
            'page_key'   => 'home',
            'field_key'  => 'cta',
            'type'       => $type,
            'label'      => 'CTA',
            'labels'     => [],
            'locale'     => 'en',
            'value'      => $value,
            'tag'        => 'span',
            'attr_class' => '',
            'options'    => [],
            'required'   => false,
            'editable'   => false,
        ]);

        if ($printedAsText === '') {
            self::assertStringContainsString($marker, $html);
        } else {
            self::assertStringNotContainsString($marker, $html);
            self::assertStringContainsString($printedAsText, $html);
        }
    }
}
