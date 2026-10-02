<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Security;

use Nowo\PageBuilderKitBundle\Enum\PageBuilderCapability;
use Nowo\PageBuilderKitBundle\Security\ConfigurablePageBuilderKitAccessChecker;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessCheckerInterface;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[CoversClass(PageBuilderKitAccessGuard::class)]
final class PageBuilderKitAccessGuardTest extends TestCase
{
    #[Test]
    public function assertLayoutPassesWhenGranted(): void
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);

        $guard = new PageBuilderKitAccessGuard(
            new ConfigurablePageBuilderKitAccessChecker(
                $authorizationChecker,
                ['ROLE_EDITOR'],
                ['ROLE_EDITOR'],
                ['ROLE_EDITOR'],
                ['ROLE_EDITOR'],
                ['ROLE_EDITOR'],
            ),
        );

        $guard->assertLayout();
        $guard->assertContent();
        self::assertTrue($guard->checker()->canAccess());
    }

    #[Test]
    public function assertPublishTemplatesAccessAndCan(): void
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);

        $guard = new PageBuilderKitAccessGuard(
            new ConfigurablePageBuilderKitAccessChecker(
                $authorizationChecker,
                ['ROLE_EDITOR'],
                ['ROLE_EDITOR'],
                ['ROLE_EDITOR'],
                ['ROLE_EDITOR'],
                ['ROLE_EDITOR'],
            ),
        );

        $guard->assertAccess();
        $guard->assertPublish();
        $guard->assertTemplates();
        $guard->assertCan('layout');
        $guard->assertCan(PageBuilderCapability::Content);
        self::assertTrue($guard->checker()->canPublish());
    }

    #[Test]
    public function assertCanDeniesUnknownCapability(): void
    {
        $denied = $this->createStub(PageBuilderKitAccessCheckerInterface::class);
        $denied->method('can')->willReturn(false);

        $guard = new PageBuilderKitAccessGuard($denied);

        $this->expectException(AccessDeniedException::class);
        $guard->assertCan('publish');
    }

    #[Test]
    public function assertAccessDeniesWhenCheckerRejects(): void
    {
        $denied = $this->createStub(PageBuilderKitAccessCheckerInterface::class);
        $denied->method('canAccess')->willReturn(false);

        $this->expectException(AccessDeniedException::class);
        (new PageBuilderKitAccessGuard($denied))->assertAccess();
    }

    #[Test]
    public function assertLayoutDeniesWhenCheckerRejects(): void
    {
        $denied = $this->createStub(PageBuilderKitAccessCheckerInterface::class);
        $denied->method('canLayout')->willReturn(false);

        $this->expectException(AccessDeniedException::class);
        (new PageBuilderKitAccessGuard($denied))->assertLayout();
    }

    #[Test]
    public function assertContentDeniesWhenCheckerRejects(): void
    {
        $denied = $this->createStub(PageBuilderKitAccessCheckerInterface::class);
        $denied->method('canContent')->willReturn(false);

        $this->expectException(AccessDeniedException::class);
        (new PageBuilderKitAccessGuard($denied))->assertContent();
    }

    #[Test]
    public function assertPublishDeniesWhenCheckerRejects(): void
    {
        $denied = $this->createStub(PageBuilderKitAccessCheckerInterface::class);
        $denied->method('canPublish')->willReturn(false);

        $this->expectException(AccessDeniedException::class);
        (new PageBuilderKitAccessGuard($denied))->assertPublish();
    }

    #[Test]
    public function assertTemplatesDeniesWhenCheckerRejects(): void
    {
        $denied = $this->createStub(PageBuilderKitAccessCheckerInterface::class);
        $denied->method('canTemplates')->willReturn(false);

        $this->expectException(AccessDeniedException::class);
        (new PageBuilderKitAccessGuard($denied))->assertTemplates();
    }

    #[Test]
    public function assertCanDeniesEnumCapability(): void
    {
        $denied = $this->createStub(PageBuilderKitAccessCheckerInterface::class);
        $denied->method('can')->willReturn(false);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Page builder capability "layout" required.');
        (new PageBuilderKitAccessGuard($denied))->assertCan(PageBuilderCapability::Layout);
    }
}
