<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Security\Html;

use Nowo\PageBuilderKitBundle\Security\Html\AllowlistPageBuilderHtmlSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AllowlistPageBuilderHtmlSanitizer::class)]
final class AllowlistPageBuilderHtmlSanitizerTest extends TestCase
{
    #[Test]
    public function stripsScriptTags(): void
    {
        $sanitizer = new AllowlistPageBuilderHtmlSanitizer();

        $input  = '<p>Safe</p><script>alert(1)</script><strong>bold</strong>';
        $output = $sanitizer->sanitize($input);

        self::assertStringNotContainsString('script', $output);
        self::assertStringContainsString('<p>Safe</p>', $output);
        self::assertStringContainsString('<strong>bold</strong>', $output);
    }

    #[Test]
    public function returnsEmptyForBlankInput(): void
    {
        $sanitizer = new AllowlistPageBuilderHtmlSanitizer();

        self::assertSame('', $sanitizer->sanitize(''));
        self::assertSame('', $sanitizer->sanitize("  \n\t  "));
    }

    #[Test]
    public function unwrapsDisallowedTagsWhileKeepingChildren(): void
    {
        $sanitizer = new AllowlistPageBuilderHtmlSanitizer();

        $output = $sanitizer->sanitize('<div class="wrap"><p>Keep</p><section><em>nested</em></section></div>');

        self::assertStringNotContainsString('<div', $output);
        self::assertStringNotContainsString('<section', $output);
        self::assertStringContainsString('<p>Keep</p>', $output);
        self::assertStringContainsString('<em>nested</em>', $output);
    }

    #[Test]
    public function removesDangerousAttributesAndJavascriptUrls(): void
    {
        $sanitizer = new AllowlistPageBuilderHtmlSanitizer();

        $output = $sanitizer->sanitize(
            '<a href="javascript:alert(1)" onclick="x" title="t">link</a>'
            . '<img src="javascript:evil()" alt="a" width="1" data-x="1">'
            . '<p class="ok" id="p1" style="color:red">text</p>',
        );

        self::assertStringNotContainsString('javascript:', $output);
        self::assertStringNotContainsString('onclick', $output);
        self::assertStringNotContainsString('data-x', $output);
        self::assertStringNotContainsString('style=', $output);
        self::assertStringContainsString('title="t"', $output);
        self::assertStringContainsString('class="ok"', $output);
        self::assertStringContainsString('id="p1"', $output);
    }
}
