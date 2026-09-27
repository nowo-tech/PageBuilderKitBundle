<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\WidgetPackPass;
use Nowo\PageBuilderKitBundle\Widget\Type\HeadingWidgetType;
use Nowo\PageBuilderKitBundle\Widget\WidgetPackRegistry;
use Nowo\PageBuilderKitBundle\Widget\WidgetTypeRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(WidgetPackPass::class)]
final class WidgetPackPassTest extends TestCase
{
    #[Test]
    public function wiresTaggedPacksAndOptionalTypes(): void
    {
        $container = new ContainerBuilder();
        $container->register(WidgetPackRegistry::class, WidgetPackRegistry::class)->setArgument('$packs', []);
        $container->register(WidgetTypeRegistry::class, WidgetTypeRegistry::class)->setArgument('$types', []);
        $container->register('widget.heading', HeadingWidgetType::class);
        $container->register('pack.demo', stdClass::class)
            ->addTag('nowo_page_builder_kit.widget_pack', ['types' => ['widget.heading']]);

        (new WidgetPackPass())->process($container);

        $packs = $container->getDefinition(WidgetPackRegistry::class)->getArgument('$packs');
        self::assertCount(1, $packs);

        $types = $container->getDefinition(WidgetTypeRegistry::class)->getArgument('$types');
        self::assertCount(1, $types);
    }

    #[Test]
    public function noOpWhenPackRegistryMissing(): void
    {
        $container = new ContainerBuilder();
        (new WidgetPackPass())->process($container);

        self::assertFalse($container->hasDefinition(WidgetPackRegistry::class));
    }
}
