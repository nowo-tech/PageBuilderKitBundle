<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Media;

use Nowo\PageBuilderKitBundle\Media\LocalFilesystemAssetStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function file_put_contents;
use function sys_get_temp_dir;
use function uniqid;

#[CoversClass(LocalFilesystemAssetStorage::class)]
final class LocalFilesystemAssetStorageTest extends TestCase
{
    #[Test]
    public function storesValidImageUnderPublicPrefix(): void
    {
        $dir = sys_get_temp_dir() . '/pbk-upload-' . uniqid('', true);
        $storage = new LocalFilesystemAssetStorage($dir, '/uploads/page-builder', 5_000_000, ['image/png']);

        $tmp = sys_get_temp_dir() . '/pbk-src-' . uniqid('', true) . '.png';
        // Minimal 1x1 PNG
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
        self::assertNotFalse($png);
        file_put_contents($tmp, $png);

        $upload = new UploadedFile($tmp, 'pixel.png', 'image/png', null, true);
        $result = $storage->store($upload);

        self::assertStringStartsWith('/uploads/page-builder/', $result->src);
        self::assertSame('image', $result->type);
        self::assertSame('pixel.png', $result->name);
        self::assertFileExists($dir . '/' . $result->storageKey);
    }

    #[Test]
    public function rejectsDisallowedMime(): void
    {
        $dir = sys_get_temp_dir() . '/pbk-upload-' . uniqid('', true);
        $storage = new LocalFilesystemAssetStorage($dir, '/uploads/page-builder', 5_000_000, ['image/png']);

        $tmp = sys_get_temp_dir() . '/pbk-src-' . uniqid('', true) . '.txt';
        file_put_contents($tmp, 'not an image');
        $upload = new UploadedFile($tmp, 'note.txt', 'text/plain', null, true);

        $this->expectException(\InvalidArgumentException::class);
        $storage->store($upload);
    }

    #[Test]
    public function rejectsOversizedFile(): void
    {
        $dir     = sys_get_temp_dir() . '/pbk-upload-' . uniqid('', true);
        $storage = new LocalFilesystemAssetStorage($dir, '/uploads/page-builder', 5, ['image/png']);
        $tmp     = sys_get_temp_dir() . '/pbk-src-' . uniqid('', true) . '.png';
        file_put_contents($tmp, str_repeat('a', 20));

        $this->expectException(\InvalidArgumentException::class);
        $storage->store(new UploadedFile($tmp, 'big.png', 'image/png', null, true));
    }

    #[Test]
    public function rejectsPathTraversalInOriginalName(): void
    {
        $dir     = sys_get_temp_dir() . '/pbk-upload-' . uniqid('', true);
        $storage = new LocalFilesystemAssetStorage($dir, '/uploads/page-builder', 5_000_000, ['image/png']);
        $tmp     = sys_get_temp_dir() . '/pbk-src-' . uniqid('', true) . '.png';
        $png     = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
        self::assertNotFalse($png);
        file_put_contents($tmp, $png);

        // Symfony UploadedFile strips directory segments from the client name; stub preserves "..".
        $upload = new class($tmp, '../evil.png', 'image/png', null, true) extends UploadedFile {
            public function getClientOriginalName(): string
            {
                return '../evil.png';
            }
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid file name.');
        $storage->store($upload);
    }
}
