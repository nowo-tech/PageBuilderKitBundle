<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\DependencyInjection\Compiler;

use LogicException;
use Nowo\PageBuilderKitBundle\Controller\Admin\PageListController;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\CsrfProtectionPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class CsrfProtectionPassTest extends TestCase
{
    public function testNoopWithoutAdminControllers(): void
    {
        $container = new ContainerBuilder();

        new CsrfProtectionPass()->process($container);

        self::assertFalse($container->has('security.csrf.token_manager'));
    }

    public function testPassesWithTokenManagerAndFormExtension(): void
    {
        $container = $this->protectedContainer();
        $container->setParameter('form.type_extension.csrf.enabled', true);

        new CsrfProtectionPass()->process($container);

        self::assertTrue($container->hasDefinition('form.type_extension.csrf'));
    }

    public function testFailsWithoutTokenManager(): void
    {
        $container = $this->protectedContainer();
        $container->removeDefinition('security.csrf.token_manager');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires CSRF protection');

        new CsrfProtectionPass()->process($container);
    }

    public function testFailsWithoutFormCsrfExtension(): void
    {
        $container = $this->protectedContainer();
        $container->removeDefinition('form.type_extension.csrf');

        $this->expectException(LogicException::class);

        new CsrfProtectionPass()->process($container);
    }

    public function testFailsWhenFormCsrfDisabled(): void
    {
        $container = $this->protectedContainer();
        $container->setParameter('form.type_extension.csrf.enabled', false);

        $this->expectException(LogicException::class);

        new CsrfProtectionPass()->process($container);
    }

    private function protectedContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(PageListController::class, new Definition(PageListController::class));
        $container->setDefinition('security.csrf.token_manager', new Definition());
        $container->setDefinition('form.type_extension.csrf', new Definition());

        return $container;
    }
}
