<?php

declare(strict_types=1);

namespace App\Demo;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use RuntimeException;
use Throwable;

use function basename;
use function count;
use function is_array;
use function is_file;
use function is_readable;
use function is_string;
use function ltrim;
use function rtrim;
use function sprintf;
use function usort;

/**
 * AwsS3Helper-compatible client for the demo S3 mock (Adobe S3Mock).
 *
 * Used by {@see \Nowo\PageBuilderKitBundle\Media\AwsS3AssetStorage} when
 * `grapesjs.assets_upload.storage: s3`.
 */
final class DemoS3Helper
{
    public string $_bucketName;

    private S3Client $client;

    public function __construct(
        string $endpoint,
        string $key,
        string $secret,
        string $bucket,
        private readonly string $publicBaseUrl,
        string $region = 'eu-central-1',
    ) {
        $this->_bucketName = $bucket;
        $this->client      = new S3Client([
            'version'                 => 'latest',
            'region'                  => $region,
            'endpoint'                => rtrim($endpoint, '/'),
            'use_path_style_endpoint' => true,
            'credentials'             => [
                'key'    => $key,
                'secret' => $secret,
            ],
        ]);
    }

    /**
     * @return array{ObjectURL: string}
     */
    public function uploadFile(
        string $filePath,
        string $fileType,
        string $key,
        bool $private = false,
        string $dispositionType = 'inline',
        ?string $bucket = null,
    ): array {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new RuntimeException(sprintf('Cannot read upload path "%s".', $filePath));
        }

        $bucket ??= $this->_bucketName;
        $objectKey = ltrim($key, '/');

        try {
            $params = [
                'Bucket'             => $bucket,
                'Key'                => $objectKey,
                'SourceFile'         => $filePath,
                'ContentType'        => $fileType,
                'ContentDisposition' => $dispositionType,
            ];
            if (!$private) {
                $params['ACL'] = 'public-read';
            }
            $this->client->putObject($params);
        } catch (S3Exception $exception) {
            // S3Mock may ignore/reject canned ACLs — retry without ACL.
            try {
                $this->client->putObject([
                    'Bucket'             => $bucket,
                    'Key'                => $objectKey,
                    'SourceFile'         => $filePath,
                    'ContentType'        => $fileType,
                    'ContentDisposition' => $dispositionType,
                ]);
            } catch (Throwable $retry) {
                throw new RuntimeException('S3 upload failed: ' . $retry->getMessage(), 0, $retry);
            }
            unset($exception);
        }

        return ['ObjectURL' => $this->getFileURL($bucket, $key)];
    }

    /**
     * @param array<string, mixed> $config
     */
    public function getFileURL(string $bucket, string $key, array $config = []): string
    {
        unset($bucket, $config);

        return rtrim($this->publicBaseUrl, '/') . '/' . ltrim($key, '/');
    }

    /**
     * @return array{bucket: string}
     */
    public function getConfiguration(): array
    {
        return ['bucket' => $this->_bucketName];
    }

    /**
     * Newest-first object keys under $prefix (for media library).
     *
     * @return list<array{key: string, name: string}>
     */
    public function listFiles(string $prefix = '', int $limit = 100): array
    {
        if ($limit < 1) {
            return [];
        }

        try {
            $result = $this->client->listObjectsV2([
                'Bucket'  => $this->_bucketName,
                'Prefix'  => ltrim($prefix, '/'),
                'MaxKeys' => $limit,
            ]);
        } catch (Throwable) {
            return [];
        }

        $contents = $result['Contents'] ?? [];
        if (!is_array($contents) || $contents === []) {
            return [];
        }

        usort(
            $contents,
            static function (mixed $a, mixed $b): int {
                $ta = is_array($a) && isset($a['LastModified']) ? (string) $a['LastModified'] : '';
                $tb = is_array($b) && isset($b['LastModified']) ? (string) $b['LastModified'] : '';

                return $tb <=> $ta;
            },
        );

        $out = [];
        foreach ($contents as $item) {
            if (count($out) >= $limit) {
                break;
            }
            if (!is_array($item) || !is_string($item['Key'] ?? null) || $item['Key'] === '') {
                continue;
            }
            $key   = $item['Key'];
            $out[] = [
                'key'  => $key,
                'name' => basename($key),
            ];
        }

        return $out;
    }
}
