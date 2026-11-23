<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\DependencyInjection\Compiler;

use ArrayObject;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\PageBuilderRemovedPortsPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The cases of {@see PageBuilderRemovedPortsPass} a kernel cannot easily build (the W1-1 kernel test covers the rest):
 * a tagged context provider whose class is only known at run time is left to `PageRenderProvider`.
 */
#[CoversClass(PageBuilderRemovedPortsPass::class)]
final class PageBuilderRemovedPortsPassTest extends TestCase
{
    #[Test]
    public function aProviderKnownOnlyAtRunTimeIsLeftToTheRenderService(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.factory_provider', ArrayObject::class)->setFactory([ArrayObject::class, 'create'])->addTag(PageBuilderRemovedPortsPass::CONTEXT_PROVIDER_TAG);
        $container->register('app.synthetic_provider')->setSynthetic(true)->addTag(PageBuilderRemovedPortsPass::CONTEXT_PROVIDER_TAG);
        $container->setParameter('app.provider_class', ['not', 'a', 'class']);
        $container->register('app.parameter_provider', '%app.provider_class%')->addTag(PageBuilderRemovedPortsPass::CONTEXT_PROVIDER_TAG);

        new PageBuilderRemovedPortsPass()->process($container);

        self::assertTrue($container->hasDefinition('app.factory_provider'));
    }

    #[Test]
    public function namesMatchesTheExactClassNameOnly(): void
    {
        $removed = 'Nowo\PageBuilderKitBundle\Media\PageBuilderAssetStorageInterface';

        self::assertTrue(PageBuilderRemovedPortsPass::names('Class "' . $removed . '" not found', $removed));
        self::assertFalse(PageBuilderRemovedPortsPass::names('Class "' . $removed . 'Factory" not found', $removed));
    }
}
