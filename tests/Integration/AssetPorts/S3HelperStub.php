<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\AssetPorts;

/**
 * Minimal core/aws-s3-bundle helper shape for the `s3` storage variant.
 */
final class S3HelperStub
{
    public string $_bucketName = 'bucket';

    public function uploadFile(string $filePath, string $fileType, string $key, bool $private = false, string $dispositionType = 'inline', ?string $bucket = null): string
    {
        return 'https://cdn.example/' . $key;
    }

    /** @param array<string, mixed> $config */
    public function getFileURL(string $bucket, string $key, array $config = []): string
    {
        return 'https://cdn.example/' . $key;
    }
}
