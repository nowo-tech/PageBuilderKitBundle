<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Media;

/**
 * Optional capability: browse previously stored assets (media library).
 *
 * Host custom storages may omit this; the admin UI degrades to URL + upload only.
 */
interface PageBuilderAssetLibraryInterface
{
    /**
     * @return list<array{src: string, type: string, name: string, width?: int, height?: int, storageKey?: string}>
     */
    public function list(int $limit = 100): array;
}
