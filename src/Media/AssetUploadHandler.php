<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Media;

use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\AssetStoragePortsPass;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function count;
use function is_array;
use function trigger_deprecation;

/**
 * Orchestrates multi-file uploads for the GrapesJS Asset Manager.
 *
 * 1.15: the storage the container resolved ({@see AssetStoragePortsPass})
 * gets an {@see AssetUpload} VO through {@see PageBuilderAssetUploadStorageInterface}; a host storage that implements
 * only the deprecated {@see PageBuilderAssetStorageInterface} still gets the `UploadedFile`, as before.
 */
final readonly class AssetUploadHandler
{
    public function __construct(
        private PageBuilderAssetUploadStorageInterface|PageBuilderAssetStorageInterface $storage,
        private bool $enabled = false,
        private LoggerInterface $logger = new NullLogger(),
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
            $assets[] = $this->store($file)->toGrapesAsset();
        }

        if ($assets === []) {
            $this->logger->warning('Page builder asset upload rejected: no valid file.', ['bundle' => 'NowoPageBuilderKitBundle', 'action' => 'asset_upload']);

            throw new InvalidArgumentException('No valid files were uploaded.');
        }

        // File names and URLs are not logged (they can carry personal data).
        $this->logger->info('Page builder assets uploaded.', ['bundle' => 'NowoPageBuilderKitBundle', 'action' => 'asset_upload', 'count' => count($assets)]);

        return $assets;
    }

    private function store(UploadedFile $file): AssetUploadResult
    {
        if (!$this->storage instanceof PageBuilderAssetUploadStorageInterface) {
            trigger_deprecation('nowo-tech/page-builder-kit-bundle', '1.15', 'Storing page builder assets through "%s" is deprecated, implement "%s" instead; removed in 2.0.', PageBuilderAssetStorageInterface::class, PageBuilderAssetUploadStorageInterface::class);

            return $this->storage->store($file);
        }

        // The check every built-in storage ran first on the UploadedFile (same message).
        if (!$file->isValid()) {
            throw new InvalidArgumentException('Invalid uploaded file.');
        }

        return $this->storage->storeUpload(AssetUploadData::fromUploadedFile($file));
    }

    public function supportsLibrary(): bool
    {
        return $this->enabled && $this->storage instanceof PageBuilderAssetLibraryInterface;
    }

    /**
     * @return list<array{src: string, type: string, name: string, width?: int, height?: int, storageKey?: string}>
     */
    public function listLibrary(int $limit = 100): array
    {
        if (!$this->enabled) {
            return [];
        }
        if (!$this->storage instanceof PageBuilderAssetLibraryInterface) {
            return [];
        }

        return $this->storage->list($limit);
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
