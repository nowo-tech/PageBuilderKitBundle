<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\Persistence;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Service\PagePublisherInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Host controllers of the worker test: a failing bundle write (closes the manager) and a normal one.
 */
final readonly class PersistenceTestController
{
    public function __construct(
        private PagePublisherInterface $publisher,
        private ManagerRegistry $registry,
    ) {
    }

    public function create(string $key): Response
    {
        return new Response($this->publisher->createPageByKey($key, 'Title ' . $key, 'en'));
    }

    /**
     * Host use case that commits a page, then fails later in the same request (LG-PBK-L1).
     */
    public function commitThenFail(string $key): Response
    {
        $this->publisher->createPageByKey($key, 'Title ' . $key, 'en');
        $manager = $this->registry->getManagerForClass(BuilderPage::class);
        if ($manager instanceof ObjectManager) {
            $manager->flush();
        }

        throw new RuntimeException('Failure after the commit.');
    }

    /**
     * Duplicate unique uuid: the flush fails and Doctrine closes the bundle manager.
     */
    public function fail(): Response
    {
        $manager = $this->registry->getManagerForClass(BuilderPage::class);
        if (!$manager instanceof ObjectManager) {
            return new Response('no manager', 500);
        }

        $manager->persist(new BuilderPage()->setPageKey('dup-a')->setUuid('00000000-0000-4000-8000-000000000001'));
        $manager->persist(new BuilderPage()->setPageKey('dup-b')->setUuid('00000000-0000-4000-8000-000000000001'));
        $manager->flush();

        return new Response('unreachable');
    }
}
