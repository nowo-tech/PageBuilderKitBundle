<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\DependencyInjection\Compiler;

use Nowo\PageBuilderKitBundle\DependencyInjection\NowoPageBuilderKitExtension;
use Nowo\PageBuilderKitBundle\Persistence\OwnEntityManagerSubscriber;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function array_keys;
use function implode;
use function is_array;
use function is_string;
use function sprintf;
use function str_starts_with;

/**
 * Keeps the bundle entities on `doctrine.entity_manager` only (1.14).
 *
 * DoctrineBundle's `auto_mapping` (the Flex default on the default manager) maps every bundle's `Entity` directory,
 * so with a dedicated manager configured the entities would be mapped twice and `getManagerForClass()` would return
 * the default manager. This pass removes the bundle namespace from the mapping driver chain of every other manager
 * and fails the build when the configured manager does not exist or does not map the entities. When the configured
 * manager is the host's default manager, the bundle does not clear it at the unit-of-work boundaries (1.15.1).
 */
final readonly class PageBuilderEntityManagerPass implements CompilerPassInterface
{
    public const string ENTITY_NAMESPACE = 'Nowo\\PageBuilderKitBundle\\Entity';

    /**
     * @param bool $checkOnly Only fail fast on an unknown manager (registered before the mapping passes, which would
     *                        otherwise fail first with a less helpful "non-existent service" error)
     */
    public function __construct(
        private bool $checkOnly = false,
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(NowoPageBuilderKitExtension::ENTITY_MANAGER_PARAMETER)) {
            return;
        }

        $name = $container->getParameter(NowoPageBuilderKitExtension::ENTITY_MANAGER_PARAMETER);
        if (!is_string($name) || $name === '') {
            return;
        }

        $managers = $container->hasParameter('doctrine.entity_managers') ? $container->getParameter('doctrine.entity_managers') : [];
        $managers = is_array($managers) ? $managers : [];
        if (!isset($managers[$name])) {
            throw new InvalidConfigurationException(sprintf('nowo_page_builder_kit.doctrine.entity_manager is "%s", but no such Doctrine entity manager is configured (known: "%s"). Add it under doctrine.orm.entity_managers or remove the option.', $name, implode('", "', array_keys($managers))));
        }

        if ($this->checkOnly) {
            return;
        }

        // The host's default manager is the host's to clear (REQ-RUNTIME-002 rule 7): never touched by the bundle.
        $default = $container->hasParameter('doctrine.default_entity_manager') ? $container->getParameter('doctrine.default_entity_manager') : null;
        if ($name === $default) {
            $container->removeDefinition(OwnEntityManagerSubscriber::class);
        }

        foreach (array_keys($managers) as $manager) {
            $chainId = sprintf('doctrine.orm.%s_metadata_driver', $manager);
            if (!$container->hasDefinition($chainId)) {
                continue;
            }

            $chain = $container->getDefinition($chainId);
            $calls = [];
            $maps  = false;
            foreach ($chain->getMethodCalls() as $call) {
                $isBundleDriver = $call[0] === 'addDriver' && is_string($call[1][1] ?? null) && str_starts_with($call[1][1], self::ENTITY_NAMESPACE);
                if ($isBundleDriver && $manager !== $name) {
                    continue;
                }

                $maps    = $maps || $isBundleDriver;
                $calls[] = $call;
            }

            $chain->setMethodCalls($calls);

            if ($manager === $name && !$maps) {
                throw new InvalidConfigurationException(sprintf('The Doctrine entity manager "%s" (nowo_page_builder_kit.doctrine.entity_manager) does not map %s.', $name, self::ENTITY_NAMESPACE));
            }
        }
    }
}
