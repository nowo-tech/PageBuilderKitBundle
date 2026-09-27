<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Media;

use finfo;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

use function bin2hex;
use function class_exists;
use function finfo_file;
use function finfo_open;
use function getimagesize;
use function in_array;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function mkdir;
use function preg_replace;
use function random_bytes;
use function rtrim;
use function sprintf;
use function str_contains;
use function strtolower;
use function trim;

use const FILEINFO_MIME_TYPE;

/**
 * Validates and stores uploads on the local public filesystem.
 */
final readonly class LocalFilesystemAssetStorage implements PageBuilderAssetStorageInterface
{
    /**
     * @param list<string> $allowedMimeTypes
     */
    public function __construct(
        private string $directory,
        private string $publicPrefix,
        private int $maxBytes = 5_242_880,
        private array $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
        ],
    ) {
    }

    public function store(UploadedFile $file): AssetUploadResult
    {
        $this->assertValidUpload($file);

        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException(sprintf('Unable to create upload directory "%s".', $this->directory));
            // @codeCoverageIgnoreEnd
        }

        $mime      = $this->resolveMimeType($file);
        $extension = strtolower($file->getClientOriginalExtension() ?: $this->extensionFromMime($mime) ?: 'bin');
        $extension = preg_replace('/[^a-z0-9]+/', '', $extension) ?: 'bin';
        $basename  = bin2hex(random_bytes(16)) . '.' . $extension;
        $file->move($this->directory, $basename);

        $prefix = rtrim($this->publicPrefix, '/');
        $src    = $prefix . '/' . $basename;
        $name   = $file->getClientOriginalName() !== '' ? $file->getClientOriginalName() : $basename;

        $width = $height = null;
        $path  = $this->directory . '/' . $basename;
        if (is_file($path)) {
            $size = @getimagesize($path);
            if (is_array($size)) {
                $width  = (int) $size[0];
                $height = (int) $size[1];
            }
        }

        return new AssetUploadResult(
            src: $src,
            name: $name,
            type: 'image',
            width: $width,
            height: $height,
            mimeType: $mime !== '' ? $mime : null,
            storageKey: $basename,
        );
    }

    private function assertValidUpload(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new InvalidArgumentException('Invalid uploaded file.');
        }

        if ($file->getSize() !== null && $file->getSize() > $this->maxBytes) {
            throw new InvalidArgumentException(sprintf('File exceeds max size of %d bytes.', $this->maxBytes));
        }

        $mime = $this->resolveMimeType($file);
        if ($mime === '' || !in_array($mime, $this->allowedMimeTypes, true)) {
            throw new InvalidArgumentException(sprintf('MIME type "%s" is not allowed.', $mime !== '' ? $mime : 'unknown'));
        }

        $original = trim($file->getClientOriginalName());
        if ($original !== '' && str_contains($original, '..')) {
            throw new InvalidArgumentException('Invalid file name.');
        }
    }

    private function resolveMimeType(UploadedFile $file): string
    {
        $path = $file->getPathname();
        if ($path !== '' && class_exists(finfo::class)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_file($finfo, $path);
                if (is_string($detected) && $detected !== '') {
                    return $detected;
                }
            }
        }

        $client = $file->getClientMimeType();
        if ($client !== '') {
            return $client;
        }

        try {
            return $file->getMimeType() ?: '';
        } catch (Throwable) {
            return '';
        }
    }

    private function extensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg'    => 'jpg',
            'image/png'     => 'png',
            'image/gif'     => 'gif',
            'image/webp'    => 'webp',
            'image/svg+xml' => 'svg',
            default         => '',
        };
    }
}
