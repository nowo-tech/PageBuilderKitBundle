<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\AssetPorts\Fixtures\DddHost;

use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Media\AssetUpload;
use Nowo\PageBuilderKitBundle\Media\AssetUploadResult;
use Nowo\PageBuilderKitBundle\Media\PageBuilderAssetUploadStorageInterface;

use function fclose;
use function in_array;

/**
 * The DDD host's adapter of the bundle port: maps the bundle VO to its own domain service. No HttpFoundation type.
 */
final readonly class MediaLibraryAssetStorage implements PageBuilderAssetUploadStorageInterface
{
    public function __construct(private MediaLibrary $library)
    {
    }

    public function storeUpload(AssetUpload $upload): AssetUploadResult
    {
        if (!in_array($upload->getMimeType(), ['image/png', 'image/gif'], true)) {
            throw new InvalidArgumentException('Only PNG and GIF.');
        }

        $stream = $upload->openStream();
        try {
            $id = $this->library->add(AssetUpload::lastSegment($upload->getClientName()), $upload->getMimeType(), $stream);
        } finally {
            fclose($stream);
        }

        return new AssetUploadResult('/media/' . $id, $upload->getClientName(), mimeType: $upload->getMimeType(), storageKey: $id);
    }
}
