<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\DependencyInjection;

use Nowo\PageBuilderKitBundle\DependencyInjection\NowoPageBuilderKitExtension;
use Nowo\PageBuilderKitBundle\Service\GrapesDocumentSanitizer;
use Nowo\PageBuilderKitBundle\Service\GrapesJsFrontendConfig;
use Nowo\PageBuilderKitBundle\Service\GrapesTwigRenderer;
use Nowo\PageBuilderKitBundle\Service\PageRenderProvider;
use Nowo\PageBuilderKitBundle\Service\PageSeoBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class NowoPageBuilderKitExtensionElseBranchTest extends TestCase
{
    #[Test]
    public function loadRegistersServicesWhenDefinitionsWereRemoved(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);
        $config = [
            'security' => ['allow_unauthenticated' => true],
            'grapesjs' => ['assets_upload' => ['enabled' => false]],
        ];

        $extension = new NowoPageBuilderKitExtension();
        $extension->load([$config], $container);

        foreach ([PageSeoBuilder::class, GrapesDocumentSanitizer::class, GrapesJsFrontendConfig::class, GrapesTwigRenderer::class, PageRenderProvider::class] as $id) {
            $container->removeDefinition($id);
        }

        $extension->load([$config], $container);

        self::assertTrue($container->hasDefinition(PageSeoBuilder::class));
        self::assertTrue($container->hasDefinition(GrapesDocumentSanitizer::class));
        self::assertTrue($container->hasDefinition(GrapesJsFrontendConfig::class));
        self::assertTrue($container->hasDefinition(GrapesTwigRenderer::class));
        self::assertTrue($container->hasDefinition(PageRenderProvider::class));
    }
}
