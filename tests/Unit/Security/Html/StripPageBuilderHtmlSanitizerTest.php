<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Security\Html;

use Nowo\PageBuilderKitBundle\Security\Html\StripPageBuilderHtmlSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StripPageBuilderHtmlSanitizer::class)]
final class StripPageBuilderHtmlSanitizerTest extends TestCase
{
    #[Test]
    public function removesAllHtmlTags(): void
    {
        $sanitizer = new StripPageBuilderHtmlSanitizer();

        self::assertSame(
            'Hello world',
            $sanitizer->sanitize('<p>Hello <em>world</em></p>'),
        );
    }
}
