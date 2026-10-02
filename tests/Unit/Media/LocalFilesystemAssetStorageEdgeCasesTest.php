<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Media;

use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Media\LocalFilesystemAssetStorage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function file_put_contents;
use function sys_get_temp_dir;
use function uniqid;

final class LocalFilesystemAssetStorageEdgeCasesTest extends TestCase
{
    #[Test]
    public function storeCreatesDirectoryAndUsesMimeExtensionFallback(): void
    {
        $dir     = sys_get_temp_dir() . '/pbk-nodir-' . uniqid('', true);
        $storage = new LocalFilesystemAssetStorage($dir, '/media', 5_000_000, ['image/gif', 'image/svg+xml']);
        $tmp     = sys_get_temp_dir() . '/pbk-gif-' . uniqid('', true);
        file_put_contents($tmp, 'GIF89a');

        $result = $storage->store(new UploadedFile($tmp, '', 'image/gif', null, true));

        self::assertDirectoryExists($dir);
        self::assertStringStartsWith('/media/', $result->src);
        self::assertStringEndsWith('.gif', $result->storageKey ?? '');
    }

    #[Test]
    public function listReturnsEmptyWhenDirectoryMissingOrNotReadable(): void
    {
        $missing = new LocalFilesystemAssetStorage(
            sys_get_temp_dir() . '/pbk-missing-' . uniqid('', true),
            '/media',
        );
        self::assertSame([], $missing->list(10));
        self::assertSame([], $missing->list(0));

        $fileAsDir = sys_get_temp_dir() . '/pbk-file-' . uniqid('', true);
        file_put_contents($fileAsDir, 'x');
        $storage = new LocalFilesystemAssetStorage($fileAsDir, '/media');
        self::assertSame([], $storage->list(5));

        $dir = sys_get_temp_dir() . '/pbk-list-' . uniqid('', true);
        mkdir($dir);
        mkdir($dir . '/subdir');
        file_put_contents($dir . '/ok.png', 'x');
        $listed = (new LocalFilesystemAssetStorage($dir, '/media'))->list(10);
        self::assertCount(1, $listed);
        self::assertSame('ok.png', $listed[0]['name']);
    }

    #[Test]
    public function storeRejectsInvalidUpload(): void
    {
        $storage = new LocalFilesystemAssetStorage(sys_get_temp_dir(), '/media', 5_000_000, ['image/png']);
        $upload  = new UploadedFile(__FILE__, 'x.png', 'image/png', null, false);

        $this->expectException(InvalidArgumentException::class);
        $storage->store($upload);
    }

    #[Test]
    public function storeRejectsDisallowedMimeAfterDetection(): void
    {
        $dir     = sys_get_temp_dir() . '/pbk-upload-' . uniqid('', true);
        $storage = new LocalFilesystemAssetStorage($dir, '/media', 5_000_000, ['image/png']);
        $tmp     = sys_get_temp_dir() . '/pbk-txt-' . uniqid('', true);
        file_put_contents($tmp, 'plain');

        $this->expectException(InvalidArgumentException::class);
        $storage->store(new UploadedFile($tmp, 'note.txt', 'text/plain', null, true));
    }
}
