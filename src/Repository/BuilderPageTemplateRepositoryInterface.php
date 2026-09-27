<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Repository;

use Nowo\PageBuilderKitBundle\Entity\BuilderPageTemplate;

interface BuilderPageTemplateRepositoryInterface
{
    public function findOneByTemplateKey(string $templateKey): ?BuilderPageTemplate;

    /**
     * @return list<BuilderPageTemplate>
     */
    public function findAllOrdered(): array;
}
