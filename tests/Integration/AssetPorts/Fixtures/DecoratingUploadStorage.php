<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\AssetPorts\Fixtures;

use Nowo\PageBuilderKitBundle\Media\AssetUpload;
use Nowo\PageBuilderKitBundle\Media\AssetUploadResult;
use Nowo\PageBuilderKitBundle\Media\PageBuilderAssetUploadStorageInterface;

/** Decorator of the 1.15 port (e.g. an image optimiser in front of the storage). */
final readonly class DecoratingUploadStorage implements PageBuilderAssetUploadStorageInterface
{
    public function __construct(public PageBuilderAssetUploadStorageInterface $inner)
    {
    }

    public function storeUpload(AssetUpload $upload): AssetUploadResult
    {
        return $this->inner->storeUpload($upload);
    }
}
