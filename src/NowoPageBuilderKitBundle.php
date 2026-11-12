<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\AfterCommitMiddlewarePass;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\AssetStoragePortsPass;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\CsrfProtectionPass;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\GrapesBlockPackPass;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\PageBuilderEntityManagerPass;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\WidgetPackPass;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\WidgetTypePass;
use Nowo\PageBuilderKitBundle\DependencyInjection\NowoPageBuilderKitExtension;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocalesLegacyBinding;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class NowoPageBuilderKitBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new TwigPathsPass());
        $container->addCompilerPass(new WidgetTypePass());
        $container->addCompilerPass(new WidgetPackPass());
        $container->addCompilerPass(new GrapesBlockPackPass());
        $container->addCompilerPass(new CsrfProtectionPass());
        // 1.15: asset storage ports (storage=service class, the handler's port; host definitions are loaded here).
        $container->addCompilerPass(new AssetStoragePortsPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);

        // Before DoctrineBundle's MiddlewaresPass: the after-commit middleware on the bundle connection only (1.14).
        $container->addCompilerPass(new AfterCommitMiddlewarePass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);

        // Before the mapping passes: a clear error for an unknown doctrine.entity_manager (1.14).
        $container->addCompilerPass(new PageBuilderEntityManagerPass(checkOnly: true), PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);

        $entityDir = __DIR__ . '/Entity';
        if (is_dir($entityDir)) {
            $container->addCompilerPass(DoctrineOrmMappingsPass::createAttributeMappingDriver(
                ['Nowo\\PageBuilderKitBundle\\Entity'],
                [$entityDir],
                [NowoPageBuilderKitExtension::ENTITY_MANAGER_PARAMETER],
            ));
        }

        // After the mapping pass above (and DoctrineBundle's auto_mapping): keep the entities on one manager (1.14).
        $container->addCompilerPass(new PageBuilderEntityManagerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -10);
    }

    public function boot(): void
    {
        if ($this->container?->has(BuilderLocales::class) === true
            && $this->container->has(BuilderLocalesLegacyBinding::class)) {
            /** @var BuilderLocales $locales */
            $locales = $this->container->get(BuilderLocales::class);
            /** @var BuilderLocalesLegacyBinding $binding */
            $binding = $this->container->get(BuilderLocalesLegacyBinding::class);
            $binding->bind($locales);
        }
    }

    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            // @igor-ignore - Boot-time Symfony Bundle extension cache (not request state).
            $this->extension = new NowoPageBuilderKitExtension();
        }

        return $this->extension;
    }
}
