<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\GrapesBlockPackPass;
use Nowo\PageBuilderKitBundle\Grapes\GrapesBlockPackRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(GrapesBlockPackPass::class)]
final class GrapesBlockPackPassTest extends TestCase
{
    #[Test]
    public function wiresTaggedPacksIntoRegistry(): void
    {
        $container = new ContainerBuilder();
        $container->register(GrapesBlockPackRegistry::class, GrapesBlockPackRegistry::class)
            ->setArgument('$packs', []);
        $container->register('pack.demo', stdClass::class)
            ->addTag('nowo_page_builder_kit.grapes_block_pack');

        (new GrapesBlockPackPass())->process($container);

        $packs = $container->getDefinition(GrapesBlockPackRegistry::class)->getArgument('$packs');
        self::assertCount(1, $packs);
    }

    #[Test]
    public function noOpWhenRegistryMissing(): void
    {
        $container = new ContainerBuilder();
        (new GrapesBlockPackPass())->process($container);

        self::assertFalse($container->hasDefinition(GrapesBlockPackRegistry::class));
    }
}
