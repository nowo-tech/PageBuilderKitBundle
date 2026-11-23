<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Fixtures\RemovedPorts\ImplementsContext;

use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Service\GrapesTwigContextProviderInterface;

/**
 * A 1.x host Twig context provider on the entity-based port removed in 2.0 (W1-1), autodiscovered: it cannot be loaded,
 * and dropped without a word the pages would render without the host's data. Excluded from PHPStan and Rector.
 */
final class LegacyContextProvider implements GrapesTwigContextProviderInterface
{
    public function getContext(BuilderPage $page, string $locale): array
    {
        return ['products' => ['a', 'b']];
    }
}
