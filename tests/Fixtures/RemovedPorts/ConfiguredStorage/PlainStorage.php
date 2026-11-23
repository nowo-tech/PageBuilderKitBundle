<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Fixtures\RemovedPorts\ConfiguredStorage;

/**
 * A loadable host service set as `grapesjs.assets_upload.service` (storage: service) that does not implement
 * `PageBuilderAssetUploadStorageInterface`: the build fails instead of a TypeError at the first upload.
 */
final class PlainStorage
{
}
