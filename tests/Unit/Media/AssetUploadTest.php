<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Media;

use Error;
use InvalidArgumentException;
use LogicException;
use Nowo\PageBuilderKitBundle\Media\AssetUpload;
use Nowo\PageBuilderKitBundle\Media\AssetUploadData;
use Nowo\PageBuilderKitBundle\Media\AssetUploadHandler;
use Nowo\PageBuilderKitBundle\Media\AssetUploadResult;
use Nowo\PageBuilderKitBundle\Media\PageBuilderAssetUploadStorageInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

use function base64_decode;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function json_encode;
use function print_r;
use function serialize;
use function stream_get_contents;
use function sys_get_temp_dir;
use function uniqid;
use function var_export;

use const UPLOAD_ERR_OK;
use const UPLOAD_ERR_PARTIAL;

#[CoversClass(AssetUpload::class)]
#[CoversClass(AssetUploadData::class)]
#[CoversClass(AssetUploadHandler::class)]
final class AssetUploadTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/pbk-vo-' . uniqid('', true);
        new Filesystem()->mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->dir);
    }

    #[Test]
    public function accessorsStreamAndServerSideDetection(): void
    {
        $png    = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
        $upload = new AssetUpload($this->file('tmp', $png), 'Hero.Final.PNG', 70);

        self::assertSame('Hero.Final.PNG', $upload->getClientName());
        self::assertSame('PNG', $upload->getClientExtension());
        self::assertSame(70, $upload->getSize());
        self::assertSame('image/png', $upload->getMimeType(), 'detected from the content when not given');

        $stream = $upload->openStream();
        self::assertSame($png, stream_get_contents($stream));
        fclose($stream);

        self::assertSame('image/webp', new AssetUpload($this->file('tmp2', 'x'), 'x', 1, 'image/webp')->getMimeType());
        self::assertNull(new AssetUpload($this->dir . '/missing', 'x')->getMimeType());
    }

    #[Test]
    public function moveKeepsTheLastSegmentAndFollowsTheFile(): void
    {
        $upload = new AssetUpload($this->file('tmp', 'data'), 'x.png');
        $target = $upload->moveTo($this->dir . '/out', '..\\../evil.png');

        self::assertSame($this->dir . '/out/evil.png', $target);
        self::assertSame('data', file_get_contents($target));
        $stream = $upload->openStream();
        self::assertSame('data', stream_get_contents($stream));
        fclose($stream);
        self::assertSame($this->dir . '/out2/evil.png', $upload->moveTo($this->dir . '/out2'));
    }

    #[Test]
    public function moveOfANonHttpUploadFailsLikeUploadedFileMove(): void
    {
        $upload = AssetUploadData::fromUploadedFile(new UploadedFile($this->file('php1', 'data'), 'x.png', null, UPLOAD_ERR_OK, false));

        $this->expectException(RuntimeException::class);
        $upload->moveTo($this->dir . '/out', 'x.png');
    }

    #[Test]
    public function openStreamOfAMissingFileFails(): void
    {
        $this->expectException(RuntimeException::class);
        new AssetUpload($this->dir . '/missing', 'x.png', null, 'image/png')->openStream();
    }

    #[Test]
    public function nothingLeaksThroughDumpsOrCasts(): void
    {
        $upload = new AssetUpload($this->file('phpSECRETPATH', 'x'), 'private-client-name.png', 1, 'image/png');

        $dump = new CliDumper()->dump(new VarCloner()->cloneVar($upload), true);
        foreach ([var_export($upload, true), print_r($upload, true), (string) json_encode($upload), (string) $dump, print_r((array) $upload, true)] as $output) {
            self::assertStringNotContainsString('phpSECRETPATH', $output);
            self::assertStringNotContainsString('private-client-name', $output);
        }
    }

    #[Test]
    public function cannotBeSerialized(): void
    {
        $this->expectException(LogicException::class);
        serialize(new AssetUpload($this->file('tmp', 'x'), 'x.png', 1, 'image/png'));
    }

    #[Test]
    public function cannotBeUnserialized(): void
    {
        $this->expectException(LogicException::class);
        new AssetUpload($this->file('tmp', 'x'), 'x.png', 1, 'image/png')->__unserialize([]);
    }

    #[Test]
    public function cannotBeCloned(): void
    {
        $upload = new AssetUpload($this->file('tmp', 'x'), 'x.png', 1, 'image/png');

        $this->expectException(Error::class);
        $copy = clone $upload;
        self::assertNotSame($upload, $copy);
    }

    #[Test]
    public function handlerRejectsAnInvalidUploadBeforeTheNewPort(): void
    {
        $storage = new class implements PageBuilderAssetUploadStorageInterface {
            public int $calls = 0;

            public function storeUpload(AssetUpload $upload): AssetUploadResult
            {
                ++$this->calls;

                return new AssetUploadResult('/x', $upload->getClientName());
            }
        };
        $handler = new AssetUploadHandler($storage, true);

        self::assertSame([['src' => '/x', 'type' => 'image', 'name' => 'ok.png']], $handler->handle([new UploadedFile($this->file('ok', 'x'), 'ok.png', 'image/png', null, true)]));

        try {
            $handler->handle([new UploadedFile($this->file('bad', 'x'), 'bad.png', 'image/png', UPLOAD_ERR_PARTIAL, true)]);
            self::fail('An invalid upload must be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Invalid uploaded file.', $exception->getMessage());
        }
        self::assertSame(1, $storage->calls);
    }

    private function file(string $name, string $content): string
    {
        $path = $this->dir . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }
}
