<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Media;

use InvalidArgumentException;

/**
 * Stores editor-uploaded assets (local disk, S3, or host custom) from a bundle value object (1.15, REQ-ARCH-001 rule 3).
 *
 * Framework-free twin of the deprecated {@see PageBuilderAssetStorageInterface}: the file is an {@see AssetUpload}
 * (stream, move, client name, size, MIME type detected on the server), not an HttpFoundation `UploadedFile`. The bundle
 * calls it only for a valid upload (`UPLOAD_ERR_OK`, a real HTTP upload); size, MIME and file name checks stay the
 * storage's job, exactly as with {@see PageBuilderAssetStorageInterface::store()} (the built-in local and S3 storages
 * run the same checks on both ports).
 *
 * Alias or decorate this interface (or point `grapesjs.assets_upload.service` at a service implementing it, with
 * `storage: service`): the bundle's upload handler then uses your service.
 */
interface PageBuilderAssetUploadStorageInterface
{
    /**
     * @throws InvalidArgumentException when the upload is rejected (size, MIME type, file name)
     */
    public function storeUpload(AssetUpload $upload): AssetUploadResult;
}
