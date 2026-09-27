<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\DependencyInjection\Compiler;

use Nowo\PageBuilderKitBundle\Widget\WidgetTypeRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class WidgetTypePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(WidgetTypeRegistry::class)) {
            return;
        }

        $references = [];
        foreach ($container->findTaggedServiceIds('nowo_page_builder_kit.widget_type') as $id => $tags) {
            $references[] = new Reference($id);
        }

        $container->getDefinition(WidgetTypeRegistry::class)->setArgument('$types', $references);
    }
}
