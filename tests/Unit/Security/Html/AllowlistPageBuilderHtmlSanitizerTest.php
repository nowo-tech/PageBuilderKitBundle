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
}
