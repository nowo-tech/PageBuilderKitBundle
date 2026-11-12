<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\AssetPorts\Fixtures;

use Nowo\PageBuilderKitBundle\Media\AssetUpload;
use Nowo\PageBuilderKitBundle\Media\AssetUploadResult;
use Nowo\PageBuilderKitBundle\Media\PageBuilderAssetUploadStorageInterface;

/** A 1.15 host storage: the new port only. */
final class HostUploadStorage implements PageBuilderAssetUploadStorageInterface
{
    public function storeUpload(AssetUpload $upload): AssetUploadResult
    {
        return new AssetUploadResult('/host/' . $upload->getClientName(), $upload->getClientName(), mimeType: $upload->getMimeType());
    }
}
