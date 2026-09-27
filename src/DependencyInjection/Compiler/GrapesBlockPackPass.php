<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\DependencyInjection\Compiler;

use Nowo\PageBuilderKitBundle\Grapes\GrapesBlockPackRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Injects tagged Grapes block packs into {@see GrapesBlockPackRegistry}.
 */
final class GrapesBlockPackPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(GrapesBlockPackRegistry::class)) {
            return;
        }

        $references = [];
        foreach ($container->findTaggedServiceIds('nowo_page_builder_kit.grapes_block_pack') as $id => $tags) {
            unset($tags);
            $references[] = new Reference($id);
        }

        $container->getDefinition(GrapesBlockPackRegistry::class)->setArgument('$packs', $references);
    }
}
