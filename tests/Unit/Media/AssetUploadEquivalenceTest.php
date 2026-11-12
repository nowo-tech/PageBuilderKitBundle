<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Media;

use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Media\AssetUpload;
use Nowo\PageBuilderKitBundle\Media\AssetUploadData;
use Nowo\PageBuilderKitBundle\Media\AssetUploadHandler;
use Nowo\PageBuilderKitBundle\Media\AssetUploadResult;
use Nowo\PageBuilderKitBundle\Media\AwsS3AssetStorage;
use Nowo\PageBuilderKitBundle\Media\LocalFilesystemAssetStorage;
use Nowo\PageBuilderKitBundle\Media\PageBuilderAssetStorageInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

use function base64_decode;
use function file_get_contents;
use function file_put_contents;
use function hash;
use function is_file;
use function json_encode;
use function preg_replace;
use function str_repeat;
use function sys_get_temp_dir;
use function uniqid;

use const UPLOAD_ERR_OK;
use const UPLOAD_ERR_PARTIAL;

/**
 * 1.15 equivalence: the same uploads give the same outcome through the deprecated `store(UploadedFile)` and through the
 * new `storeUpload(AssetUpload)` (local and S3 storages), and through the upload handler with a storage on the
 * deprecated port only and with one on the new port. Same accepted / rejected files (upload error, size limit,
 * server-side MIME allowlist, polyglot with a script extension, `..` in the name), same exception messages, same
 * stored content, MIME type, extension, name and image size.
 */
