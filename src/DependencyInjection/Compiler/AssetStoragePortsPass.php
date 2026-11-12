<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\DependencyInjection\Compiler;

use Nowo\PageBuilderKitBundle\Media\AssetUploadHandler;
use Nowo\PageBuilderKitBundle\Media\PageBuilderAssetStorageInterface;
use Nowo\PageBuilderKitBundle\Media\PageBuilderAssetUploadStorageInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use function class_exists;
use function is_a;
use function is_string;

/**
 * Wires the asset storage ports (1.15, REQ-ARCH-001 rule 3).
 *
 * 1. `storage: service`: the 1.15 {@see PageBuilderAssetUploadStorageInterface} alias points at the service when its
 *    class implements that port; the deprecated {@see PageBuilderAssetStorageInterface} alias (marked deprecated) only
 *    when it implements that one. A class that cannot be resolved keeps the pre-1.15 wiring (deprecated port). Aliases
 *    the host defined itself are never replaced.
 * 2. The upload handler gets the port the host provides: the 1.15 port when the host aliased or decorated it (or the
 *    configured storage implements it), else the deprecated port, so a storage that implements only the old interface
 *    keeps working unchanged.
 *
 * Runs before optimization: host definitions are loaded, decorators are not applied yet (they follow the alias).
 */
final class AssetStoragePortsPass implements CompilerPassInterface
{
    public const string PACKAGE = 'nowo-tech/page-builder-kit-bundle';

    public const string DEPRECATION = 'The "%alias_id%" service alias is deprecated, use "' . PageBuilderAssetUploadStorageInterface::class . '" instead.';

    public const string STORAGE_ID = 'nowo_page_builder_kit.asset_storage';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(self::STORAGE_ID)) {
            return;
        }

        $this->aliasCustomStorage($container);

        $storage     = $this->resolveAlias($container, self::STORAGE_ID);
        $newOverride = $this->isOverridden($container, PageBuilderAssetUploadStorageInterface::class, $storage);
        $oldOverride = $this->isOverridden($container, PageBuilderAssetStorageInterface::class, $storage);
        $useNew      = $newOverride || (!$oldOverride && $container->has(PageBuilderAssetUploadStorageInterface::class));

        if ($container->hasDefinition(AssetUploadHandler::class)) {
            $container->getDefinition(AssetUploadHandler::class)->setArgument(
                '$storage',
                new Reference($useNew ? PageBuilderAssetUploadStorageInterface::class : PageBuilderAssetStorageInterface::class),
            );
        }
    }

    private function aliasCustomStorage(ContainerBuilder $container): void
    {
        $hasNew = $container->has(PageBuilderAssetUploadStorageInterface::class);
        $hasOld = $container->has(PageBuilderAssetStorageInterface::class);
        if ($hasNew && $hasOld) {
            return;
        }

        $class = $this->classOf($container, self::STORAGE_ID);
        if ($class === null) {
            // Unknown class (factory, synthetic, missing service): the pre-1.15 wiring, without a deprecation.
            if (!$hasOld) {
                $container->setAlias(PageBuilderAssetStorageInterface::class, self::STORAGE_ID)->setPublic(false);
            }

            return;
        }

        $implementsNew = is_a($class, PageBuilderAssetUploadStorageInterface::class, true);
        if ($implementsNew && !$hasNew) {
            $container->setAlias(PageBuilderAssetUploadStorageInterface::class, self::STORAGE_ID)->setPublic(false);
        }

        // A class that implements neither port keeps the pre-1.15 alias (and its error at injection time).
        if (!$hasOld && (is_a($class, PageBuilderAssetStorageInterface::class, true) || !$implementsNew)) {
            $container->setAlias(PageBuilderAssetStorageInterface::class, self::STORAGE_ID)->setPublic(false)
                ->setDeprecated(self::PACKAGE, '1.15', self::DEPRECATION);
        }
    }

    /**
     * True when the port is decorated, or its alias resolves to another service than the configured storage.
     */
    private function isOverridden(ContainerBuilder $container, string $port, string $storage): bool
    {
        foreach ($container->getDefinitions() as $definition) {
            if (($definition->getDecoratedService()[0] ?? null) === $port) {
                return true;
            }
        }

        if (!$container->has($port)) {
            return false;
        }

        return $this->resolveAlias($container, $port) !== $storage;
    }

    private function resolveAlias(ContainerBuilder $container, string $id): string
    {
        $seen = [];
        while ($container->hasAlias($id) && !isset($seen[$id])) {
            $seen[$id] = true;
            $id        = (string) $container->getAlias($id);
        }

        return $id;
    }

    /**
     * @return class-string|null
     */
    private function classOf(ContainerBuilder $container, string $service): ?string
    {
        $id = $this->resolveAlias($container, $service);
        if (!$container->hasDefinition($id)) {
            return null;
        }

        $definition = $container->getDefinition($id);
        $class      = $container->getParameterBag()->resolveValue($definition->getClass() ?? $id);

        if (!is_string($class) || $definition->getFactory() !== null || !class_exists($class)) {
            return null;
        }

        return $class;
    }
}
