<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Media;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function bin2hex;
use function closedir;
use function count;
use function filemtime;
use function getimagesize;
use function in_array;
use function is_array;
use function is_dir;
use function is_file;
use function mkdir;
use function opendir;
use function random_bytes;
use function readdir;
use function rtrim;
use function sprintf;
use function str_contains;
use function trim;
use function usort;

/**
 * Validates and stores uploads on the local public filesystem.
 *
 * 1.15: {@see storeUpload()} (framework-free port) runs the same checks as {@see store()}; {@see store()} checks the
 * HTTP upload and delegates to it.
 */
final readonly class LocalFilesystemAssetStorage implements PageBuilderAssetUploadStorageInterface, PageBuilderAssetStorageInterface, PageBuilderAssetLibraryInterface
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
        ],
    ) {
    }

    /**
     * @deprecated since 1.15, use {@see storeUpload()}; removed in 2.0
     */
    public function store(UploadedFile $file): AssetUploadResult
    {
        if (!$file->isValid()) {
            throw new InvalidArgumentException('Invalid uploaded file.');
        }

        return $this->storeUpload(AssetUploadData::fromUploadedFile($file));
    }

    public function storeUpload(AssetUpload $upload): AssetUploadResult
    {
        $this->assertValidUpload($upload);

        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException(sprintf('Unable to create upload directory "%s".', $this->directory));
            // @codeCoverageIgnoreEnd
        }

        $mime      = $upload->getMimeType() ?? '';
        $extension = UploadedAssetExtension::resolve($mime, $upload->getClientExtension());
        $basename  = bin2hex(random_bytes(16)) . '.' . $extension;
        $upload->moveTo($this->directory, $basename);

        $prefix = rtrim($this->publicPrefix, '/');
        $src    = $prefix . '/' . $basename;
        $name   = $upload->getClientName() !== '' ? $upload->getClientName() : $basename;

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

    /**
     * @return list<array{src: string, type: string, name: string, width?: int, height?: int, storageKey?: string}>
     */
    public function list(int $limit = 100): array
    {
        if ($limit < 1) {
            return [];
        }
        if (!is_dir($this->directory)) {
            return [];
        }

        $files  = [];
        $handle = @opendir($this->directory);
        if ($handle === false) {
            return [];
        }
        while (($entry = readdir($handle)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $this->directory . '/' . $entry;
            if (!is_file($path)) {
                continue;
            }
            $files[] = ['name' => $entry, 'mtime' => (int) @filemtime($path), 'path' => $path];
        }
        closedir($handle);

        usort(
            $files,
            static fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime'],
        );

        $prefix = rtrim($this->publicPrefix, '/');
        $out    = [];
        foreach ($files as $file) {
            if (count($out) >= $limit) {
                break;
            }
            $basename = $file['name'];
            $src      = $prefix . '/' . $basename;
            $width    = $height = null;
            $size     = @getimagesize($file['path']);
            if (is_array($size)) {
                $width  = (int) $size[0];
                $height = (int) $size[1];
            }
            $asset = [
                'src'        => $src,
                'type'       => 'image',
                'name'       => $basename,
                'storageKey' => $basename,
            ];
            if ($width !== null) {
                $asset['width'] = $width;
            }
            if ($height !== null) {
                $asset['height'] = $height;
            }
            $out[] = $asset;
        }

        return $out;
    }

    /**
     * The checks of every upload, in the order they always ran: size, detected MIME type, client file name.
     */
    private function assertValidUpload(AssetUpload $upload): void
    {
        $size = $upload->getSize();
        if ($size !== null && $size > $this->maxBytes) {
            throw new InvalidArgumentException(sprintf('File exceeds max size of %d bytes.', $this->maxBytes));
        }

        $mime = $upload->getMimeType() ?? '';
        if ($mime === '' || !in_array($mime, $this->allowedMimeTypes, true)) {
            throw new InvalidArgumentException(sprintf('MIME type "%s" is not allowed.', $mime !== '' ? $mime : 'unknown'));
        }

        $original = trim($upload->getClientName());
        if ($original !== '' && str_contains($original, '..')) {
            throw new InvalidArgumentException('Invalid file name.');
        }
    }
}
