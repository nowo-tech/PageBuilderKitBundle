<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

/**
 * Injects extra Twig variables into GrapesJS HTML rendering from the page's ids and key (1.15, REQ-ARCH-001 rule 1).
 *
 * Entity-free twin of the deprecated {@see GrapesTwigContextProviderInterface}. Services implementing it are
 * autoconfigured with the same `nowo_page_builder_kit.grapes_twig_context` tag; a service implementing both interfaces
 * gets only this method called.
 */
interface GrapesTwigPageContextProviderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getPageContext(PageContext $page, string $locale): array;
}
