<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Fixtures\RemovedPorts\TaggedContext;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A loadable host service tagged as a Twig context provider that does not implement the 2.0 port
 * (`GrapesTwigPageContextProviderInterface`): the render service could not call it, so the build fails.
 */
#[AutoconfigureTag('nowo_page_builder_kit.grapes_twig_context')]
final class UntypedContextProvider
{
    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return [];
    }
}
