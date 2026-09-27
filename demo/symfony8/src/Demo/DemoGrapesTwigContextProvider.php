<?php

declare(strict_types=1);

namespace App\Demo;

use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Service\GrapesTwigContextProviderInterface;

/**
 * Demo host variables available inside GrapesJS Twig HTML (scalars + product lists).
 */
final class DemoGrapesTwigContextProvider implements GrapesTwigContextProviderInterface
{
    public function getContext(BuilderPage $page, string $locale): array
    {
        $es = $locale === 'es';

        return [
            'demoApp'  => 'PageBuilderKit demo',
            'demoSeed' => DemoUseCases::SEED_VERSION,
            'products' => [
                [
                    'name'  => $es ? 'Plan Starter' : 'Starter plan',
                    'price' => $es ? '19 €' : '$19',
                    'url'   => '/pricing',
                ],
                [
                    'name'  => $es ? 'Plan Pro' : 'Pro plan',
                    'price' => $es ? '49 €' : '$49',
                    'url'   => '/pricing',
                ],
                [
                    'name'  => $es ? 'Plan Enterprise' : 'Enterprise plan',
                    'price' => $es ? 'A medida' : 'Custom',
                    'url'   => '/contact',
                ],
            ],
            'highlights' => $es
                ? ['Secciones por locale', 'Twig sandbox', 'Upload S3']
                : ['Locale sections', 'Sandboxed Twig', 'S3 uploads'],
        ];
    }
}
