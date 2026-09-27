<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use Nowo\PageBuilderKitBundle\Entity\BuilderPage;

/**
 * Hosts can tag services as nowo_page_builder_kit.grapes_twig_context
 * to inject extra Twig variables into GrapesJS HTML rendering.
 */
interface GrapesTwigContextProviderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getContext(BuilderPage $page, string $locale): array;
}
