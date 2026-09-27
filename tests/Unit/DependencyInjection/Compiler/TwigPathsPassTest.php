<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Twig\Loader\FilesystemLoader;

use function dirname;

#[CoversClass(TwigPathsPass::class)]
final class TwigPathsPassTest extends TestCase
{
    #[Test]
    public function addsBundleViewsPathToNativeLoader(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $loader = new Definition(FilesystemLoader::class);
        $container->setDefinition('twig.loader.native_filesystem', $loader);

        (new TwigPathsPass())->process($container);

        $calls = $loader->getMethodCalls();
        self::assertNotEmpty($calls);
        self::assertSame('addPath', $calls[0][0]);
        self::assertSame('NowoPageBuilderKitBundle', $calls[0][1][1]);
    }

    #[Test]
    public function resolvesTwigLoaderAliasChain(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $target = new Definition(FilesystemLoader::class);
        $container->setDefinition('twig.loader.native_filesystem', $target);
        $container->setAlias('twig.loader.native', 'twig.loader.native_filesystem');

        (new TwigPathsPass())->process($container);

        self::assertNotEmpty($target->getMethodCalls());
    }

    #[Test]
    public function prependsProjectOverrideDirectoryWhenPresent(): void
    {
        $projectDir   = sys_get_temp_dir() . '/pbk_twig_' . uniqid('', true);
        $overridePath = $projectDir . '/templates/bundles/NowoPageBuilderKitBundle';
        mkdir($overridePath, 0755, true);

        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $projectDir);
        $loader = new Definition(FilesystemLoader::class);
        $container->setDefinition('twig.loader.filesystem', $loader);

        try {
            (new TwigPathsPass())->process($container);

            $calls = $loader->getMethodCalls();
            self::assertSame('prependPath', $calls[0][0]);
            self::assertSame($overridePath, $calls[0][1][0]);
        } finally {
            rmdir($overridePath);
            rmdir(dirname($overridePath));
            rmdir(dirname($overridePath, 2));
            rmdir(dirname($overridePath, 3));
            rmdir($projectDir);
        }
    }

    #[Test]
    public function noOpWithoutTwigLoader(): void
    {
        $container = new ContainerBuilder();

        (new TwigPathsPass())->process($container);

        self::assertFalse($container->hasDefinition('twig.loader.native_filesystem'));
    }

    #[Test]
    public function usesNativeLoaderDefinitionWhenPresent(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $loader = new Definition(FilesystemLoader::class);
        $container->setDefinition('twig.loader.native', $loader);

        (new TwigPathsPass())->process($container);

        self::assertNotEmpty($loader->getMethodCalls());
    }

    #[Test]
    public function resolvesNestedAliasChainToDefinition(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $target = new Definition(FilesystemLoader::class);
        $container->setDefinition('twig.loader.native_filesystem', $target);
        $container->setAlias('twig.loader.native', 'twig.loader.native_filesystem');
        $container->setAlias('twig.loader.alias', 'twig.loader.native');

        (new TwigPathsPass())->process($container);

        self::assertNotEmpty($target->getMethodCalls());
    }
}
