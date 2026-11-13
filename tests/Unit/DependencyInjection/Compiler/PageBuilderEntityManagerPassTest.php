<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\PageBuilderEntityManagerPass;
use Nowo\PageBuilderKitBundle\DependencyInjection\NowoPageBuilderKitExtension;
use Nowo\PageBuilderKitBundle\Persistence\ExcludedEntityNamespaceDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PageBuilderEntityManagerPass::class)]
final class PageBuilderEntityManagerPassTest extends TestCase
{
    #[Test]
    public function doesNothingWithoutADedicatedManager(): void
    {
        $container = new ContainerBuilder();
        new PageBuilderEntityManagerPass()->process($container);
        $container->setParameter(NowoPageBuilderKitExtension::ENTITY_MANAGER_PARAMETER, null);
        new PageBuilderEntityManagerPass()->process($container);
        self::assertFalse($container->hasParameter('doctrine.entity_managers'));
    }

    #[Test]
    public function removesTheBundleMappingFromEveryOtherManager(): void
    {
        $container = $this->container(['default' => 'x', 'page_builder' => 'y', 'reporting' => 'z']);
        $container->setDefinition('doctrine.orm.default_metadata_driver', $this->chain(true, true));
        $container->setDefinition('doctrine.orm.page_builder_metadata_driver', $this->chain(true, false));

        new PageBuilderEntityManagerPass()->process($container);

        self::assertSame([['addDriver', PageBuilderEntityManagerPass::ENTITY_NAMESPACE], ['addDriver', 'App\\Entity']], $this->calls($container, 'default'), 'excluded first, host mapping kept');
        self::assertCount(1, $container->getDefinition('doctrine.orm.page_builder_metadata_driver')->getMethodCalls());
    }

    /**
     * DoctrineBundle 3.4 `auto_discover_entities` gives a manager without mappings a default driver that claims every
     * entity class: every other manager gets a driver that maps nothing for the bundle namespace, first in its chain.
     */
    #[Test]
    public function excludesTheEntitiesBeforeTheDefaultDriverOfEveryOtherManager(): void
    {
        $container = $this->container(['default' => 'x', 'page_builder' => 'y']);
        $container->setDefinition('doctrine.orm.default_metadata_driver', $this->chain(true, true)->addMethodCall('setDefaultDriver', [new Reference('auto_discover')]));
        $container->setDefinition('doctrine.orm.page_builder_metadata_driver', $this->chain(true, false));

        new PageBuilderEntityManagerPass()->process($container);

        $expected = [['addDriver', PageBuilderEntityManagerPass::ENTITY_NAMESPACE], ['addDriver', 'App\\Entity'], ['setDefaultDriver', null]];
        self::assertSame($expected, $this->calls($container, 'default'));
        $excluded = $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls()[0][1][0];
        self::assertInstanceOf(Definition::class, $excluded);
        self::assertSame(ExcludedEntityNamespaceDriver::class, $excluded->getClass());
        self::assertSame([['addDriver', PageBuilderEntityManagerPass::ENTITY_NAMESPACE]], $this->calls($container, 'page_builder'), 'the configured manager keeps its own driver only');

        new PageBuilderEntityManagerPass()->process($container);
        self::assertSame($expected, $this->calls($container, 'default'), 'idempotent');
        self::assertSame([['addDriver', PageBuilderEntityManagerPass::ENTITY_NAMESPACE]], $this->calls($container, 'page_builder'), 'idempotent');
    }

    #[Test]
    public function unknownManagerFailsEvenInCheckOnlyMode(): void
    {
        $container = $this->container(['default' => 'x']);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('known: "default"');
        new PageBuilderEntityManagerPass(checkOnly: true)->process($container);
    }

    #[Test]
    public function checkOnlyModeLeavesTheMappingAlone(): void
    {
        $container = $this->container(['default' => 'x', 'page_builder' => 'y']);
        $container->setDefinition('doctrine.orm.default_metadata_driver', $this->chain(true, true));
        new PageBuilderEntityManagerPass(checkOnly: true)->process($container);
        self::assertCount(2, $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls());
    }

    #[Test]
    public function managerWithoutTheBundleMappingFails(): void
    {
        $container = $this->container(['default' => 'x', 'page_builder' => 'y']);
        $container->setDefinition('doctrine.orm.page_builder_metadata_driver', $this->chain(false, true));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('does not map');
        new PageBuilderEntityManagerPass()->process($container);
    }

    /**
     * @param array<string, string> $managers
     */
    private function container(array $managers): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(NowoPageBuilderKitExtension::ENTITY_MANAGER_PARAMETER, 'page_builder');
        $container->setParameter('doctrine.entity_managers', $managers);

        return $container;
    }

    /**
     * @return list<array{string, mixed}> method name and namespace argument of each call of the manager's chain
     */
    private function calls(ContainerBuilder $container, string $manager): array
    {
        $calls = [];
        foreach ($container->getDefinition('doctrine.orm.' . $manager . '_metadata_driver')->getMethodCalls() as $call) {
            $calls[] = [$call[0], $call[1][1] ?? null];
        }

        return $calls;
    }

    private function chain(bool $bundle, bool $host): Definition
    {
        $chain = new Definition();
        if ($bundle) {
            $chain->addMethodCall('addDriver', [new Definition(), PageBuilderEntityManagerPass::ENTITY_NAMESPACE]);
        }
        if ($host) {
            $chain->addMethodCall('addDriver', [new Definition(), 'App\\Entity']);
        }

        return $chain;
    }
}
