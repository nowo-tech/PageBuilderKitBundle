<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Media;

use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Media\AwsS3AssetStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function file_put_contents;
use function sys_get_temp_dir;
use function uniqid;

#[CoversClass(AwsS3AssetStorage::class)]
final class AwsS3AssetStorageTest extends TestCase
{
    #[Test]
    public function uploadsViaHelperAndReturnsObjectUrl(): void
    {
        $helper = new class {
            public string $_bucketName = 'demo-bucket';

            /** @var list<array{0: string, 1: string, 2: string, 3: bool}> */
            public array $calls = [];

            public function uploadFile(string $filePath, string $fileType, string $key, bool $private = false, string $dispositionType = 'inline', ?string $bucket = null): object
            {
                $this->calls[] = [$filePath, $fileType, $key, $private];

                return new class($key) {
                    public function __construct(private readonly string $key)
                    {
                    }

                    public function get(string $name): mixed
                    {
                        return $name === 'ObjectURL' ? 'https://cdn.example/' . $this->key : null;
                    }
                };
            }

            /** @param array<string, mixed> $config */
            /** @param array<string, mixed> $config */
            public function getFileURL(string $bucket, string $key, array $config = []): string
            {
                return 'https://fallback.example/' . $key;
            }
        };

        $storage = new AwsS3AssetStorage($helper, 'page-builder', false, 5_000_000, ['image/png']);

        $tmp = sys_get_temp_dir() . '/pbk-s3-' . uniqid('', true) . '.png';
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
        self::assertNotFalse($png);
        file_put_contents($tmp, $png);

        $result = $storage->store(new UploadedFile($tmp, 'hero.png', 'image/png', null, true));

        self::assertStringStartsWith('https://cdn.example/page-builder/', $result->src);
        self::assertSame('hero.png', $result->name);
        self::assertCount(1, $helper->calls);
        self::assertFalse($helper->calls[0][3]);
    }

    #[Test]
    public function rejectsInvalidHelper(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AwsS3AssetStorage(new stdClass());
    }

    #[Test]
    public function resolvesPublicBaseUrlAndBucketFromConfiguration(): void
    {
        $helper = new class {
            /** @return array<string, string> */
            public function uploadFile(string $filePath, string $fileType, string $key, bool $private = false, string $dispositionType = 'inline', ?string $bucket = null): array
            {
                return ['url' => 'https://array.example/' . $key];
            }

            /** @param array<string, mixed> $config */
            public function getFileURL(string $bucket, string $key, array $config = []): string
            {
                return 'https://fallback.example/' . $key;
            }

            /** @return array<string, mixed> */
            public function getConfiguration(): array
            {
                return ['bucket' => 'cfg-bucket'];
            }
        };

        $storage = new AwsS3AssetStorage($helper, 'pb', false, 5_000_000, ['image/png'], 'https://cdn.example/base');

        $tmp = sys_get_temp_dir() . '/pbk-s3b-' . uniqid('', true) . '.png';
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
        self::assertNotFalse($png);
        file_put_contents($tmp, $png);

        $result = $storage->store(new UploadedFile($tmp, '', 'image/png', null, true));

        self::assertStringStartsWith('https://cdn.example/base/pb/', $result->src);
        self::assertSame('image/png', $result->mimeType);
    }

    #[Test]
    public function rejectsOversizedAndInvalidUploads(): void
    {
        $helper = new class {
            public function uploadFile(string $a, string $b, string $c, bool $d = false, string $e = 'inline', ?string $f = null): stdClass
            {
                return new stdClass();
            }

            /** @param array<string, mixed> $config */
            public function getFileURL(string $bucket, string $key, array $config = []): string
            {
                return 'https://x/' . $key;
            }

            public string $_bucketName = 'b';
        };

        $storage = new AwsS3AssetStorage($helper, 'pb', false, 10, ['image/png']);

        $this->expectException(InvalidArgumentException::class);
        $storage->store(new UploadedFile(__FILE__, 'big.png', 'image/png', 100, true));
    }
}
