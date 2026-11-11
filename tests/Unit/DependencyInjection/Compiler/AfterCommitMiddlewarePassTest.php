<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\AfterCommitMiddlewarePass;
use Nowo\PageBuilderKitBundle\DependencyInjection\NowoPageBuilderKitExtension;
use Nowo\PageBuilderKitBundle\Persistence\AfterCommitMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(AfterCommitMiddlewarePass::class)]
final class AfterCommitMiddlewarePassTest extends TestCase
{
    #[Test]
    public function theMiddlewareFollowsTheBundleConnection(): void
    {
        $container = new ContainerBuilder();
        new AfterCommitMiddlewarePass()->process($container);
        $container->register(AfterCommitMiddleware::class)->addTag('doctrine.middleware');
        $container->setParameter(NowoPageBuilderKitExtension::ENTITY_MANAGER_PARAMETER, 'page_builder');
        $container->register('doctrine.orm.page_builder_entity_manager')->setArguments([new Reference('doctrine.dbal.pages_connection')]);
        new AfterCommitMiddlewarePass()->process($container);
        self::assertSame([['connection' => 'pages']], $container->getDefinition(AfterCommitMiddleware::class)->getTag('doctrine.middleware'));

        // No option: the default manager's connection, else the default connection.
        $container->setParameter(NowoPageBuilderKitExtension::ENTITY_MANAGER_PARAMETER, null);
        $container->setParameter('doctrine.default_connection', 'main');
        new AfterCommitMiddlewarePass()->process($container);
        self::assertSame([['connection' => 'main']], $container->getDefinition(AfterCommitMiddleware::class)->getTag('doctrine.middleware'));

        $bare = new ContainerBuilder();
        $bare->register(AfterCommitMiddleware::class)->addTag('doctrine.middleware');
        new AfterCommitMiddlewarePass()->process($bare);
        self::assertSame([['connection' => 'default']], $bare->getDefinition(AfterCommitMiddleware::class)->getTag('doctrine.middleware'));
    }
}
