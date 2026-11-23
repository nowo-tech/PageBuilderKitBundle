<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\DependencyInjection;

use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\PageBuilderEntityManagerPass;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\PageBuilderPrivateEntityManagerPass;
use Nowo\PageBuilderKitBundle\DependencyInjection\Configuration;
use Nowo\PageBuilderKitBundle\DependencyInjection\NowoPageBuilderKitExtension;
use Nowo\PageBuilderKitBundle\Persistence\OwnEntityManagerSubscriber;
use Nowo\PageBuilderKitBundle\Persistence\PageBuilderEntityManagerFactory;
use Nowo\PageBuilderKitBundle\Persistence\PageBuilderTransaction;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepository;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRevisionRepository;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageTemplateRepository;
use Nowo\TransactionDoctrine\PrivateManager\PrivateEntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function defined;
use function restore_error_handler;
use function set_error_handler;
use function str_contains;

use const E_USER_DEPRECATED;

/**
 * 2.0 (REQ-ARCH-001 rule 6): the setting table of `doctrine.*` after the flip, at the container level.
 *
 * | setting                                         | 2.0                                                        |
 * |-------------------------------------------------|------------------------------------------------------------|
 * | nothing (the 1.x default)                       | private manager on the default manager, no notice          |
 * | `private_entity_manager: true`                  | the same; the key is a deprecated no-op (Q4)               |
 * | `private_entity_manager: false`                 | build error ("remove the key")                             |
 * | `entity_manager: <the default manager's name>`  | private manager on it (W0-1)                               |
 * | `entity_manager: <dedicated>`                   | unchanged: that manager (the DDD option), no notice        |
 * | `transaction: host`                             | unchanged: the host's use case owns the flush, no notice   |
 * | `true` + `entity_manager` / `transaction: host` | still a build error (1.19 refused it)                      |
 */
#[CoversClass(NowoPageBuilderKitExtension::class)]
#[CoversClass(PageBuilderPrivateEntityManagerPass::class)]
#[CoversClass(PageBuilderEntityManagerPass::class)]
#[CoversClass(Configuration::class)]
final class PrivateEntityManagerWiringTest extends TestCase
{
    public function testZeroConfigWiresThePrivateManagerWithoutNotice(): void
    {
        [$container, $notices] = $this->load([]);

        self::assertSame([], $notices, 'no notice in 2.0');
        $this->assertPrivateManager($container, '');
    }

    public function testTrueIsADeprecatedNoOp(): void
    {
        [$container, $notices] = $this->load(['private_entity_manager' => true]);

        self::assertCount(1, $notices);
        self::assertStringContainsString('Since nowo-tech/page-builder-kit-bundle 2.0: The "private_entity_manager" option at "nowo_page_builder_kit.doctrine" has no effect since 2.0', $notices[0]);
        self::assertStringContainsString('The key is removed in 3.0.', $notices[0]);
        $this->assertPrivateManager($container, '');
    }

