<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Repository;

use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageRevision;

interface BuilderPageRevisionRepositoryInterface
{
    /**
     * @return list<BuilderPageRevision>
     */
    public function findByPageNewestFirst(BuilderPage $page): array;

    public function findLatestForPage(BuilderPage $page): ?BuilderPageRevision;

    public function findOneForPage(BuilderPage $page, int $revisionId): ?BuilderPageRevision;
}
