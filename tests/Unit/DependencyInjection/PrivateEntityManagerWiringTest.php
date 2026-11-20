<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\DependencyInjection;

use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\PageBuilderPrivateEntityManagerPass;
use Nowo\PageBuilderKitBundle\DependencyInjection\NowoPageBuilderKitExtension;
use Nowo\PageBuilderKitBundle\Persistence\OwnEntityManagerSubscriber;
use Nowo\PageBuilderKitBundle\Persistence\PageBuilderTransaction;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepository;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRevisionRepository;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageTemplateRepository;
use Nowo\TransactionDoctrine\HostFlushProbe;
use Nowo\TransactionDoctrine\PrivateManager\PrivateEntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function str_contains;

use const E_USER_DEPRECATED;

/**
 * 1.19 (REQ-ARCH-001 rule 6 deprecation layer): `doctrine.private_entity_manager`, its notices and the host-flush probe
 * wiring.
 */
#[CoversClass(NowoPageBuilderKitExtension::class)]
#[CoversClass(PageBuilderPrivateEntityManagerPass::class)]
final class PrivateEntityManagerWiringTest extends TestCase
{
    public function testUnsetTriggersTheNoticeAndWiresTheProbe(): void
    {
        [$container, $notices] = $this->load([]);

        self::assertSame([sprintf('Since nowo-tech/page-builder-kit-bundle 1.19: %s', NowoPageBuilderKitExtension::PRIVATE_ENTITY_MANAGER_UNSET_DEPRECATION)], $notices);
        self::assertFalse($container->getParameter(NowoPageBuilderKitExtension::PRIVATE_ENTITY_MANAGER_PARAMETER));
        $this->assertProbeWired($container);
        self::assertFalse($container->hasDefinition(NowoPageBuilderKitExtension::PRIVATE_ENTITY_MANAGER_SERVICE));
    }

    public function testExplicitFalseTriggersTheRemovalNoticeAndWiresTheProbe(): void
    {
        [$container, $notices] = $this->load(['private_entity_manager' => false]);

        self::assertSame([sprintf('Since nowo-tech/page-builder-kit-bundle 1.19: %s', NowoPageBuilderKitExtension::PRIVATE_ENTITY_MANAGER_FALSE_DEPRECATION)], $notices);
        self::assertStringContainsString('removed in 2.0', NowoPageBuilderKitExtension::PRIVATE_ENTITY_MANAGER_FALSE_DEPRECATION);
        $this->assertProbeWired($container);
    }

    public function testTrueWiresThePrivateManagerWithoutNoticeNorProbe(): void
    {
        [$container, $notices] = $this->load(['private_entity_manager' => true]);

        self::assertSame([], $notices);
        self::assertTrue($container->getParameter(NowoPageBuilderKitExtension::PRIVATE_ENTITY_MANAGER_PARAMETER));
        self::assertFalse($container->hasDefinition(NowoPageBuilderKitExtension::HOST_FLUSH_PROBE_SERVICE));

        $private = $container->getDefinition(NowoPageBuilderKitExtension::PRIVATE_ENTITY_MANAGER_SERVICE);
        self::assertSame(PrivateEntityManager::class, $private->getClass());
        self::assertTrue($private->hasTag(PrivateEntityManager::TAG), 'reset by transaction-doctrine\'s boundary listener');
        self::assertSame([['method' => 'reset']], $private->getTag('kernel.reset'));
        self::assertSame(NowoPageBuilderKitExtension::PRIVATE_ENTITY_MANAGER_SERVICE, (string) $container->getAlias(NowoPageBuilderKitExtension::ENTITY_MANAGER_SERVICE));

        foreach ([BuilderPageRepository::class, BuilderPageRevisionRepository::class, BuilderPageTemplateRepository::class] as $repository) {
            self::assertEquals(new Reference(NowoPageBuilderKitExtension::PRIVATE_REGISTRY_SERVICE), $container->getDefinition($repository)->getArgument('$registry'), $repository);
        }

        $transaction = $container->getDefinition(PageBuilderTransaction::class);
        self::assertTrue($transaction->getArgument('$ownsEntityManager'));
        self::assertEquals(new Reference(NowoPageBuilderKitExtension::PRIVATE_REGISTRY_SERVICE), $transaction->getArgument('$registry'));
        self::assertArrayNotHasKey('$hostFlushProbe', $transaction->getArguments());

        $subscriber = $container->getDefinition(OwnEntityManagerSubscriber::class);
        self::assertTrue($subscriber->hasTag('kernel.event_subscriber'), 'dropped at every boundary without transaction-doctrine\'s bundle too');
    }

