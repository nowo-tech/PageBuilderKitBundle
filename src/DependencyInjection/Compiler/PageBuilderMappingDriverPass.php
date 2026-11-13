<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Nowo\PageBuilderKitBundle\DependencyInjection\NowoPageBuilderKitExtension;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function is_dir;

/**
 * Registers the mapping of the bundle entities on `doctrine.entity_manager` (or the default entity manager), 1.16.
 *
 * `doctrine.mapping_type: attribute` (the default) registers exactly the attribute driver of 1.14–1.15;
 * `doctrine.mapping_type: xml` registers the shipped XML mapping instead ({@see NowoPageBuilderKitExtension::XML_MAPPING_DIR},
 * the same tables, columns, indexes and constraints) for hosts whose managers load XML mapping only (REQ-ARCH-001
 * rule 2). The choice is read when the container is compiled, after the bundle configuration was processed.
 */
final readonly class PageBuilderMappingDriverPass implements CompilerPassInterface
{
    public function __construct(
        private string $entityDir,
        private string $xmlDir = NowoPageBuilderKitExtension::XML_MAPPING_DIR,
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        $type = $container->hasParameter(NowoPageBuilderKitExtension::MAPPING_TYPE_PARAMETER)
            ? $container->getParameter(NowoPageBuilderKitExtension::MAPPING_TYPE_PARAMETER)
            : 'attribute';
        $managers = [NowoPageBuilderKitExtension::ENTITY_MANAGER_PARAMETER];

        if ($type === 'xml') {
            DoctrineOrmMappingsPass::createXmlMappingDriver([$this->xmlDir => PageBuilderEntityManagerPass::ENTITY_NAMESPACE], $managers)
                ->process($container);

            return;
        }

        if (is_dir($this->entityDir)) {
            DoctrineOrmMappingsPass::createAttributeMappingDriver([PageBuilderEntityManagerPass::ENTITY_NAMESPACE], [$this->entityDir], $managers)
                ->process($container);
        }
    }
}
