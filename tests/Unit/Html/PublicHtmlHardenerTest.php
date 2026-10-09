<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Html;

use Nowo\PageBuilderKitBundle\Html\PublicHtmlHardener;
use Nowo\PageBuilderKitBundle\Service\GrapesDocumentSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PublicHtmlHardener::class)]
final class PublicHtmlHardenerTest extends TestCase
{
    private PublicHtmlHardener $hardener;

    protected function setUp(): void
    {
        $this->hardener = new PublicHtmlHardener();
    }

    /**
     * Regression for the pre-1.6 kit bypass: GrapesDocumentSanitizer HTML-decodes Twig tokens, so the decoded
     * Twig *source* contains a live <script>. Whatever reaches the final gate, it must not survive.
     */
    public function testNeutralizesDecodedTwigSource(): void
    {
        $decodedSource = new GrapesDocumentSanitizer()->sanitizeHtml('<p>{{ &lt;script&gt;alert(document.domain)&lt;/script&gt; }}</p>');
        self::assertStringContainsString('<script>', $decodedSource, 'Twig source is decoded by design (only fed to Twig).');

        $safe = $this->hardener->harden($decodedSource);

        self::assertStringNotContainsStringIgnoringCase('<script', $safe);
        self::assertStringNotContainsString('alert(document.domain)', $safe);
    }

    public function testNullInputBecomesEmptyString(): void
    {
        self::assertSame('', $this->hardener->harden(null));
        self::assertSame('', $this->hardener->hardenCss(null));
    }

    public function testAllowScriptsKeepsScriptElementsButStillStripsHandlers(): void
    {
        $safe = new PublicHtmlHardener(true)->harden('<p onclick="x()">ok</p><script>track()</script><iframe src="/x"></iframe>');

        self::assertSame('<p>ok</p><script>track()</script>', $safe);
    }

    public function testCleanMarkupKeepsItsStructure(): void
    {
        $html = '<section class="pbk-hero"><picture><source srcset="/a.webp 1x, /a@2x.webp 2x" type="image/webp">'
            . '<img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-src="/media/a.jpg" alt="Á"></picture>'
            . '<a href="/contact" style="color:red">Book now</a><svg viewBox="0 0 1 1"><use xlink:href="#i"></use></svg>'
            . '<span data-pbk-bind="hero_title">[[fields.hero_title]]</span></section>';

        // Always re-serialized by the HTML5 serializer: same markup for already-normalized HTML.
        self::assertSame($html, $this->hardener->harden($html));
        self::assertSame($this->hardener->harden($html), $this->hardener->harden($this->hardener->harden($html)), 'Idempotent.');
    }

