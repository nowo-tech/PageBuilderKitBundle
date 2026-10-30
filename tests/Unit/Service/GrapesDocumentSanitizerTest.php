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
        $result = new GrapesDocumentSanitizer()->sanitizeStructure([
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
        $html  = new GrapesDocumentSanitizer()->sanitizeHtml($input);

        self::assertStringContainsString('{{ title }}', $html);
        self::assertStringContainsString('{% if locale == "es" %}', $html);
        self::assertStringContainsString('{{ pageKey }}', $html);
        self::assertStringContainsString('{% endif %}', $html);
    }

    #[Test]
    public function restoresTwigTokensInsideHtmlAttributes(): void
    {
        $input = '<a href="{{ p.url }}">{{ p.name }}</a>';
        $html  = new GrapesDocumentSanitizer()->sanitizeHtml($input);

        self::assertStringContainsString('href="{{ p.url }}"', $html);
        self::assertStringContainsString('{{ p.name }}', $html);
        self::assertStringNotContainsString('%20', $html);
    }

    #[Test]
    public function sanitizeHtmlStripsDisallowedTags(): void
    {
        $html = new GrapesDocumentSanitizer()->sanitizeHtml('<iframe src="x"></iframe><p>Ok</p>');

        self::assertStringNotContainsString('iframe', strtolower($html));
        self::assertStringContainsString('<p>Ok</p>', $html);
    }

    #[Test]
    public function sanitizeCssRejectsExpressionSyntax(): void
    {
        self::assertSame('', new GrapesDocumentSanitizer()->sanitizeCss('div{color:expression(alert(1))}'));
    }

    #[Test]
    public function withoutTwigRestoreEncodedTokenContentStaysEncoded(): void
    {
        $sanitizer = new GrapesDocumentSanitizer();
        $input     = '<p>{{ &lt;script&gt;alert(1)&lt;/script&gt; }}</p>';

        self::assertStringContainsString('<script>', $sanitizer->sanitizeHtml($input), 'Twig source (decoded by design).');
        self::assertSame('<p>{{ &lt;script&gt;alert(1)&lt;/script&gt; }}</p>', $sanitizer->sanitizeHtml($input, false));
    }

    #[Test]
    public function sanitizeGrapesDropsHandlersScriptsAndUnsafeUrls(): void
    {
        $project = ['pages' => [['frames' => [['component' => ['type' => 'wrapper', 'components' => [
            ['tagName' => 'img', 'attributes' => ['src' => 'x', 'onerror' => 'alert(1)', 'ONLOAD' => 'x', 'alt' => 'a']],
            ['type' => 'link', 'attributes' => ['href' => ' JaVaScript:alert(1)', 'title' => 't']],
            ['type' => 'link', 'attributes' => ['href' => 'https://example.com']],
            ['tagName' => 'img', 'attributes' => ['src' => 'data:image/png;base64,AAAA']],
            ['tagName' => 'img', 'attributes' => ['src' => 'data:text/html;base64,AAAA']],
            ['tagName' => 'iframe', 'attributes' => ['srcdoc' => '<script>x</script>']],
            ['type' => 'script', 'content' => 'alert(1)'],
            ['tagName' => 'div', 'attributes' => ['srcdoc' => 'x', 'formaction' => 'x'], 'script' => 'alert(1)', 'script-props' => ['a']],
            ['type' => 'text', 'content' => '<b onclick="x">Hi</b>'],
        ]]]]]], 'styles' => [['selectors' => ['a'], 'style' => ['color' => 'red']]]];

        $out        = new GrapesDocumentSanitizer()->sanitizeGrapes($project);
        $components = $out['pages'][0]['frames'][0]['component']['components'];

        self::assertCount(7, $components, 'iframe and script components removed; list re-indexed.');
        self::assertSame(['src' => 'x', 'alt' => 'a'], $components[0]['attributes']);
        self::assertSame(['title' => 't'], $components[1]['attributes']);
        self::assertSame(['href' => 'https://example.com'], $components[2]['attributes']);
        self::assertSame(['src' => 'data:image/png;base64,AAAA'], $components[3]['attributes']);
        self::assertSame([], $components[4]['attributes']);
        self::assertSame(['tagName' => 'div', 'attributes' => []], $components[5]);
        self::assertStringNotContainsString('onclick', $components[6]['content']);
        self::assertSame($project['styles'], $out['styles']);
    }

    #[Test]
    public function sanitizeGrapesKeepsScriptsWhenAllowed(): void
    {
        $project = ['components' => [
            ['type' => 'script', 'content' => 'x'],
            ['tagName' => 'div', 'script' => 'x'],
            ['tagName' => 'object'],
        ]];

        $out = new GrapesDocumentSanitizer(true)->sanitizeGrapes($project);

        self::assertSame([['type' => 'script', 'content' => 'x'], ['tagName' => 'div', 'script' => 'x']], $out['components']);
    }

    #[Test]
    public function sanitizeStructureCleansTopLevelAndLocaleGrapes(): void
    {
        $grapes = ['components' => [['tagName' => 'img', 'attributes' => ['onerror' => 'x']]]];
        $out    = new GrapesDocumentSanitizer()->sanitizeStructure([
            'grapes'        => $grapes,
            'localeContent' => ['en' => ['html' => '', 'css' => '', 'grapes' => $grapes]],
        ]);

        self::assertSame([], $out['grapes']['components'][0]['attributes']);
        self::assertSame([], $out['localeContent']['en']['grapes']['components'][0]['attributes']);
    }

    #[Test]
    public function dropsCommentsMxssVectorsAndUnsafeUrls(): void
    {
        $html = new GrapesDocumentSanitizer(false)->sanitizeHtml(
            '<p>a<!--><img src=x onerror=alert(2)>--></p><?x <img src=x onerror=alert(4)> ?>'
            . '<a href="vbscript:msgbox(1)">v</a><a href="data:text/html,<script>alert(1)</script>">d</a><a href="https://ok.test/">ok</a>'
            . '<svg><animate attributeName="href" values="javascript:alert(1)"/><set attributeName="href" to="javascript:alert(1)"/></svg>'
            . '<math><mtext><style>x</style></mtext></math><template shadowrootmode="open">t</template><portal src="https://x"></portal>'
            . '<div srcdoc="&lt;script&gt;" xml:base="javascript:alert(1)//">s</div><img src="data:image/png;base64,AA" alt="">',
            false,
        );

        foreach (['<!--', 'onerror', 'vbscript', 'data:text', '<animate', '<set', '<math', '<template', '<portal', 'srcdoc', 'xml:base', '<style'] as $needle) {
            self::assertStringNotContainsString($needle, $html, $needle);
        }
        self::assertStringContainsString('<a href="https://ok.test/">ok</a>', $html);
        self::assertStringContainsString('src="data:image/png;base64,AA"', $html);
    }
}
