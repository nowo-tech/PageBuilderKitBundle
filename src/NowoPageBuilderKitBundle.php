<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\GrapesBlockPackPass;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\WidgetPackPass;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\WidgetTypePass;
use Nowo\PageBuilderKitBundle\DependencyInjection\NowoPageBuilderKitExtension;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocalesLegacyBinding;
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

        $entityDir = __DIR__ . '/Entity';
        if (is_dir($entityDir)) {
            $container->addCompilerPass(DoctrineOrmMappingsPass::createAttributeMappingDriver(
                ['Nowo\\PageBuilderKitBundle\\Entity'],
                [$entityDir],
            ));
        }
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
