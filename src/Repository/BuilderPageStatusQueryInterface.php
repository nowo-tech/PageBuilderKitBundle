<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Repository;

use Nowo\PageBuilderKitBundle\Enum\PageStatus;

/**
 * Reads the publication status of a page without hydrating its document (large JSON).
 */
interface BuilderPageStatusQueryInterface
{
    /**
     * @return PageStatus|null null when no page exists for the key
     */
    public function findStatusByPageKey(string $pageKey): ?PageStatus;
}
