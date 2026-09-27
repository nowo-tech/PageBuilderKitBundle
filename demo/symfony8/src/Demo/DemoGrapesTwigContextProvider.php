<?php

declare(strict_types=1);

namespace App\Demo;

use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Service\GrapesTwigContextProviderInterface;

/**
 * Demo host variables available inside GrapesJS Twig HTML.
 */
final class DemoGrapesTwigContextProvider implements GrapesTwigContextProviderInterface
{
    public function getContext(BuilderPage $page, string $locale): array
    {
        return [
            'demoApp' => 'PageBuilderKit demo',
            'demoSeed' => DemoUseCases::SEED_VERSION,
        ];
    }
}
