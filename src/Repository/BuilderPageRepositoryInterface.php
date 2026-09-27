<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Repository;

use Nowo\PageBuilderKitBundle\Entity\BuilderPage;

interface BuilderPageRepositoryInterface
{
    public function findOneByPageKey(string $pageKey): ?BuilderPage;

    /**
     * @return list<BuilderPage>
     */
    public function findAllOrdered(): array;
}
