<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use Nowo\PageBuilderKitBundle\Service\GrapesDocumentSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GrapesDocumentSanitizer::class)]
final class GrapesDocumentSanitizerTest extends TestCase
{
    #[Test]
    public function stripsScriptsAndEventHandlersByDefault(): void
    {
        $sanitizer = new GrapesDocumentSanitizer(false);
        $html      = $sanitizer->sanitizeHtml(
            '<p onclick="alert(1)">Hi</p><script>evil()</script><a href="javascript:alert(1)">x</a>',
        );

        self::assertStringContainsString('<p>Hi</p>', $html);
        self::assertStringNotContainsString('script', strtolower($html));
        self::assertStringNotContainsString('onclick', strtolower($html));
        self::assertStringNotContainsString('javascript:', strtolower($html));
    }

    #[Test]
    public function keepsScriptsWhenAllowed(): void
    {
        $sanitizer = new GrapesDocumentSanitizer(true);
        $html      = $sanitizer->sanitizeHtml('<p>Ok</p><script>console.log(1)</script>');

        self::assertStringContainsString('<script>', $html);
        self::assertStringContainsString('console.log(1)', $html);
    }

    #[Test]
    public function rejectsDangerousCss(): void
    {
        $sanitizer = new GrapesDocumentSanitizer();

        self::assertSame('', $sanitizer->sanitizeCss('body{background:url(javascript:alert(1))}'));
        self::assertSame('', $sanitizer->sanitizeCss('@import url("x.css");'));
        self::assertSame('.ok{color:red}', $sanitizer->sanitizeCss('.ok{color:red}'));
    }

    #[Test]
    public function sanitizeStructureNormalizesFields(): void
    {
        $result = (new GrapesDocumentSanitizer())->sanitizeStructure([
            'html'   => '<p>Hi</p><script>x</script>',
            'css'    => '.a{}',
            'grapes' => null,
        ]);

        self::assertStringContainsString('<p>Hi</p>', $result['html']);
        self::assertStringNotContainsString('script', strtolower((string) $result['html']));
        self::assertSame('.a{}', $result['css']);
        self::assertSame([], $result['grapes']);
    }

    #[Test]
    public function preservesTwigTokensForPublicRender(): void
    {
        $input = '<h1>{{ title }}</h1>{% if locale == "es" %}<p>{{ pageKey }}</p>{% endif %}';
        $html  = (new GrapesDocumentSanitizer())->sanitizeHtml($input);

        self::assertStringContainsString('{{ title }}', $html);
        self::assertStringContainsString('{% if locale == "es" %}', $html);
        self::assertStringContainsString('{{ pageKey }}', $html);
        self::assertStringContainsString('{% endif %}', $html);
    }

    #[Test]
    public function sanitizeHtmlStripsDisallowedTags(): void
    {
        $html = (new GrapesDocumentSanitizer())->sanitizeHtml('<iframe src="x"></iframe><p>Ok</p>');

        self::assertStringNotContainsString('iframe', strtolower($html));
        self::assertStringContainsString('<p>Ok</p>', $html);
    }

    #[Test]
    public function sanitizeCssRejectsExpressionSyntax(): void
    {
        self::assertSame('', (new GrapesDocumentSanitizer())->sanitizeCss('div{color:expression(alert(1))}'));
    }
}
