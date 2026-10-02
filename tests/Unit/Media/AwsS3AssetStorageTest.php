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

use function count;
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
    public function listUsesHelperListFilesWhenAvailable(): void
    {
        $helper = new class {
            public string $_bucketName = 'demo-bucket';

            /** @return array<string, mixed> */
            public function uploadFile(string $a, string $b, string $c, bool $d = false, string $e = 'inline', ?string $f = null): array
            {
                return ['ObjectURL' => 'https://cdn.example/' . $c];
            }

            /** @param array<string, mixed> $config */
            public function getFileURL(string $bucket, string $key, array $config = []): string
            {
                return 'https://cdn.example/' . $key;
            }

            /** @return list<array{key: string, name: string}|string> */
            public function listFiles(string $prefix = '', int $limit = 100): array
            {
                return [
                    ['key' => $prefix . 'b.png', 'name' => 'b.png'],
                    $prefix . 'a.png',
                ];
            }
        };

        $storage = new AwsS3AssetStorage($helper, 'page-builder', false, 5_000_000, ['image/png'], 'https://cdn.example');
        $listed  = $storage->list(10);

        self::assertCount(2, $listed);
        self::assertSame('https://cdn.example/page-builder/b.png', $listed[0]['src']);
        self::assertSame('b.png', $listed[0]['name']);
        self::assertSame([], $storage->list(0));
    }

    #[Test]
    public function listReturnsEmptyWithoutListFilesHelper(): void
    {
        $helper = new class {
            public string $_bucketName = 'b';

            /** @return array<string, mixed> */
            public function uploadFile(string $a, string $b, string $c, bool $d = false, string $e = 'inline', ?string $f = null): array
            {
                return [];
            }

            /** @param array<string, mixed> $config */
            public function getFileURL(string $bucket, string $key, array $config = []): string
            {
                return 'https://x/' . $key;
            }
        };

        self::assertSame([], (new AwsS3AssetStorage($helper))->list());
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

        $invalid = new AwsS3AssetStorage($helper, 'pb', false, 10, ['image/png']);
        try {
            $invalid->store(new UploadedFile(__FILE__, 'big.png', 'image/png', 100, true));
            self::fail('Expected invalid upload rejection.');
        } catch (InvalidArgumentException) {
            // expected: invalid UploadedFile rejected
        }

        $tmp = sys_get_temp_dir() . '/pbk-s3-over-' . uniqid('', true) . '.png';
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
        self::assertNotFalse($png);
        file_put_contents($tmp, $png);

        $oversized = new AwsS3AssetStorage($helper, 'pb', false, 1, ['image/png']);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File exceeds max size');
        $oversized->store(new UploadedFile($tmp, 'tiny.png', 'image/png', null, true));
    }

    #[Test]
    public function listSkipsBadItemsAndUsesGetFileUrlFallback(): void
    {
        $helper = new class {
            public string $_bucketName = 'demo-bucket';

            /** @return array<string, mixed> */
            public function uploadFile(string $a, string $b, string $c, bool $d = false, string $e = 'inline', ?string $f = null): array
            {
                return ['ObjectURL' => 'https://cdn.example/' . $c];
            }

            /** @param array<string, mixed> $config */
            public function getFileURL(string $bucket, string $key, array $config = []): string
            {
                return $key === 'page-builder/empty.png' ? '' : 'https://cdn.example/' . $key;
            }

            /** @return list<mixed>|string */
            public function listFiles(string $prefix = '', int $limit = 100): array|string
            {
                if ($limit === 7) {
                    return 'bad';
                }

                return [
                    null,
                    '',
                    ['name' => 'no-key'],
                    ['key' => '', 'name' => 'empty-key'],
                    ['key' => $prefix . 'ok.png', 'name' => 'ok.png'],
                    $prefix . 'empty.png',
                    $prefix . 'second.png',
                    $prefix . 'third.png',
                ];
            }
        };

        $storage = new AwsS3AssetStorage($helper, 'page-builder', false, 5_000_000, ['image/png']);
        self::assertSame([], $storage->list(7));

        $listed = $storage->list(10);
        self::assertGreaterThanOrEqual(1, count($listed));
        self::assertSame('https://cdn.example/page-builder/ok.png', $listed[0]['src']);
        self::assertSame('ok.png', $listed[0]['name']);
        self::assertSame([], array_filter(
            $listed,
            static fn (array $row): bool => str_contains($row['src'], 'empty.png'),
        ));

        $limited = $storage->list(1);
        self::assertCount(1, $limited);
    }

    #[Test]
    public function fallsBackToClientMimeAndMimeBasedExtension(): void
    {
        $helper = new class {
            public string $_bucketName = 'demo-bucket';

            /** @return array<string, mixed> */
            public function uploadFile(string $filePath, string $fileType, string $key, bool $private = false, string $dispositionType = 'inline', ?string $bucket = null): array
            {
                return ['ObjectURL' => 'https://cdn.example/' . $key];
            }

            /** @param array<string, mixed> $config */
            public function getFileURL(string $bucket, string $key, array $config = []): string
            {
                return 'https://cdn.example/' . $key;
            }
        };

        $storage = new AwsS3AssetStorage($helper, 'pb', false, 5_000_000, [
            'image/jpeg',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'application/octet-stream',
        ]);

        foreach ([
            'image/jpeg'    => '.jpg',
            'image/gif'     => '.gif',
            'image/webp'    => '.webp',
            'image/svg+xml' => '.svg',
        ] as $mime => $ext) {
            $tmp = sys_get_temp_dir() . '/pbk-s3-mime-' . uniqid('', true);
            file_put_contents($tmp, 'not-an-image');

            $file = new class($tmp, $mime) extends UploadedFile {
                public function __construct(string $path, private readonly string $forcedMime)
                {
                    parent::__construct($path, 'asset', $forcedMime, null, true);
                }

                public function getClientOriginalExtension(): string
                {
                    return '';
                }

                public function getClientMimeType(): string
                {
                    return $this->forcedMime;
                }

                public function getPathname(): string
                {
                    return '';
                }
            };

            $result = $storage->store($file);
            self::assertStringEndsWith($ext, (string) $result->storageKey);
            self::assertSame($mime, $result->mimeType);
        }
    }
}
