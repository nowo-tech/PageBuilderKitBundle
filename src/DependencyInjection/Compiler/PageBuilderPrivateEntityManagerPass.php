<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\DependencyInjection\Compiler;

use Nowo\PageBuilderKitBundle\DependencyInjection\NowoPageBuilderKitExtension;
use Nowo\TransactionDoctrine\HostFlushProbe;
use Nowo\TransactionDoctrine\PrivateManager\DependencyInjection\PrivateEntityManagerPass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function is_string;

/**
 * 1.19 (REQ-ARCH-001 rule 6 deprecation layer).
 *
 * - `doctrine.private_entity_manager: true`: resolves the application's manager the private one is created on (the
 *   default entity manager, `doctrine.default_entity_manager`, so a default manager not named "default" works without
 *   configuration), then runs transaction-doctrine's {@see PrivateEntityManagerPass}, which checks that manager exists
 *   and tags the bundle's PrivateEntityManagerMiddleware `doctrine.middleware` for its connection only.
 * - Host-flush probe (option unset): the bundle's own {@see HostFlushProbe} becomes an alias of transaction-doctrine's
 *   shared one when NowoTransactionDoctrineBundle is registered, so a host gets one report per class and unit of work.
 *
 * Runs before DoctrineBundle's MiddlewaresPass (priority 0).
 */
final class PageBuilderPrivateEntityManagerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition(NowoPageBuilderKitExtension::HOST_FLUSH_PROBE_SERVICE) && $container->has(HostFlushProbe::SERVICE_ID)) {
            $container->removeDefinition(NowoPageBuilderKitExtension::HOST_FLUSH_PROBE_SERVICE);
            $container->setAlias(NowoPageBuilderKitExtension::HOST_FLUSH_PROBE_SERVICE, HostFlushProbe::SERVICE_ID);
        }

        if (!$container->hasDefinition(NowoPageBuilderKitExtension::PRIVATE_MIDDLEWARE_SERVICE)) {
            return;
        }

        // Set with the middleware by the extension ('' until resolved here).
        $source = $container->getParameter(NowoPageBuilderKitExtension::PRIVATE_SOURCE_MANAGER_PARAMETER);
        if (!is_string($source) || $source === '') {
            $default = $container->hasParameter('doctrine.default_entity_manager') ? $container->getParameter('doctrine.default_entity_manager') : null;
            $container->setParameter(NowoPageBuilderKitExtension::PRIVATE_SOURCE_MANAGER_PARAMETER, is_string($default) && $default !== '' ? $default : 'default');
        }

        new PrivateEntityManagerPass(
            NowoPageBuilderKitExtension::PRIVATE_MIDDLEWARE_SERVICE,
            NowoPageBuilderKitExtension::PRIVATE_SOURCE_MANAGER_PARAMETER,
            'nowo_page_builder_kit.doctrine.private_entity_manager (default entity manager)',
        )->process($container);
    }
}
