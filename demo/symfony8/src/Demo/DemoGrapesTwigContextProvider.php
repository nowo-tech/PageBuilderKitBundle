<?php

declare(strict_types=1);

namespace App\Demo;

use Nowo\PageBuilderKitBundle\Service\GrapesTwigPageContextProviderInterface;
use Nowo\PageBuilderKitBundle\Service\PageContext;

/**
 * Demo host variables available inside GrapesJS Twig HTML (scalars + product lists). Entity-free provider
 * (GrapesTwigPageContextProviderInterface): the entity-based interface is removed in 2.0.
 */
final class DemoGrapesTwigContextProvider implements GrapesTwigPageContextProviderInterface
{
    public function getPageContext(PageContext $page, string $locale): array
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
