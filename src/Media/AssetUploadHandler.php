<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Media;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function count;
use function is_array;

/**
 * Orchestrates multi-file uploads for the GrapesJS Asset Manager.
 */
final class AssetUploadHandler
{
    public function __construct(
        private readonly PageBuilderAssetStorageInterface $storage,
        private readonly bool $enabled = false,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param list<UploadedFile|null> $files
     *
     * @return list<array{src: string, type: string, name: string, width?: int, height?: int}>
     */
    public function handle(array $files): array
    {
        if (!$this->enabled) {
            throw new RuntimeException('Asset upload is disabled.');
        }

        $assets = [];
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }
            $assets[] = $this->storage->store($file)->toGrapesAsset();
        }

        if ($assets === []) {
            throw new InvalidArgumentException('No valid files were uploaded.');
        }

        return $assets;
    }

    /**
     * Normalize GrapesJS / browser multipart payloads into a flat file list.
     *
     * @return list<UploadedFile|null>
     */
    public static function normalizeFiles(mixed $files): array
    {
        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (!is_array($files) || count($files) === 0) {
            return [];
        }

        $out = [];
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $out[] = $file;
            }
        }

        return $out;
    }
}
