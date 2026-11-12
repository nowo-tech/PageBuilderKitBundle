<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Media;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stores editor-uploaded assets (local disk, S3, or host custom).
 *
 * @deprecated since 1.15, implement {@see PageBuilderAssetUploadStorageInterface} instead (framework-free: an
 *             {@see AssetUpload} VO, no `UploadedFile`); removed in 2.0. Until then the bundle still calls a storage that
 *             implements only this interface.
 */
interface PageBuilderAssetStorageInterface
{
    public function store(UploadedFile $file): AssetUploadResult;
}
