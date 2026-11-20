<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\DependencyInjection\Compiler;

use Nowo\PageBuilderKitBundle\DependencyInjection\NowoPageBuilderKitExtension;
use Nowo\PageBuilderKitBundle\Persistence\PageBuilderTransaction;
use Nowo\TransactionDoctrine\HostFlushProbe;
use Nowo\TransactionDoctrine\PrivateManager\DependencyInjection\PrivateEntityManagerPass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function is_string;
use function trigger_deprecation;

/**
 * 1.19 (REQ-ARCH-001 rule 6 deprecation layer).
 *
 * - `doctrine.private_entity_manager: true`: resolves the application's manager the private one is created on (the
 *   default entity manager, `doctrine.default_entity_manager`, so a default manager not named "default" works without
 *   configuration), then runs transaction-doctrine's {@see PrivateEntityManagerPass}, which checks that manager exists
 *   and tags the bundle's PrivateEntityManagerMiddleware `doctrine.middleware` for its connection only.
 * - `doctrine.entity_manager` naming the application's default manager with `transaction: bundle` and the option unset
 *   or `false` (audit XD-4): the bundle flushes that manager exactly as with `entity_manager` unset, so the same
 *   deprecation notice is triggered and the host-flush probe is wired (the extension cannot know the default
 *   manager's name).
 * - Host-flush probe (option unset): the bundle's own {@see HostFlushProbe} becomes an alias of transaction-doctrine's
 *   shared one when NowoTransactionDoctrineBundle is registered, so a host gets one report per class and unit of work.
 *   When that bundle also resets the shared probe at the unit-of-work boundaries (`unit_of_work_boundaries`, its
 *   default), {@see PageBuilderTransaction} leaves the reset to it (audit XD-8), so the bundle never clears what other
 *   bundles reported in the same unit of work.
 *
 * Runs before DoctrineBundle's MiddlewaresPass (priority 0).
 */
final class PageBuilderPrivateEntityManagerPass implements CompilerPassInterface
{
    /** transaction-doctrine's boundary listener, which resets the shared probe (`unit_of_work_boundaries: true`). */
    public const string SHARED_BOUNDARY_LISTENER = 'nowo_transaction_doctrine.unit_of_work_boundary_listener';

    public function process(ContainerBuilder $container): void
    {
        $notice = $container->hasParameter(NowoPageBuilderKitExtension::NAMED_DEFAULT_MANAGER_NOTICE_PARAMETER)
            ? $container->getParameter(NowoPageBuilderKitExtension::NAMED_DEFAULT_MANAGER_NOTICE_PARAMETER)
            : null;
        if (is_string($notice) && $notice !== '' && $container->getParameter(NowoPageBuilderKitExtension::ENTITY_MANAGER_PARAMETER) === $this->defaultManager($container)) {
            trigger_deprecation('nowo-tech/page-builder-kit-bundle', '1.19', $notice);
            NowoPageBuilderKitExtension::registerHostFlushProbe($container);
        }

        if ($container->hasDefinition(NowoPageBuilderKitExtension::HOST_FLUSH_PROBE_SERVICE) && $container->has(HostFlushProbe::SERVICE_ID)) {
            $container->removeDefinition(NowoPageBuilderKitExtension::HOST_FLUSH_PROBE_SERVICE);
            $container->setAlias(NowoPageBuilderKitExtension::HOST_FLUSH_PROBE_SERVICE, HostFlushProbe::SERVICE_ID);
            if ($container->has(self::SHARED_BOUNDARY_LISTENER)) {
                $container->getDefinition(PageBuilderTransaction::class)->setArgument('$resetsHostFlushProbe', false);
            }
        }

        if (!$container->hasDefinition(NowoPageBuilderKitExtension::PRIVATE_MIDDLEWARE_SERVICE)) {
            return;
        }

        // Set with the middleware by the extension ('' until resolved here).
        $source = $container->getParameter(NowoPageBuilderKitExtension::PRIVATE_SOURCE_MANAGER_PARAMETER);
        if (!is_string($source) || $source === '') {
            $container->setParameter(NowoPageBuilderKitExtension::PRIVATE_SOURCE_MANAGER_PARAMETER, $this->defaultManager($container));
        }

        new PrivateEntityManagerPass(
            NowoPageBuilderKitExtension::PRIVATE_MIDDLEWARE_SERVICE,
            NowoPageBuilderKitExtension::PRIVATE_SOURCE_MANAGER_PARAMETER,
            'nowo_page_builder_kit.doctrine.private_entity_manager (default entity manager)',
        )->process($container);
    }

    /** The application's default entity manager (`doctrine.default_entity_manager`, else "default"). */
    private function defaultManager(ContainerBuilder $container): string
    {
        $default = $container->hasParameter('doctrine.default_entity_manager') ? $container->getParameter('doctrine.default_entity_manager') : null;

        return is_string($default) && $default !== '' ? $default : 'default';
    }
}
