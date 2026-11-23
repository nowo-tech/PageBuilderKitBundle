<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\DependencyInjection\Compiler;

use LogicException;
use Nowo\PageBuilderKitBundle\Media\PageBuilderAssetUploadStorageInterface;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function is_string;
use function sprintf;

/**
 * Wires the asset storage port (1.15, REQ-ARCH-001 rule 3; 2.0: the only port).
 *
 * `storage: service`: the {@see PageBuilderAssetUploadStorageInterface} alias points at the configured service, which
 * must implement that port. A service whose class implements neither it (for example one that still implements only
 * the `PageBuilderAssetStorageInterface` removed in 2.0) nor can be loaded fails the build with a message naming the
 * replacement: the upload handler never falls back to another storage. An alias of the port the host defined itself
 * is never replaced. A service built by a factory (class known only at run time) is aliased as is.
 *
 * Runs before optimization: host definitions are loaded, decorators are not applied yet (they follow the alias).
 */
final class AssetStoragePortsPass implements CompilerPassInterface
{
    public const string PACKAGE = 'nowo-tech/page-builder-kit-bundle';

    public const string STORAGE_ID = 'nowo_page_builder_kit.asset_storage';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(self::STORAGE_ID) || $container->has(PageBuilderAssetUploadStorageInterface::class)) {
            return;
        }

        $id = $this->resolveAlias($container, self::STORAGE_ID);
        if ($container->hasDefinition($id)) {
            $definition = $container->getDefinition($id);
            $class      = $container->getParameterBag()->resolveValue($definition->getClass() ?? $id);
            if (is_string($class) && $definition->getFactory() === null && !$definition->isSynthetic()) {
                $reflection = $container->getReflectionClass($class, false);
                if (!$reflection instanceof ReflectionClass) {
                    throw new LogicException(sprintf('nowo_page_builder_kit.grapesjs.assets_upload.service is "%s" (%s), which cannot be loaded: the class or one of its interfaces is missing. %s', $id, $class, self::hint()));
                }
                if (!$reflection->implementsInterface(PageBuilderAssetUploadStorageInterface::class)) {
                    throw new LogicException(sprintf('nowo_page_builder_kit.grapesjs.assets_upload.service is "%s" (%s), which does not implement "%s". %s', $id, $class, PageBuilderAssetUploadStorageInterface::class, self::hint()));
                }
            }
        }

        $container->setAlias(PageBuilderAssetUploadStorageInterface::class, self::STORAGE_ID)->setPublic(false);
    }

    /**
     * What a storage implements since 2.0.
     */
    public static function hint(): string
    {
        return sprintf('Since 2.0 the asset storage implements "%s" (storeUpload(AssetUpload)); "%s" (deprecated since 1.15) was removed. See UPGRADING.md "To 1.15.0" and "To 2.0.0".', PageBuilderAssetUploadStorageInterface::class, 'Nowo\\PageBuilderKitBundle\\Media\\PageBuilderAssetStorageInterface');
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
}
