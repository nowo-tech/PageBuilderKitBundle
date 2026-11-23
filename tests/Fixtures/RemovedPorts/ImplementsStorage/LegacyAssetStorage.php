<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Fixtures\RemovedPorts\ImplementsStorage;

use Nowo\PageBuilderKitBundle\Media\AssetUploadResult;
use Nowo\PageBuilderKitBundle\Media\PageBuilderAssetStorageInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * A 1.x host storage on the asset storage port removed in 2.0 (W1-1), autodiscovered: it cannot be loaded, and
 * dropped without a word the host's uploads would silently go elsewhere. Excluded from PHPStan and Rector.
 */
final class LegacyAssetStorage implements PageBuilderAssetStorageInterface
{
    public function store(UploadedFile $file): AssetUploadResult
    {
        return new AssetUploadResult('/legacy/' . $file->getClientOriginalName(), $file->getClientOriginalName());
    }
}
