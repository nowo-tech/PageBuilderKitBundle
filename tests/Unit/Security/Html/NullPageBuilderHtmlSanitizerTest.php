<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Security\Html;

use Nowo\PageBuilderKitBundle\Security\Html\NullPageBuilderHtmlSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullPageBuilderHtmlSanitizer::class)]
final class NullPageBuilderHtmlSanitizerTest extends TestCase
{
    #[Test]
    public function returnsHtmlUnchanged(): void
    {
        $html = '<p>keep</p>';

        self::assertSame($html, (new NullPageBuilderHtmlSanitizer())->sanitize($html));
    }
}
