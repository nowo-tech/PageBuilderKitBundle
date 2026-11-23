<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\DependencyInjection\Compiler;

use Nowo\PageBuilderKitBundle\DependencyInjection\NowoPageBuilderKitExtension;
use Nowo\PageBuilderKitBundle\Persistence\OwnEntityManagerSubscriber;
use Nowo\TransactionDoctrine\PrivateManager\DependencyInjection\PrivateEntityManagerPass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function is_string;

/**
 * REQ-ARCH-001 rule 6 (2.0), once the application's Doctrine managers are known. With `transaction: bundle` the bundle
 * never flushes the application's manager:
 *
 * - `doctrine.entity_manager` naming the application's default entity manager (`doctrine.default_entity_manager`, so a
 *   default manager not named "default" works without configuration; lesson W0-1): the same setup as leaving it unset,
 *   so the bundle switches to its private entity manager on that manager (the extension cannot know the default
 *   manager's name and wired it as a dedicated one);
 * - then, whenever the private manager is used, resolves the manager it is created on and runs transaction-doctrine's
 *   {@see PrivateEntityManagerPass}, which checks that manager exists and tags the bundle's
 *   PrivateEntityManagerMiddleware `doctrine.middleware` for its connection only.
 *
 * A dedicated `doctrine.entity_manager` (the DDD option) and `transaction: host` are left as the extension wired them.
 * The 1.x mode (the bundle flushing the application's default manager), its notices and its host-flush probe are gone.
 *
 * Runs before DoctrineBundle's MiddlewaresPass (priority 0) and before {@see PageBuilderEntityManagerPass} (-10).
 */
final class PageBuilderPrivateEntityManagerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $default = $this->defaultManager($container);
        $named   = $container->hasParameter(NowoPageBuilderKitExtension::ENTITY_MANAGER_PARAMETER)
            ? $container->getParameter(NowoPageBuilderKitExtension::ENTITY_MANAGER_PARAMETER)
            : null;
        $host = $container->hasParameter('nowo_page_builder_kit.doctrine.transaction')
            && $container->getParameter('nowo_page_builder_kit.doctrine.transaction') === 'host';

        if (is_string($named) && $named === $default && !$host && !$container->hasDefinition(NowoPageBuilderKitExtension::PRIVATE_MIDDLEWARE_SERVICE)) {
            $container->removeDefinition(OwnEntityManagerSubscriber::class);
            $container->removeAlias(NowoPageBuilderKitExtension::ENTITY_MANAGER_SERVICE);
            NowoPageBuilderKitExtension::registerPrivateEntityManager($container, $named);
        }

        if (!$container->hasDefinition(NowoPageBuilderKitExtension::PRIVATE_MIDDLEWARE_SERVICE)) {
            return;
        }

        // Set with the middleware ('' until resolved here).
        $source = $container->getParameter(NowoPageBuilderKitExtension::PRIVATE_SOURCE_MANAGER_PARAMETER);
        if (!is_string($source) || $source === '') {
            $container->setParameter(NowoPageBuilderKitExtension::PRIVATE_SOURCE_MANAGER_PARAMETER, $default);
        }

        new PrivateEntityManagerPass(
            NowoPageBuilderKitExtension::PRIVATE_MIDDLEWARE_SERVICE,
            NowoPageBuilderKitExtension::PRIVATE_SOURCE_MANAGER_PARAMETER,
            'nowo_page_builder_kit private entity manager (default entity manager)',
        )->process($container);
    }

    /** The application's default entity manager (`doctrine.default_entity_manager`, else "default"). */
    private function defaultManager(ContainerBuilder $container): string
    {
        $default = $container->hasParameter('doctrine.default_entity_manager') ? $container->getParameter('doctrine.default_entity_manager') : null;

        return is_string($default) && $default !== '' ? $default : 'default';
    }
}
