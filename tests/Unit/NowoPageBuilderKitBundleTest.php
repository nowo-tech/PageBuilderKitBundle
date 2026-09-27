<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit;

use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\WidgetTypePass;
use Nowo\PageBuilderKitBundle\DependencyInjection\NowoPageBuilderKitExtension;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocalesLegacyBinding;
use Nowo\PageBuilderKitBundle\NowoPageBuilderKitBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(NowoPageBuilderKitBundle::class)]
final class NowoPageBuilderKitBundleTest extends TestCase
{
    #[Test]
    public function getContainerExtensionReturnsBundleExtension(): void
    {
        $bundle = new NowoPageBuilderKitBundle();

        $extension = $bundle->getContainerExtension();

        self::assertInstanceOf(NowoPageBuilderKitExtension::class, $extension);
        self::assertSame($extension, $bundle->getContainerExtension());
    }

    #[Test]
    public function buildRegistersCompilerPasses(): void
    {
        $container = new ContainerBuilder();
        (new NowoPageBuilderKitBundle())->build($container);

        $passClasses = array_map(
            static fn (CompilerPassInterface $pass): string => $pass::class,
            $container->getCompilerPassConfig()->getBeforeOptimizationPasses(),
        );
        self::assertContains(TwigPathsPass::class, $passClasses);
        self::assertContains(WidgetTypePass::class, $passClasses);
    }

    #[Test]
    public function bootBindsBuilderLocalesWhenContainerProvidesService(): void
    {
        $container = new ContainerBuilder();
        $locales   = new BuilderLocales('es', ['es']);
        $binding   = new BuilderLocalesLegacyBinding();
        $container->register(BuilderLocales::class)->setSynthetic(true);
        $container->register(BuilderLocalesLegacyBinding::class)->setSynthetic(true);
        $container->compile();
        $container->set(BuilderLocales::class, $locales);
        $container->set(BuilderLocalesLegacyBinding::class, $binding);

        $bundle = new NowoPageBuilderKitBundle();
        $bundle->setContainer($container);
        $bundle->boot();

        self::assertSame($locales, $binding->getBoundInstance());
    }
}