    /**
     * @param array<string, mixed> $doctrine
     */
    #[DataProvider('optionsThatStayIn2')]
    public function testOptionsThatStayIn20GetNoNoticeNorProbe(array $doctrine): void
    {
        [$container, $notices] = $this->load($doctrine);

        self::assertSame([], $notices);
        self::assertFalse($container->hasDefinition(NowoPageBuilderKitExtension::HOST_FLUSH_PROBE_SERVICE));
        self::assertArrayNotHasKey('$hostFlushProbe', $container->getDefinition(PageBuilderTransaction::class)->getArguments());
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function optionsThatStayIn2(): iterable
    {
        yield 'dedicated manager' => [['entity_manager' => 'page_builder']];
        yield 'dedicated manager, false' => [['entity_manager' => 'page_builder', 'private_entity_manager' => false]];
        yield 'host transaction (DDD)' => [['transaction' => 'host']];
    }

    /**
     * @param array<string, mixed> $doctrine
     */
    #[DataProvider('invalidCombinations')]
    public function testTrueRejectsADedicatedManagerOrHostTransaction(array $doctrine, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        $this->load($doctrine);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidCombinations(): iterable
    {
        yield 'entity_manager' => [['private_entity_manager' => true, 'entity_manager' => 'page_builder'], 'cannot be combined with doctrine.entity_manager'];
        yield 'transaction host' => [['private_entity_manager' => true, 'transaction' => 'host'], 'requires doctrine.transaction: bundle'];
    }

    public function testPassResolvesTheDefaultManagerAndTagsTheMiddlewareForItsConnection(): void
    {
        [$container] = $this->load(['private_entity_manager' => true]);
        $container->setParameter('doctrine.default_entity_manager', 'main');
        $container->setParameter('doctrine.entity_managers', ['main' => 'doctrine.orm.main_entity_manager']);
        $container->setDefinition('doctrine.orm.main_entity_manager', new Definition('stdClass', [new Reference('doctrine.dbal.app_connection')]));

        new PageBuilderPrivateEntityManagerPass()->process($container);

        self::assertSame('main', $container->getParameter(NowoPageBuilderKitExtension::PRIVATE_SOURCE_MANAGER_PARAMETER));
        self::assertSame([['connection' => 'app']], $container->getDefinition(NowoPageBuilderKitExtension::PRIVATE_MIDDLEWARE_SERVICE)->getTag('doctrine.middleware'));
    }

    public function testPassUsesTransactionDoctrinesSharedProbeWhenRegistered(): void
    {
        [$container] = $this->load([]);
        $container->register(HostFlushProbe::SERVICE_ID, HostFlushProbe::class);

        new PageBuilderPrivateEntityManagerPass()->process($container);

        self::assertSame(HostFlushProbe::SERVICE_ID, (string) $container->getAlias(NowoPageBuilderKitExtension::HOST_FLUSH_PROBE_SERVICE));
    }

    public function testPassKeepsTheBundlesOwnProbeWithoutTransactionDoctrinesBundle(): void
    {
        [$container] = $this->load([]);

        new PageBuilderPrivateEntityManagerPass()->process($container);

        $probe = $container->getDefinition(NowoPageBuilderKitExtension::HOST_FLUSH_PROBE_SERVICE);
        self::assertSame(HostFlushProbe::class, $probe->getClass());
        self::assertTrue($probe->hasTag('kernel.reset'));
    }

    /**
     * Audit XD-4: `entity_manager` naming the default manager flushes it as when unset: the pass triggers the same
     * notice and wires the probe (the extension cannot know the default manager's name).
     */
    public function testTheDefaultManagerNamedAsEntityManagerTriggersTheNoticeAndWiresTheProbe(): void
    {
        [$container, $notices] = $this->load(['entity_manager' => 'main']);
        self::assertSame([], $notices);
        $container->setParameter('doctrine.default_entity_manager', 'main');

        self::assertSame([sprintf('Since nowo-tech/page-builder-kit-bundle 1.19: %s', NowoPageBuilderKitExtension::PRIVATE_ENTITY_MANAGER_UNSET_DEPRECATION)], $this->process($container));
        $this->assertProbeWired($container);
    }

    public function testTheDefaultManagerNamedWithExplicitFalseTriggersTheRemovalNotice(): void
    {
        [$container] = $this->load(['entity_manager' => 'default', 'private_entity_manager' => false]);

        // No doctrine.default_entity_manager parameter: "default".
        self::assertSame([sprintf('Since nowo-tech/page-builder-kit-bundle 1.19: %s', NowoPageBuilderKitExtension::PRIVATE_ENTITY_MANAGER_FALSE_DEPRECATION)], $this->process($container));
        $this->assertProbeWired($container);
    }

    /**
     * @param array<string, mixed> $doctrine
     */
    #[DataProvider('namedManagersWithoutNotice')]
    public function testTheDefaultManagerCheckLeavesTheOptionsThatStayIn20Alone(array $doctrine): void
    {
        [$container] = $this->load($doctrine);
        $container->setParameter('doctrine.default_entity_manager', 'main');

        self::assertSame([], $this->process($container));
        self::assertFalse($container->hasDefinition(NowoPageBuilderKitExtension::HOST_FLUSH_PROBE_SERVICE));
        self::assertArrayNotHasKey('$hostFlushProbe', $container->getDefinition(PageBuilderTransaction::class)->getArguments());
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function namedManagersWithoutNotice(): iterable
    {
        yield 'dedicated manager' => [['entity_manager' => 'page_builder']];
        yield 'default manager, host transaction (DDD)' => [['entity_manager' => 'main', 'transaction' => 'host']];
        yield 'host transaction (DDD)' => [['transaction' => 'host']];
    }

    /**
     * Audit XD-8: the shared probe is left to transaction-doctrine's boundary listener when it is registered.
     */
    public function testTheSharedProbeIsResetByTransactionDoctrineOnlyWhenItsBoundaryListenerIsRegistered(): void
    {
        [$container] = $this->load([]);
        $container->register(HostFlushProbe::SERVICE_ID, HostFlushProbe::class);
        new PageBuilderPrivateEntityManagerPass()->process($container);
        self::assertArrayNotHasKey('$resetsHostFlushProbe', $container->getDefinition(PageBuilderTransaction::class)->getArguments(), 'unit_of_work_boundaries: false');

        [$container] = $this->load([]);
        $container->register(HostFlushProbe::SERVICE_ID, HostFlushProbe::class);
        $container->register(PageBuilderPrivateEntityManagerPass::SHARED_BOUNDARY_LISTENER, 'stdClass');
        new PageBuilderPrivateEntityManagerPass()->process($container);
        self::assertFalse($container->getDefinition(PageBuilderTransaction::class)->getArgument('$resetsHostFlushProbe'));

        [$container] = $this->load([]);
        $container->register(PageBuilderPrivateEntityManagerPass::SHARED_BOUNDARY_LISTENER, 'stdClass');
        new PageBuilderPrivateEntityManagerPass()->process($container);
        self::assertArrayNotHasKey('$resetsHostFlushProbe', $container->getDefinition(PageBuilderTransaction::class)->getArguments(), 'the bundle\'s own probe');
    }

    /**
     * @return list<string> the private-manager notices the pass triggered
     */
    private function process(ContainerBuilder $container): array
    {
        $notices = [];
        // @phpstan-ignore frankenphp.worker.noSetErrorExceptionHandler (test only: restored right after)
        set_error_handler(static function (int $type, string $message) use (&$notices): bool {
            if (str_contains($message, 'private_entity_manager')) {
                $notices[] = $message;
            }

            return true;
        }, E_USER_DEPRECATED);

        try {
            new PageBuilderPrivateEntityManagerPass()->process($container);
        } finally {
            restore_error_handler();
        }

        return $notices;
    }

    private function assertProbeWired(ContainerBuilder $container): void
    {
        self::assertTrue($container->hasDefinition(NowoPageBuilderKitExtension::HOST_FLUSH_PROBE_SERVICE));
        self::assertEquals(new Reference(NowoPageBuilderKitExtension::HOST_FLUSH_PROBE_SERVICE), $container->getDefinition(PageBuilderTransaction::class)->getArgument('$hostFlushProbe'));
    }

    /**
     * @param array<string, mixed> $doctrine
     *
     * @return array{0: ContainerBuilder, 1: list<string>} the container and the private-manager notices triggered
     */
    private function load(array $doctrine): array
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);
        $notices = [];
        // @phpstan-ignore frankenphp.worker.noSetErrorExceptionHandler (test only: restored right after)
        set_error_handler(static function (int $type, string $message) use (&$notices): bool {
            if (str_contains($message, 'private_entity_manager')) {
                $notices[] = $message;
            }

            return true;
        }, E_USER_DEPRECATED);

        try {
            new NowoPageBuilderKitExtension()->load([['doctrine' => $doctrine, 'security' => ['allow_unauthenticated' => true]]], $container);
        } finally {
            restore_error_handler();
        }

        return [$container, $notices];
    }
}
