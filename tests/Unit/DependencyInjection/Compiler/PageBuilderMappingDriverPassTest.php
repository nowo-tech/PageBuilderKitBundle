<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\DependencyInjection\Compiler;

use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\PageBuilderEntityManagerPass;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\PageBuilderMappingDriverPass;
use Nowo\PageBuilderKitBundle\DependencyInjection\NowoPageBuilderKitExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

use function dirname;

/**
 * 1.16: `doctrine.mapping_type` picks the driver of the bundle entities; `attribute` (the default) registers exactly the
 * attribute driver of 1.14–1.15, on the same manager.
 */
final class PageBuilderMappingDriverPassTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, ?string, string}>
     */
    public static function mappingTypes(): iterable
    {
        yield 'no parameter: attribute, default manager' => [null, null, 'default'];
        yield 'attribute on the default manager' => ['attribute', null, 'default'];
        yield 'attribute on a dedicated manager' => ['attribute', 'page_builder', 'page_builder'];
    }

    #[DataProvider('mappingTypes')]
    public function testAttributeMappingIsTheDefault(?string $type, ?string $manager, string $expectedManager): void
    {
        $container = $this->container($type, $manager);
        new PageBuilderMappingDriverPass(dirname(__DIR__, 4) . '/src/Entity')->process($container);

        $calls = $container->getDefinition('doctrine.orm.' . $expectedManager . '_metadata_driver')->getMethodCalls();
        self::assertCount(1, $calls);
        self::assertSame('addDriver', $calls[0][0]);
        self::assertSame(PageBuilderEntityManagerPass::ENTITY_NAMESPACE, $calls[0][1][1]);
        self::assertInstanceOf(Definition::class, $calls[0][1][0]);
        self::assertSame(AttributeDriver::class, $calls[0][1][0]->getClass());
        self::assertSame([[dirname(__DIR__, 4) . '/src/Entity']], [$calls[0][1][0]->getArgument(0)]);
    }

    public function testXmlMappingRegistersTheShippedXmlDirectory(): void
    {
        $container = $this->container('xml', 'page_builder');
        new PageBuilderMappingDriverPass(dirname(__DIR__, 4) . '/src/Entity')->process($container);

        self::assertSame([], $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls());
        $calls = $container->getDefinition('doctrine.orm.page_builder_metadata_driver')->getMethodCalls();
        self::assertCount(1, $calls);
        self::assertSame(PageBuilderEntityManagerPass::ENTITY_NAMESPACE, $calls[0][1][1]);
        $driver = $calls[0][1][0];
        self::assertInstanceOf(Definition::class, $driver);
        self::assertSame(XmlDriver::class, $driver->getClass());
        $locator = $driver->getArgument(0);
        self::assertInstanceOf(Definition::class, $locator);
        self::assertSame([NowoPageBuilderKitExtension::XML_MAPPING_DIR => PageBuilderEntityManagerPass::ENTITY_NAMESPACE], $locator->getArgument(0));
        self::assertDirectoryExists(NowoPageBuilderKitExtension::XML_MAPPING_DIR);
    }

    public function testNoAttributeDriverWithoutAnEntityDirectory(): void
    {
        $container = $this->container('attribute', null);
        new PageBuilderMappingDriverPass(__DIR__ . '/missing')->process($container);

        self::assertSame([], $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls());
    }

    private function container(?string $type, ?string $manager): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('doctrine.default_entity_manager', 'default');
        $container->setParameter(NowoPageBuilderKitExtension::ENTITY_MANAGER_PARAMETER, $manager);
        if ($type !== null) {
            $container->setParameter(NowoPageBuilderKitExtension::MAPPING_TYPE_PARAMETER, $type);
        }
        $container->setDefinition('doctrine.orm.default_metadata_driver', new Definition(MappingDriverChain::class));
        $container->setDefinition('doctrine.orm.page_builder_metadata_driver', new Definition(MappingDriverChain::class));

        return $container;
    }
}
