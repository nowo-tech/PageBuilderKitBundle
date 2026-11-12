<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use Nowo\PageBuilderKitBundle\Entity\BuilderPage;

/**
 * Hosts can tag services as nowo_page_builder_kit.grapes_twig_context
 * to inject extra Twig variables into GrapesJS HTML rendering.
 *
 * @deprecated since 1.15, implement {@see GrapesTwigPageContextProviderInterface} instead (a {@see PageContext} VO, no
 *             entity); removed in 2.0. Until then the bundle still calls services that implement only this interface.
 */
interface GrapesTwigContextProviderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getContext(BuilderPage $page, string $locale): array;
}
