<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Media;

use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Media\AssetUploadHandler;
use Nowo\PageBuilderKitBundle\Media\AssetUploadResult;
use Nowo\PageBuilderKitBundle\Media\PageBuilderAssetLibraryInterface;
use Nowo\PageBuilderKitBundle\Media\PageBuilderAssetStorageInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function sys_get_temp_dir;
use function uniqid;

#[CoversClass(AssetUploadHandler::class)]
final class AssetUploadHandlerTest extends TestCase
{
    #[Test]
    public function isEnabledReflectsConstructorFlag(): void
    {
        $storage = $this->createStub(PageBuilderAssetStorageInterface::class);
        self::assertFalse((new AssetUploadHandler($storage))->isEnabled());
        self::assertTrue((new AssetUploadHandler($storage, true))->isEnabled());
    }

    #[Test]
    public function handleStoresValidFiles(): void
    {
        $tmp = sys_get_temp_dir() . '/pbk-' . uniqid('', true) . '.png';
        file_put_contents($tmp, 'x');
        $upload = new UploadedFile($tmp, 'x.png', 'image/png', null, true);

        $storage = new class implements PageBuilderAssetStorageInterface {
            public function store(UploadedFile $file): AssetUploadResult
            {
                return new AssetUploadResult('/x.png', 'x.png');
            }
        };

        $assets = (new AssetUploadHandler($storage, true))->handle([$upload, null]);

        self::assertSame('/x.png', $assets[0]['src']);
    }

    #[Test]
    public function handleThrowsWhenDisabledOrEmpty(): void
    {
        $storage = $this->createStub(PageBuilderAssetStorageInterface::class);

        $this->expectException(RuntimeException::class);
        (new AssetUploadHandler($storage, false))->handle([]);
    }

    #[Test]
    public function handleThrowsWhenNoValidFiles(): void
    {
        $storage = $this->createStub(PageBuilderAssetStorageInterface::class);

        $this->expectException(InvalidArgumentException::class);
        (new AssetUploadHandler($storage, true))->handle([null]);
    }

    #[Test]
    public function normalizeFilesAcceptsSingleOrArray(): void
    {
        $file = new UploadedFile(__FILE__, 'f.php', null, null, true);

        self::assertSame([$file], AssetUploadHandler::normalizeFiles($file));
        self::assertSame([$file], AssetUploadHandler::normalizeFiles([$file, 'skip']));
        self::assertSame([], AssetUploadHandler::normalizeFiles([]));
        self::assertSame([], AssetUploadHandler::normalizeFiles('bad'));
    }

    #[Test]
    public function listLibraryDelegatesWhenStorageSupportsIt(): void
    {
        $storage = new class implements PageBuilderAssetStorageInterface, PageBuilderAssetLibraryInterface {
            public function store(UploadedFile $file): AssetUploadResult
            {
                return new AssetUploadResult('/x.png', 'x.png');
            }

            public function list(int $limit = 100): array
            {
                return [['src' => '/a.png', 'type' => 'image', 'name' => 'a.png']];
            }
        };

        $handler = new AssetUploadHandler($storage, true);
        self::assertTrue($handler->supportsLibrary());
        self::assertSame('/a.png', $handler->listLibrary()[0]['src']);

        $plain    = $this->createStub(PageBuilderAssetStorageInterface::class);
        $disabled = new AssetUploadHandler($plain, false);
        self::assertFalse($disabled->supportsLibrary());
        self::assertSame([], $disabled->listLibrary());
    }
}
