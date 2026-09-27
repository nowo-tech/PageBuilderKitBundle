<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\DependencyInjection\Compiler;

use Nowo\PageBuilderKitBundle\Widget\WidgetPackRegistry;
use Nowo\PageBuilderKitBundle\Widget\WidgetTypeRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use function is_array;
use function is_string;

/**
 * Injects tagged widget packs into {@see WidgetPackRegistry}.
 * Widget types themselves must also carry `nowo_page_builder_kit.widget_type`
 * (or be returned from the pack and registered by the host).
 */
final class WidgetPackPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(WidgetPackRegistry::class)) {
            return;
        }

        $references = [];
        foreach ($container->findTaggedServiceIds('nowo_page_builder_kit.widget_pack') as $id => $tags) {
            unset($tags);
            $references[] = new Reference($id);
        }

        $container->getDefinition(WidgetPackRegistry::class)->setArgument('$packs', $references);

        // Ensure pack-owned types that are only referenced via the pack still
        // land in the type registry when hosts tag them on the pack service.
        if (!$container->hasDefinition(WidgetTypeRegistry::class)) {
            return;
        }

        $registry = $container->getDefinition(WidgetTypeRegistry::class);
        /** @var list<mixed|Reference> $types */
        $types = $registry->getArgument('$types');
        if (!is_array($types)) {
            $types = [];
        }

        foreach ($container->findTaggedServiceIds('nowo_page_builder_kit.widget_pack') as $id => $tags) {
            foreach ($tags as $tag) {
                $typeIds = $tag['types'] ?? [];
                if (!is_array($typeIds)) {
                    continue;
                }
                foreach ($typeIds as $typeId) {
                    if (is_string($typeId) && $typeId !== '' && $container->hasDefinition($typeId)) {
                        $types[] = new Reference($typeId);
                    }
                }
            }
            unset($id);
        }

        $unique = [];
        $refs   = [];
        foreach ($types as $ref) {
            if (!$ref instanceof Reference) {
                continue;
            }
            $refId = (string) $ref;
            if (isset($unique[$refId])) {
                continue;
            }
            $unique[$refId] = true;
            $refs[]         = $ref;
        }

        $registry->setArgument('$types', $refs);
    }
}
