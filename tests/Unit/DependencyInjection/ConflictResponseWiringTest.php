<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\DependencyInjection;

use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\AfterCommitMiddlewarePass;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\PageBuilderEntityManagerPass;
use Nowo\PageBuilderKitBundle\DependencyInjection\Configuration;
use Nowo\PageBuilderKitBundle\DependencyInjection\NowoPageBuilderKitExtension;
use Nowo\PageBuilderKitBundle\EventListener\PageBuilderConflictListener;
use Nowo\PageBuilderKitBundle\Persistence\AfterCommitMiddleware;
use Nowo\PageBuilderKitBundle\Persistence\PageBuilderTransaction;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * 1.17 (REQ-RUNTIME-003): conflict_response options, the conflict listener, the commit ledger and the write-unit runner wiring.
 */
final class ConflictResponseWiringTest extends TestCase
{
    #[Test]
    public function theDefaultsFollowTransactionDoctrine(): void
    {
        $config = new Processor()->processConfiguration(new Configuration(), [[]]);
        self::assertSame(['status' => null, 'retry_after' => null, 'commit_tracking' => 'bundle'], $config['conflict_response']);

        $container = $this->load([]);
        $listener  = $container->getDefinition(PageBuilderConflictListener::class);
        $policy    = $listener->getArgument(0);
        self::assertInstanceOf(Reference::class, $policy);
        self::assertSame(ConflictResponsePolicy::class, (string) $policy);
        self::assertEquals(new Reference(NowoPageBuilderKitExtension::COMMIT_LEDGER_SERVICE), $listener->getArgument(1));
        self::assertTrue($container->hasDefinition(NowoPageBuilderKitExtension::COMMIT_TRACKING_MIDDLEWARE));
        // Audit UW-4: above 0, before the framework's error listener logs the exception.
        self::assertSame([['event' => 'kernel.request', 'method' => 'onRequest', 'priority' => PageBuilderConflictListener::REQUEST_PRIORITY], ['event' => 'kernel.exception', 'method' => 'onException', 'priority' => 64]], $listener->getTag('kernel.event_listener'));
        self::assertNull($container->getDefinition(PageBuilderTransaction::class)->getArgument('$managerName'));
    }

    #[Test]
    public function aHostStatusOrRetryAfterBuildsTheBundlePolicy(): void
    {
        $policy = $this->load(['conflict_response' => ['status' => 503]])->getDefinition(PageBuilderConflictListener::class)->getArgument(0);
        self::assertInstanceOf(Definition::class, $policy);
        self::assertSame([503, ConflictResponsePolicy::DEFAULT_RETRY_AFTER], $policy->getArguments());

        $policy = $this->load(['conflict_response' => ['retry_after' => 5]])->getDefinition(PageBuilderConflictListener::class)->getArgument(0);
        self::assertInstanceOf(Definition::class, $policy);
        self::assertSame([ConflictResponsePolicy::STATUS_CONFLICT, 5], $policy->getArguments());
    }

    #[Test]
    public function commitTrackingOffRegistersNoMiddleware(): void
    {
        $container = $this->load(['conflict_response' => ['commit_tracking' => 'off'], 'doctrine' => ['entity_manager' => 'pages']]);

        self::assertFalse($container->hasDefinition(NowoPageBuilderKitExtension::COMMIT_TRACKING_MIDDLEWARE));
        self::assertFalse($container->hasDefinition(NowoPageBuilderKitExtension::COMMIT_LEDGER_SERVICE));
        self::assertNull($container->getDefinition(PageBuilderConflictListener::class)->getArgument(1));
        self::assertSame('pages', $container->getDefinition(PageBuilderTransaction::class)->getArgument('$managerName'));
    }

    #[Test]
    public function anInvalidStatusFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('conflict_response.status must be 409 or 503');
        new Processor()->processConfiguration(new Configuration(), [['conflict_response' => ['status' => 500]]]);
    }

    #[Test]
    public function theCommitTrackingMiddlewareFollowsTheBundleConnection(): void
    {
        $container = new ContainerBuilder();
        $container->register(AfterCommitMiddleware::class)->addTag('doctrine.middleware');
        $container->register(NowoPageBuilderKitExtension::COMMIT_TRACKING_MIDDLEWARE)->addTag('doctrine.middleware');
        $container->setParameter(NowoPageBuilderKitExtension::ENTITY_MANAGER_PARAMETER, 'page_builder');
        $container->register('doctrine.orm.page_builder_entity_manager')->setArguments([new Reference('doctrine.dbal.pages_connection')]);

        new AfterCommitMiddlewarePass()->process($container);

        self::assertSame([['connection' => 'pages']], $container->getDefinition(NowoPageBuilderKitExtension::COMMIT_TRACKING_MIDDLEWARE)->getTag('doctrine.middleware'));
    }

    #[Test]
    public function theRunnerOwnsOnlyAManagerOtherThanTheHostDefault(): void
    {
        foreach (['page_builder' => true, 'default' => false] as $name => $owns) {
            $container = new ContainerBuilder();
            $container->setParameter(NowoPageBuilderKitExtension::ENTITY_MANAGER_PARAMETER, $name);
            $container->setParameter('doctrine.entity_managers', ['default' => 'x', 'page_builder' => 'y']);
            $container->setParameter('doctrine.default_entity_manager', 'default');
            $container->register(PageBuilderTransaction::class)->setArgument('$ownsEntityManager', false);
            $chain = new Definition();
            $chain->addMethodCall('addDriver', [new Definition(), PageBuilderEntityManagerPass::ENTITY_NAMESPACE]);
            $container->setDefinition('doctrine.orm.' . $name . '_metadata_driver', $chain);

            new PageBuilderEntityManagerPass()->process($container);

            self::assertSame($owns, $container->getDefinition(PageBuilderTransaction::class)->getArgument('$ownsEntityManager'), $name);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);
        new NowoPageBuilderKitExtension()->load([$config + ['security' => ['allow_unauthenticated' => true]]], $container);

        return $container;
    }
}
