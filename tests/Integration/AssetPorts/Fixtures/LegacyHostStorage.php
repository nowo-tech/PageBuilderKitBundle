<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\AssetPorts\Fixtures;

use Nowo\PageBuilderKitBundle\Media\AssetUploadResult;
use Nowo\PageBuilderKitBundle\Media\PageBuilderAssetStorageInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** A pre-1.15 host storage: the deprecated port only. */
final class LegacyHostStorage implements PageBuilderAssetStorageInterface
{
    public function store(UploadedFile $file): AssetUploadResult
    {
        return new AssetUploadResult('/legacy/' . $file->getClientOriginalName(), $file->getClientOriginalName());
    }
}
