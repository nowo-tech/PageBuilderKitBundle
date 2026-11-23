<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

/**
 * Injects extra Twig variables into GrapesJS HTML rendering from the page's ids and key (1.15, REQ-ARCH-001 rule 1).
 *
 * Services implementing it are autoconfigured with the `nowo_page_builder_kit.grapes_twig_context` tag. Since 2.0 it is
 * the only Twig context provider port: the entity-based `GrapesTwigContextProviderInterface` (deprecated since 1.15) was
 * removed, and a tagged service that does not implement this interface fails the container build.
 */
interface GrapesTwigPageContextProviderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getPageContext(PageContext $page, string $locale): array;
}
