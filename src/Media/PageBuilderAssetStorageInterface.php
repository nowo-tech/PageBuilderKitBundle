<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Media;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stores editor-uploaded assets (local disk, S3, or host custom).
 */
interface PageBuilderAssetStorageInterface
{
    public function store(UploadedFile $file): AssetUploadResult;
}
