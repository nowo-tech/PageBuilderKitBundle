<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Media;

use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Media\AssetUpload;
use Nowo\PageBuilderKitBundle\Media\AssetUploadHandler;
use Nowo\PageBuilderKitBundle\Media\AssetUploadResult;
use Nowo\PageBuilderKitBundle\Media\PageBuilderAssetLibraryInterface;
use Nowo\PageBuilderKitBundle\Media\PageBuilderAssetUploadStorageInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function is_string;
use function sys_get_temp_dir;
use function uniqid;

use const UPLOAD_ERR_NO_FILE;

#[CoversClass(AssetUploadHandler::class)]
final class AssetUploadHandlerTest extends TestCase
{
    #[Test]
    public function isEnabledReflectsConstructorFlag(): void
    {
        $storage = $this->createStub(PageBuilderAssetUploadStorageInterface::class);
        self::assertFalse(new AssetUploadHandler($storage)->isEnabled());
        self::assertTrue(new AssetUploadHandler($storage, true)->isEnabled());
    }

    #[Test]
    public function handleStoresValidFiles(): void
    {
        $tmp = sys_get_temp_dir() . '/pbk-' . uniqid('', true) . '.png';
        file_put_contents($tmp, 'x');
        $upload = new UploadedFile($tmp, 'x.png', 'image/png', null, true);

        $storage = new class implements PageBuilderAssetUploadStorageInterface {
            public function storeUpload(AssetUpload $upload): AssetUploadResult
            {
                return new AssetUploadResult('/x.png', 'x.png');
            }
        };

        $assets = new AssetUploadHandler($storage, true)->handle([$upload, null]);

        self::assertSame('/x.png', $assets[0]['src']);
    }

    #[Test]
    public function handleLogsUploadCountAndRejections(): void
    {
        $tmp = sys_get_temp_dir() . '/pbk-' . uniqid('', true) . '.png';
        file_put_contents($tmp, 'x');
        $upload  = new UploadedFile($tmp, 'x.png', 'image/png', null, true);
        $storage = new class implements PageBuilderAssetUploadStorageInterface {
            public function storeUpload(AssetUpload $upload): AssetUploadResult
            {
                return new AssetUploadResult('/x.png', 'x.png');
            }
        };
        $logger = new class extends AbstractLogger {
            /** @var list<array{string, string, array<mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [is_string($level) ? $level : 'unknown', (string) $message, $context];
            }
        };
        $handler = new AssetUploadHandler($storage, true, $logger);

        $handler->handle([$upload]);
        try {
            $handler->handle([null]);
        } catch (InvalidArgumentException) {
        }

        self::assertSame(['info', 'Page builder assets uploaded.'], [$logger->records[0][0], $logger->records[0][1]]);
        self::assertSame(1, $logger->records[0][2]['count']);
        self::assertSame('warning', $logger->records[1][0]);
    }

    #[Test]
    public function handleThrowsWhenDisabledOrEmpty(): void
    {
        $storage = $this->createStub(PageBuilderAssetUploadStorageInterface::class);

        $this->expectException(RuntimeException::class);
        new AssetUploadHandler($storage, false)->handle([]);
    }

    #[Test]
    public function handleThrowsWhenNoValidFiles(): void
    {
        $storage = $this->createStub(PageBuilderAssetUploadStorageInterface::class);

        $this->expectException(InvalidArgumentException::class);
        new AssetUploadHandler($storage, true)->handle([null]);
    }

    /** The check every built-in storage ran on the UploadedFile: an invalid upload never reaches the storage. */
    #[Test]
    public function handleRejectsAnInvalidUploadBeforeTheStorage(): void
    {
        $tmp = sys_get_temp_dir() . '/pbk-' . uniqid('', true) . '.png';
        file_put_contents($tmp, 'x');
        $storage = new class implements PageBuilderAssetUploadStorageInterface {
            public int $calls = 0;

            public function storeUpload(AssetUpload $upload): AssetUploadResult
            {
                ++$this->calls;

                return new AssetUploadResult('/x.png', 'x.png');
            }
        };

        try {
            new AssetUploadHandler($storage, true)->handle([new UploadedFile($tmp, 'x.png', 'image/png', UPLOAD_ERR_NO_FILE, true)]);
            self::fail('An invalid upload must be refused.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Invalid uploaded file.', $exception->getMessage());
        }
        self::assertSame(0, $storage->calls);
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
        $storage = new class implements PageBuilderAssetUploadStorageInterface, PageBuilderAssetLibraryInterface {
            public function storeUpload(AssetUpload $upload): AssetUploadResult
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

        $plain    = $this->createStub(PageBuilderAssetUploadStorageInterface::class);
        $disabled = new AssetUploadHandler($plain, false);
        self::assertFalse($disabled->supportsLibrary());
        self::assertSame([], $disabled->listLibrary());

        $enabledWithoutLibrary = new AssetUploadHandler($plain, true);
        self::assertFalse($enabledWithoutLibrary->supportsLibrary());
        self::assertSame([], $enabledWithoutLibrary->listLibrary());
    }
}
