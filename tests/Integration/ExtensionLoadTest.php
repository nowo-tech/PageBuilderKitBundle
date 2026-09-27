<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration;

use Nowo\PageBuilderKitBundle\DependencyInjection\NowoPageBuilderKitExtension;
use Nowo\PageBuilderKitBundle\Service\DocumentNormalizer;
use Nowo\PageBuilderKitBundle\Service\DocumentService;
use Nowo\PageBuilderKitBundle\Twig\PageBuilderKitExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ExtensionLoadTest extends TestCase
{
    #[Test]
    public function extensionProcessesConfigAndRegistersCoreServices(): void
    {
        $rawConfig = [
            'security' => [
                'allow_unauthenticated' => true,
            ],
        ];

        $processor = new Processor();
        $processed = $processor->processConfiguration(
            (new NowoPageBuilderKitExtension())->getConfiguration([], new ContainerBuilder()),
            [$rawConfig],
        );

        self::assertSame('es', $processed['default_locale']);

        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);

        $extension = new NowoPageBuilderKitExtension();
        $extension->load([$rawConfig], $container);

        self::assertTrue($container->hasDefinition(DocumentService::class));
        self::assertTrue($container->hasDefinition(DocumentNormalizer::class));
        self::assertTrue($container->hasDefinition(PageBuilderKitExtension::class));
    }
}
