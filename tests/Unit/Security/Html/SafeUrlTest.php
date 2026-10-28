<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Security\Html;

use Nowo\PageBuilderKitBundle\Security\Html\SafeUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SafeUrl::class)]
final class SafeUrlTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function cases(): iterable
    {
        yield 'https link' => ['https://example.com/a?b=1', false, true];
        yield 'relative' => ['/pricing', false, true];
        yield 'mailto' => ['mailto:team@example.com', false, true];
        yield 'javascript' => ['javascript:alert(1)', false, false];
        yield 'javascript with case and tab' => ["JaVa\tScRiPt:alert(1)", false, false];
        yield 'javascript with leading space' => [' javascript:alert(1)', false, false];
        yield 'vbscript' => ['vbscript:msgbox(1)', false, false];
        yield 'data html in href' => ['data:text/html;base64,PHNjcmlwdD4=', false, false];
        yield 'data image in href' => ['data:image/png;base64,AAAA', false, false];
        yield 'data png image in src' => ['data:image/png;base64,AAAA', true, true];
        yield 'data svg image in src' => ['data:image/svg+xml;base64,PHN2Zz4=', true, false];
    }

    #[DataProvider('cases')]
    public function testPolicy(string $url, bool $imageSource, bool $safe): void
    {
        self::assertSame($safe, SafeUrl::isSafe($url, $imageSource));
        self::assertSame($safe ? $url : '', SafeUrl::filter($url, $imageSource));
    }
}
