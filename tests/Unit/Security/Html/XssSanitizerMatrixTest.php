<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Security\Html;

use Nowo\PageBuilderKitBundle\Security\Html\AllowlistPageBuilderHtmlSanitizer;
use Nowo\PageBuilderKitBundle\Service\GrapesDocumentSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * XSS regression matrix for default allowlist + Grapes HTML sanitizer.
 */
#[CoversClass(AllowlistPageBuilderHtmlSanitizer::class)]
#[CoversClass(GrapesDocumentSanitizer::class)]
final class XssSanitizerMatrixTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: list<string>}>
     */
    public static function allowlistPayloads(): iterable
    {
        yield 'script_tag' => ['<p>ok</p><script>alert(1)</script>', ['script', 'alert']];
        yield 'iframe' => ['<iframe src="https://evil.test"></iframe><p>x</p>', ['iframe']];
        yield 'onerror' => ['<img src=x onerror=alert(1)>', ['onerror']];
        yield 'svg_onload' => ['<svg onload=alert(1)></svg>', ['svg', 'onload']];
        yield 'javascript_href' => ['<a href="javascript:alert(1)">x</a>', ['javascript:']];
    }

    /**
     * @param list<string> $forbidden
     */
    #[Test]
    #[DataProvider('allowlistPayloads')]
    public function allowlistStripsDangerousMarkup(string $input, array $forbidden): void
    {
        $output = strtolower((new AllowlistPageBuilderHtmlSanitizer())->sanitize($input));
        foreach ($forbidden as $needle) {
            self::assertStringNotContainsString(strtolower($needle), $output);
        }
    }

    /**
     * @return iterable<string, array{0: string, 1: list<string>}>
     */
    public static function grapesPayloads(): iterable
    {
        yield 'script' => ['<p>Hi</p><script>evil()</script>', ['<script', 'evil()']];
        yield 'onclick' => ['<p onclick="alert(1)">Hi</p>', ['onclick']];
        yield 'javascript_url' => ['<a href="javascript:alert(1)">x</a>', ['javascript:']];
    }

    /**
     * @param list<string> $forbidden
     */
    #[Test]
    #[DataProvider('grapesPayloads')]
    public function grapesSanitizerStripsXssVectors(string $input, array $forbidden): void
    {
        $output = strtolower((new GrapesDocumentSanitizer(false))->sanitizeHtml($input));
        foreach ($forbidden as $needle) {
            self::assertStringNotContainsString(strtolower($needle), $output);
        }
    }

    #[Test]
    public function grapesRejectsDangerousCss(): void
    {
        $sanitizer = new GrapesDocumentSanitizer();
        self::assertSame('', $sanitizer->sanitizeCss('body{background:url(javascript:alert(1))}'));
        self::assertSame('', $sanitizer->sanitizeCss('@import url("x.css");'));
    }
}
