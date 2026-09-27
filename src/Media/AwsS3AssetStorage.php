<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Media;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function bin2hex;
use function class_exists;
use function finfo_file;
use function finfo_open;
use function getimagesize;
use function in_array;
use function is_array;
use function is_object;
use function is_string;
use function ltrim;
use function method_exists;
use function pathinfo;
use function preg_replace;
use function property_exists;
use function random_bytes;
use function rtrim;
use function sprintf;
use function strtolower;
use function trim;

use const FILEINFO_MIME_TYPE;

/**
 * Stores uploads via core/aws-s3-bundle AwsS3Helper (optional dependency).
 *
 * Expects an object exposing uploadFile() + getFileURL() (AwsS3Helper).
 */
final class AwsS3AssetStorage implements PageBuilderAssetStorageInterface
{
    /**
     * @param list<string> $allowedMimeTypes
     */
    public function __construct(
        private readonly object $s3Helper,
        private readonly string $folder = 'page-builder',
        private readonly bool $private = false,
        private readonly int $maxBytes = 5_242_880,
        private readonly array $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
        ],
        private readonly ?string $publicBaseUrl = null,
    ) {
        if (!method_exists($this->s3Helper, 'uploadFile') || !method_exists($this->s3Helper, 'getFileURL')) {
            throw new InvalidArgumentException('S3 helper must expose uploadFile() and getFileURL().');
        }
    }

    public function store(UploadedFile $file): AssetUploadResult
    {
        $this->assertValidUpload($file);

        $mime = $this->resolveMimeType($file) ?: 'application/octet-stream';
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: match ($mime) {
            // @codeCoverageIgnoreStart — finfo usually supplies png/jpeg from bytes before these arms
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => 'bin',
            // @codeCoverageIgnoreEnd
        }));
        $extension = preg_replace('/[^a-z0-9]+/', '', $extension) ?: 'bin';
        $folder    = trim($this->folder, '/');
        $key       = ($folder !== '' ? $folder . '/' : '') . bin2hex(random_bytes(16)) . '.' . $extension;
        $path      = $file->getRealPath();
        if (!is_string($path) || $path === '') {
            // @codeCoverageIgnoreStart
            throw new RuntimeException('Uploaded file has no temporary path.');
            // @codeCoverageIgnoreEnd
        }

        /** @var mixed $result */
        $result = $this->s3Helper->uploadFile($path, $mime, $key, $this->private, 'inline');

        $bucket = $this->resolveBucket();
        $src    = $this->resolvePublicUrl($result, $bucket, $key);
        $name   = $file->getClientOriginalName() !== '' ? $file->getClientOriginalName() : pathinfo($key, PATHINFO_BASENAME);

        $width = $height = null;
        $size  = @getimagesize($path);
        if (is_array($size)) {
            $width  = (int) $size[0];
            $height = (int) $size[1];
        }

        return new AssetUploadResult(
            src: $src,
            name: $name,
            type: 'image',
            width: $width,
            height: $height,
            mimeType: $mime,
            storageKey: $key,
        );
    }

    private function resolveBucket(): string
    {
        if (property_exists($this->s3Helper, '_bucketName') && is_string($this->s3Helper->_bucketName) && $this->s3Helper->_bucketName !== '') {
            return $this->s3Helper->_bucketName;
        }

        if (method_exists($this->s3Helper, 'getConfiguration')) {
            /** @var mixed $config */
            $config = $this->s3Helper->getConfiguration();
            if (is_array($config) && is_string($config['bucket'] ?? null) && $config['bucket'] !== '') {
                return $config['bucket'];
            }
        }

        throw new RuntimeException('Unable to resolve S3 bucket name from helper.');
    }

    private function resolvePublicUrl(mixed $result, string $bucket, string $key): string
    {
        if (is_string($this->publicBaseUrl) && $this->publicBaseUrl !== '') {
            return rtrim($this->publicBaseUrl, '/') . '/' . ltrim($key, '/');
        }

        if (is_object($result) && method_exists($result, 'get')) {
            $objectUrl = $result->get('ObjectURL');
            if (is_string($objectUrl) && $objectUrl !== '') {
                return $objectUrl;
            }
        }

        if (is_array($result)) {
            $objectUrl = $result['ObjectURL'] ?? $result['url'] ?? null;
            if (is_string($objectUrl) && $objectUrl !== '') {
                return $objectUrl;
            }
        }

        /** @var mixed $url */
        $url = $this->s3Helper->getFileURL($bucket, $key);
        if (!is_string($url) || $url === '') {
            throw new RuntimeException('S3 upload succeeded but no public URL was returned.');
        }

        return $url;
    }

    private function assertValidUpload(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new InvalidArgumentException('Invalid uploaded file.');
        }

        if ($file->getSize() !== null && $file->getSize() > $this->maxBytes) {
            throw new InvalidArgumentException(sprintf('File exceeds max size of %d bytes.', $this->maxBytes)); // covered in rejectsOversized
        }

        $mime = $this->resolveMimeType($file);
        if ($mime === '' || !in_array($mime, $this->allowedMimeTypes, true)) {
            throw new InvalidArgumentException(sprintf('MIME type "%s" is not allowed.', $mime !== '' ? $mime : 'unknown'));
        }
    }

    private function resolveMimeType(UploadedFile $file): string
    {
        $path = $file->getPathname();
        if ($path !== '' && class_exists(\finfo::class)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_file($finfo, $path);
                if (is_string($detected) && $detected !== '') {
                    return $detected;
                }
            }
        }

        $client = (string) $file->getClientMimeType();
        if ($client !== '') {
            return $client;
        }

        // @codeCoverageIgnoreStart
        try {
            return (string) ($file->getMimeType() ?: '');
        } catch (\Throwable) {
            return '';
        }
        // @codeCoverageIgnoreEnd
    }
}
