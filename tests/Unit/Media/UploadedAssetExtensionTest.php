<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Media;

use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Media\UploadedAssetExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UploadedAssetExtension::class)]
final class UploadedAssetExtensionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function acceptedProvider(): iterable
    {
        yield 'jpeg canonical' => ['image/jpeg', 'jpg', 'jpg'];
        yield 'jpeg alias' => ['image/jpeg', 'JPEG', 'jpg'];
        yield 'png' => ['image/png', 'png', 'png'];
        yield 'gif' => ['image/gif', 'gif', 'gif'];
        yield 'webp' => ['image/webp', 'webp', 'webp'];
        yield 'avif' => ['image/avif', 'avif', 'avif'];
        yield 'svg (opt-in)' => ['image/svg+xml', 'svg', 'svg'];
        yield 'no client extension' => ['image/gif', '', 'gif'];
        yield 'unknown mime is bin' => ['application/x-custom', 'php', 'bin'];
    }

    #[Test]
    #[DataProvider('acceptedProvider')]
    public function derivesExtensionFromDetectedMime(string $mime, string $clientExtension, string $expected): void
    {
        self::assertSame($expected, UploadedAssetExtension::resolve($mime, $clientExtension));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function mismatchProvider(): iterable
    {
        yield 'gif polyglot named php' => ['image/gif', 'php'];
        yield 'phtml' => ['image/png', 'phtml'];
        yield 'phar' => ['image/jpeg', 'phar'];
        yield 'html' => ['image/webp', 'html'];
        yield 'svg named png' => ['image/svg+xml', 'png'];
        yield 'punctuation only' => ['image/png', '.'];
    }

    #[Test]
    #[DataProvider('mismatchProvider')]
    public function rejectsClientExtensionThatDoesNotMatchDetectedMime(string $mime, string $clientExtension): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not match detected MIME type');

        UploadedAssetExtension::resolve($mime, $clientExtension);
    }
}