    /**
     * @param array<string, mixed> $doctrine
     */
    #[DataProvider('falseSettings')]
    public function testFalseFailsTheBuild(array $doctrine, string $hint): void
    {
        try {
            $this->load($doctrine);
            self::fail('false must fail the build.');
        } catch (InvalidConfigurationException $exception) {
            self::assertStringContainsString('nowo_page_builder_kit.doctrine.private_entity_manager: false is no longer supported', $exception->getMessage());
            self::assertStringContainsString('Remove the key.', $exception->getMessage());
            self::assertStringContainsString($hint, $exception->getMessage());
            self::assertStringContainsString('See UPGRADING.md "To 2.0.0".', $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function falseSettings(): iterable
    {
        yield 'alone' => [['private_entity_manager' => false], 'The bundle then writes through its private entity manager'];
        yield 'with a dedicated manager' => [['private_entity_manager' => false, 'entity_manager' => 'page_builder'], 'doctrine.entity_manager "page_builder" stays: a dedicated manager keeps working'];
        yield 'with transaction: host' => [['private_entity_manager' => false, 'transaction' => 'host'], 'doctrine.transaction: host stays'];
    }

    public function testADedicatedManagerIsUnchanged(): void
    {
        [$container, $notices] = $this->load(['entity_manager' => 'page_builder']);
        $container->setParameter('doctrine.default_entity_manager', 'main');
        new PageBuilderPrivateEntityManagerPass()->process($container);

        self::assertSame([], $notices);
        self::assertFalse($container->getParameter(NowoPageBuilderKitExtension::PRIVATE_ENTITY_MANAGER_PARAMETER));
        self::assertFalse($container->hasDefinition(NowoPageBuilderKitExtension::PRIVATE_ENTITY_MANAGER_SERVICE));
        self::assertSame('doctrine.orm.page_builder_entity_manager', (string) $container->getAlias(NowoPageBuilderKitExtension::ENTITY_MANAGER_SERVICE));
        self::assertSame('page_builder', $container->getDefinition(PageBuilderTransaction::class)->getArgument('$managerName'));
        self::assertSame('page_builder', $container->getDefinition(OwnEntityManagerSubscriber::class)->getArgument(3));
        self::assertArrayNotHasKey('$registry', $container->getDefinition(BuilderPageRepository::class)->getArguments(), 'the application\'s registry');
    }

    public function testHostTransactionIsUnchanged(): void
    {
        [$container, $notices] = $this->load(['transaction' => 'host']);
        new PageBuilderPrivateEntityManagerPass()->process($container);

        self::assertSame([], $notices);
        self::assertFalse($container->getParameter(NowoPageBuilderKitExtension::PRIVATE_ENTITY_MANAGER_PARAMETER));
        self::assertFalse($container->hasDefinition(NowoPageBuilderKitExtension::PRIVATE_ENTITY_MANAGER_SERVICE));
        self::assertSame([PageBuilderEntityManagerFactory::class, 'create'], $container->getDefinition(NowoPageBuilderKitExtension::ENTITY_MANAGER_SERVICE)->getFactory());
        self::assertTrue($container->getDefinition(PageBuilderTransaction::class)->getArgument('$hostControlsTransaction'));
    }

    public function testTheDefaultManagerNamedWithHostTransactionIsUnchanged(): void
    {
        [$container] = $this->load(['entity_manager' => 'main', 'transaction' => 'host']);
        $container->setParameter('doctrine.default_entity_manager', 'main');
        new PageBuilderPrivateEntityManagerPass()->process($container);

        self::assertFalse($container->hasDefinition(NowoPageBuilderKitExtension::PRIVATE_ENTITY_MANAGER_SERVICE));
        self::assertSame('doctrine.orm.main_entity_manager', (string) $container->getAlias(NowoPageBuilderKitExtension::ENTITY_MANAGER_SERVICE));
    }

    /**
     * W0-1: `entity_manager` naming the default manager is the zero-config setup (in 1.x it flushed that manager): the
     * pass switches it to the private manager on that manager, and the entity-manager pass keeps the subscriber that
     * drops it.
     */
    public function testTheDefaultManagerNamedAsEntityManagerUsesThePrivateManager(): void
    {
        [$container, $notices] = $this->load(['entity_manager' => 'main']);
        $container->setParameter('doctrine.default_entity_manager', 'main');
        $container->setParameter('doctrine.entity_managers', ['main' => 'doctrine.orm.main_entity_manager']);
        $container->setDefinition('doctrine.orm.main_entity_manager', new Definition('stdClass', [new Reference('doctrine.dbal.app_connection')]));

        new PageBuilderPrivateEntityManagerPass()->process($container);
        new PageBuilderEntityManagerPass()->process($container);

        self::assertSame([], $notices);
        $this->assertPrivateManager($container, 'main');
        self::assertSame([['connection' => 'app']], $container->getDefinition(NowoPageBuilderKitExtension::PRIVATE_MIDDLEWARE_SERVICE)->getTag('doctrine.middleware'));
    }

    /**
     * @param array<string, mixed> $doctrine
     */
    #[DataProvider('invalidCombinations')]
    public function testTrueStillRejectsADedicatedManagerOrHostTransaction(array $doctrine, string $message): void
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
        [$container] = $this->load([]);
        $container->setParameter('doctrine.default_entity_manager', 'main');
        $container->setParameter('doctrine.entity_managers', ['main' => 'doctrine.orm.main_entity_manager']);
        $container->setDefinition('doctrine.orm.main_entity_manager', new Definition('stdClass', [new Reference('doctrine.dbal.app_connection')]));

        new PageBuilderPrivateEntityManagerPass()->process($container);

        self::assertSame('main', $container->getParameter(NowoPageBuilderKitExtension::PRIVATE_SOURCE_MANAGER_PARAMETER));
        self::assertSame([['connection' => 'app']], $container->getDefinition(NowoPageBuilderKitExtension::PRIVATE_MIDDLEWARE_SERVICE)->getTag('doctrine.middleware'));
    }

    public function testNoProbeAndNoNoticeConstantRemain(): void
    {
        [$container] = $this->load([]);

        self::assertFalse($container->hasDefinition('nowo_page_builder_kit.host_flush_probe'));
        self::assertArrayNotHasKey('$hostFlushProbe', $container->getDefinition(PageBuilderTransaction::class)->getArguments());
        self::assertFalse($container->hasParameter('nowo_page_builder_kit.private_entity_manager.named_default_manager_notice'));
        self::assertFalse(defined(NowoPageBuilderKitExtension::class . '::PRIVATE_ENTITY_MANAGER_UNSET_DEPRECATION'));
    }

    private function assertPrivateManager(ContainerBuilder $container, string $source): void
    {
        self::assertTrue($container->getParameter(NowoPageBuilderKitExtension::PRIVATE_ENTITY_MANAGER_PARAMETER));
        $private = $container->getDefinition(NowoPageBuilderKitExtension::PRIVATE_ENTITY_MANAGER_SERVICE);
        self::assertSame(PrivateEntityManager::class, $private->getClass());
        self::assertTrue($private->hasTag(PrivateEntityManager::TAG), 'reset by transaction-doctrine\'s boundary listener');
        self::assertSame([['method' => 'reset']], $private->getTag('kernel.reset'));
        self::assertSame(NowoPageBuilderKitExtension::PRIVATE_ENTITY_MANAGER_SERVICE, (string) $container->getAlias(NowoPageBuilderKitExtension::ENTITY_MANAGER_SERVICE));
        if ($source !== '') {
            self::assertSame($source, $container->getParameter(NowoPageBuilderKitExtension::PRIVATE_SOURCE_MANAGER_PARAMETER));
        }

        foreach ([BuilderPageRepository::class, BuilderPageRevisionRepository::class, BuilderPageTemplateRepository::class] as $repository) {
            self::assertEquals(new Reference(NowoPageBuilderKitExtension::PRIVATE_REGISTRY_SERVICE), $container->getDefinition($repository)->getArgument('$registry'), $repository);
        }

        $transaction = $container->getDefinition(PageBuilderTransaction::class);
        self::assertTrue($transaction->getArgument('$ownsEntityManager'));
        self::assertFalse($transaction->getArgument('$hostControlsTransaction'));
        self::assertEquals(new Reference(NowoPageBuilderKitExtension::PRIVATE_REGISTRY_SERVICE), $transaction->getArgument('$registry'));
        self::assertArrayNotHasKey('$hostFlushProbe', $transaction->getArguments());

        $subscriber = $container->getDefinition(OwnEntityManagerSubscriber::class);
        self::assertTrue($subscriber->hasTag('kernel.event_subscriber'), 'dropped at every boundary without transaction-doctrine\'s bundle too');
        self::assertEquals(new Reference(NowoPageBuilderKitExtension::PRIVATE_REGISTRY_SERVICE), $subscriber->getArgument(2));
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
            if (str_contains($message, 'private_entity_manager') || str_contains($message, 'flushed by')) {
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
