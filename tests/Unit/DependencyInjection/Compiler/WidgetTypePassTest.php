<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\WidgetTypePass;
use Nowo\PageBuilderKitBundle\Widget\Type\HeadingWidgetType;
use Nowo\PageBuilderKitBundle\Widget\WidgetTypeRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

#[CoversClass(WidgetTypePass::class)]
final class WidgetTypePassTest extends TestCase
{
    #[Test]
    public function wiresTaggedWidgetTypesIntoRegistry(): void
    {
        $container = new ContainerBuilder();
        $container->register(WidgetTypeRegistry::class)->setArguments([[]]);
        $container->register('widget.heading', HeadingWidgetType::class)
            ->addTag('nowo_page_builder_kit.widget_type');

        (new WidgetTypePass())->process($container);

        $argument = $container->getDefinition(WidgetTypeRegistry::class)->getArgument('$types');
        self::assertCount(1, $argument);
    }

    #[Test]
    public function noOpWhenRegistryMissing(): void
    {
        $container = new ContainerBuilder();
        $container->register('widget.heading', Definition::class)->addTag('nowo_page_builder_kit.widget_type');

        (new WidgetTypePass())->process($container);

        self::assertFalse($container->hasDefinition(WidgetTypeRegistry::class));
    }
}
