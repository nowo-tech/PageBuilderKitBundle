<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\AssetPorts\Fixtures;

use Nowo\PageBuilderKitBundle\Media\AssetUpload;
use Nowo\PageBuilderKitBundle\Media\AssetUploadResult;
use Nowo\PageBuilderKitBundle\Media\PageBuilderAssetStorageInterface;
use Nowo\PageBuilderKitBundle\Media\PageBuilderAssetUploadStorageInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** A host in the middle of the migration: both ports. */
final class DualHostStorage implements PageBuilderAssetUploadStorageInterface, PageBuilderAssetStorageInterface
{
    public function storeUpload(AssetUpload $upload): AssetUploadResult
    {
        return new AssetUploadResult('/dual-new/' . $upload->getClientName(), $upload->getClientName());
    }

    public function store(UploadedFile $file): AssetUploadResult
    {
        return new AssetUploadResult('/dual-old/' . $file->getClientOriginalName(), $file->getClientOriginalName());
    }
}