#[CoversClass(LocalFilesystemAssetStorage::class)]
#[CoversClass(AwsS3AssetStorage::class)]
#[CoversClass(AssetUploadHandler::class)]
#[CoversClass(AssetUpload::class)]
#[CoversClass(AssetUploadData::class)]
final class AssetUploadEquivalenceTest extends TestCase
{
    private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private const string GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/pbk-eq-' . uniqid('', true);
        new Filesystem()->mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->dir);
    }

    /**
     * @return iterable<string, array{string, string, string, int, bool}>
     */
    public static function uploads(): iterable
    {
        $png = base64_decode(self::PNG, true);
        $gif = base64_decode(self::GIF, true);

        // content, client name, client MIME type, upload error, accepted by both storages
        yield 'png' => [$png, 'hero.png', 'image/png', UPLOAD_ERR_OK, true];
        yield 'gif, upper-case extension' => [$gif, 'Anim.GIF', 'image/gif', UPLOAD_ERR_OK, true];
        yield 'png without extension' => [$png, 'photo', 'image/png', UPLOAD_ERR_OK, true];
        yield 'png named .jpg' => [$png, 'hero.jpg', 'image/jpeg', UPLOAD_ERR_OK, false];
        yield 'GIF + PHP polyglot named .php' => [$gif . '<?php echo 1;', 'x.php', 'image/gif', UPLOAD_ERR_OK, false];
        yield 'PHP named .png, client says image/png' => ["<?php echo 'x';\n", 'evil.png', 'image/png', UPLOAD_ERR_OK, false];
        yield 'SVG (not allowed by default)' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'logo.svg', 'image/svg+xml', UPLOAD_ERR_OK, false];
        yield 'oversized png' => [$png . str_repeat("\0", 400), 'big.png', 'image/png', UPLOAD_ERR_OK, false];
        yield 'dots in the name' => [$png, 'a..b.png', 'image/png', UPLOAD_ERR_OK, true];
        yield 'partial upload' => [$png, 'part.png', 'image/png', UPLOAD_ERR_PARTIAL, false];
    }

    #[Test]
    #[DataProvider('uploads')]
    public function storageGivesTheSameOutcomeOnBothPorts(string $content, string $name, string $clientMime, int $error, bool $accepted): void
    {
        foreach (['local', 's3'] as $kind) {
            $old = $this->outcome($kind, $content, $name, $clientMime, $error, static fn (LocalFilesystemAssetStorage|AwsS3AssetStorage $storage, UploadedFile $file): AssetUploadResult => $storage->store($file));
            $new = $this->outcome($kind, $content, $name, $clientMime, $error, static function (LocalFilesystemAssetStorage|AwsS3AssetStorage $storage, UploadedFile $file): AssetUploadResult {
                // What AssetUploadHandler does for a storage on the 1.15 port.
                if (!$file->isValid()) {
                    throw new InvalidArgumentException('Invalid uploaded file.');
                }

                return $storage->storeUpload(AssetUploadData::fromUploadedFile($file));
            });
            self::assertSame($old, $new, $kind . ': ' . $name);
        }

        $local = $this->outcome('local', $content, $name, $clientMime, $error, static fn (LocalFilesystemAssetStorage|AwsS3AssetStorage $storage, UploadedFile $file): AssetUploadResult => $storage->store($file));
        if ($name === 'a..b.png') {
            // The local storage rejects ".." in the client name; the S3 storage never did (unchanged).
            self::assertSame('error', $local['outcome']);
            self::assertSame('ok', $this->outcome('s3', $content, $name, $clientMime, $error, static fn (LocalFilesystemAssetStorage|AwsS3AssetStorage $storage, UploadedFile $file): AssetUploadResult => $storage->store($file))['outcome']);
        } else {
            self::assertSame($accepted ? 'ok' : 'error', $local['outcome'], $name);
        }
    }

    #[Test]
    #[DataProvider('uploads')]
    public function handlerGivesTheSameOutcomeForAStorageOnEitherPort(string $content, string $name, string $clientMime, int $error, bool $accepted): void
    {
        $legacy = $this->handlerOutcome(true, $content, $name, $clientMime, $error);
        $new    = $this->handlerOutcome(false, $content, $name, $clientMime, $error);

        self::assertSame($legacy, $new, $name);
    }

    /**
     * @param callable(AwsS3AssetStorage|LocalFilesystemAssetStorage, UploadedFile): AssetUploadResult $store
     *
     * @return array<string, mixed>
     */
    private function outcome(string $kind, string $content, string $name, string $clientMime, int $error, callable $store): array
    {
        $run  = $this->dir . '/' . uniqid('', true);
        $path = $run . '-src';
        file_put_contents($path, $content);
        $file    = new UploadedFile($path, $name, $clientMime, $error, true);
        $helper  = $this->s3Helper();
        $storage = $kind === 'local'
            ? new LocalFilesystemAssetStorage($run . '/public', '/uploads', 300, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
            : new AwsS3AssetStorage($helper, 'page-builder', false, 300, ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

        try {
            $result = $store($storage, $file);
        } catch (Throwable $exception) {
            return ['outcome' => 'error', 'class' => $exception::class, 'message' => $exception->getMessage()];
        }

        $stored = $kind === 'local' ? $run . '/public/' . $result->storageKey : null;

        return [
            'outcome' => 'ok',
            'src'     => $this->mask($result->src),
            'name'    => $result->name,
            'type'    => $result->type,
            'width'   => $result->width,
            'height'  => $result->height,
            'mime'    => $result->mimeType,
            'key'     => $this->mask((string) $result->storageKey),
            'content' => $stored !== null && is_file($stored) ? hash('sha256', (string) file_get_contents($stored)) : ($helper->calls[0] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handlerOutcome(bool $legacyPort, string $content, string $name, string $clientMime, int $error): array
    {
        $run  = $this->dir . '/' . uniqid('', true);
        $path = $run . '-src';
        file_put_contents($path, $content);
        $local   = new LocalFilesystemAssetStorage($run . '/public', '/uploads', 300, ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
        $storage = $legacyPort ? new LegacyOnlyStorage($local) : $local;

        try {
            $assets = new AssetUploadHandler($storage, true)->handle([new UploadedFile($path, $name, $clientMime, $error, true)]);
        } catch (Throwable $exception) {
            return ['outcome' => 'error', 'class' => $exception::class, 'message' => $exception->getMessage()];
        }

        return ['outcome' => 'ok', 'assets' => $this->mask((string) json_encode($assets))];
    }

    private function mask(string $value): string
    {
        return (string) preg_replace('/[0-9a-f]{32}/', 'HEX', $value);
    }

    private function s3Helper(): object
    {
        return new class {
            public string $_bucketName = 'bucket';

            /** @var list<array{string, string, string, bool, string}> */
            public array $calls = [];

            public function uploadFile(string $filePath, string $fileType, string $key, bool $private = false, string $dispositionType = 'inline', ?string $bucket = null): string
            {
                $this->calls[] = [hash('sha256', (string) file_get_contents($filePath)), $fileType, (string) preg_replace('/[0-9a-f]{32}/', 'HEX', $key), $private, $dispositionType];

                return 'https://cdn.example/' . $key;
            }

            /** @param array<string, mixed> $config */
            public function getFileURL(string $bucket, string $key, array $config = []): string
            {
                return 'https://fallback.example/' . $key;
            }
        };
    }
}

/**
 * A pre-1.15 host storage: the deprecated port only (delegates to the local storage's deprecated method).
 */
final readonly class LegacyOnlyStorage implements PageBuilderAssetStorageInterface
{
    public function __construct(private LocalFilesystemAssetStorage $inner)
    {
    }

    public function store(UploadedFile $file): AssetUploadResult
    {
        return $this->inner->store($file);
    }
}
