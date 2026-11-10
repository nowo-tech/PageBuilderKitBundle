<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;

/**
 * Resolves the entity manager that maps the bundle entities when `doctrine.entity_manager` is not set (1.14).
 *
 * A standard host gets its default manager (unchanged behaviour); a host that maps the bundle on another manager
 * gets that one instead of the autowired default. Doctrine managers are lazy services reset in place, so the
 * returned reference stays valid in a kept-alive kernel.
 *
 * @internal
 */
final class PageBuilderEntityManagerFactory
{
    public static function create(ManagerRegistry $registry): EntityManagerInterface
    {
        $manager = $registry->getManagerForClass(BuilderPage::class);
        if (!$manager instanceof EntityManagerInterface) {
            throw new LogicException('No Doctrine ORM entity manager maps ' . BuilderPage::class . ': register the NowoPageBuilderKitBundle mapping (auto_mapping or an explicit mapping) or set nowo_page_builder_kit.doctrine.entity_manager.');
        }

        return $manager;
    }
}