    public function testBlankInputIsUntouched(): void
    {
        self::assertSame('', $this->hardener->harden(''));
        self::assertSame("  \n", $this->hardener->harden("  \n"));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function dangerousMarkup(): iterable
    {
        yield 'script element' => ['<p>ok</p><script>alert(1)</script>', 'alert(1)'];
        yield 'uppercase script' => ['<SCRIPT>alert(1)</SCRIPT><p>ok</p>', 'alert(1)'];
        yield 'iframe srcdoc' => ['<iframe srcdoc="&lt;script&gt;alert(1)&lt;/script&gt;"></iframe><p>ok</p>', 'srcdoc'];
        yield 'style element' => ['<style>body{background:url(x)}</style><p>ok</p>', 'background'];
        yield 'event handler' => ['<p>ok</p><img src="/a.png" onerror="alert(1)">', 'onerror'];
        yield 'mixed-case handler' => ['<p>ok</p><div OnMouseOver="alert(1)">x</div>', 'alert(1)'];
        yield 'javascript href' => ['<p>ok</p><a href="javascript:alert(1)">x</a>', 'javascript:'];
        yield 'obfuscated javascript href' => ['<p>ok</p><a href="&#x20;java&#x09;script:alert(1)">x</a>', 'alert(1)'];
        yield 'vbscript href' => ['<p>ok</p><a href="vbscript:msgbox(1)">x</a>', 'vbscript'];
        yield 'data html href' => ['<p>ok</p><a href="data:text/html;base64,PHNjcmlwdD4=">x</a>', 'data:text/html'];
        yield 'svg animate href' => ['<p>ok</p><svg><a><animate attributeName="href" values="javascript:alert(1)"/><text>x</text></a></svg>', 'animate'];
        yield 'svg set' => ['<p>ok</p><svg><set attributeName="onmouseover" to="alert(1)"/></svg>', 'alert(1)'];
        yield 'foreignObject' => ['<p>ok</p><svg><foreignObject><img src="/a.png"></foreignObject></svg>', 'foreignobject'];
        yield 'xlink javascript' => ['<p>ok</p><svg><a xlink:href="javascript:alert(1)">x</a></svg>', 'javascript:'];
        yield 'srcset javascript candidate' => ['<p>ok</p><img srcset="/a.png 1x, javascript:alert(1) 2x">', 'javascript:'];
        yield 'form action' => ['<p>ok</p><form action="https://evil.test"><button>x</button></form>', 'evil.test'];
        yield 'button formaction' => ['<p>ok</p><button formaction="https://evil.test">x</button>', 'evil.test'];
        yield 'math element' => ['<p>ok</p><math><mtext>x</mtext></math>', 'mtext'];
        yield 'base element' => ['<base href="https://evil.test/"><p>ok</p>', 'evil.test'];
        yield 'meta refresh' => ['<meta http-equiv="refresh" content="0;url=https://evil.test"><p>ok</p>', 'evil.test'];
        yield 'object' => ['<object data="/x.swf"></object><p>ok</p>', 'x.swf'];
        yield 'html5 entity scheme' => ['<p>ok</p><a href="javascript&colon;alert(1)">x</a>', 'javascript'];
        yield 'tab entity scheme' => ['<p>ok</p><a href="java&Tab;script:alert(1)">x</a>', 'alert(1)'];
        yield 'slash attribute separator' => ['<p>ok</p><a/href="javascript:alert(1)">x</a>', 'javascript'];
        yield 'svg slash onload' => ['<p>ok</p><svg/onload=alert(1)>', 'onload'];
        yield 'bogus comment' => ['<p>ok</p><!--> <img src=x onerror=alert(1)> -->', 'onerror'];
        yield 'xmp raw text' => ['<p>ok</p><xmp><a title="</xmp><img src=x onerror=alert(1)>"></xmp>', 'onerror'];
        yield 'noembed raw text' => ['<p>ok</p><noembed><a title="</noembed><meta http-equiv=refresh content=0;url=//evil.test>"></noembed>', 'refresh'];
        yield 'title raw text' => ['<p>ok</p><title><a title="</title><img src=x onerror=alert(1)>"></title>', 'onerror'];
        yield 'template content' => ['<p>ok</p><template><img src=x onerror=alert(1)></template>', 'onerror'];
        yield 'noscript mXSS' => ['<p>ok</p><noscript><p title="</noscript><img src=x onerror=alert(1)>"></noscript>', 'onerror'];
    }

    #[DataProvider('dangerousMarkup')]
    public function testRemovesDangerousMarkup(string $html, string $needle): void
    {
        $safe = $this->hardener->harden($html);

        self::assertStringNotContainsStringIgnoringCase($needle, $safe);
        self::assertStringContainsString('ok', $safe);
    }

    public function testKeepsSafeSiblingsWhenRewriting(): void
    {
        $safe = $this->hardener->harden('<div class="pbk-card"><a href="/services" onclick="x()">View</a></div>');

        self::assertSame('<div class="pbk-card"><a href="/services">View</a></div>', $safe);
    }

    public function testAllowsDataImageUrls(): void
    {
        $html = '<p>x</p><img src="data:image/webp;base64,UklGRg==" onload="x()">';

        self::assertStringContainsString('src="data:image/webp;base64,UklGRg=="', $this->hardener->harden($html));
    }

    public function testCssCannotCloseItsStyleElement(): void
    {
        $css = '.a{color:red}</sty</sty</stylelele><meta http-equiv=refresh content="0;url=https://evil.example">';

        $safe = $this->hardener->hardenCss($css);

        self::assertStringNotContainsString('<', $safe);
        self::assertStringStartsWith('.a{color:red}\\3C /sty', $safe);
        self::assertSame('.hero{background:url(/a.webp)}', $this->hardener->hardenCss('.hero{background:url(/a.webp)}'));
    }
}
