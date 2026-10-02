<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Security;

use Nowo\PageBuilderKitBundle\Security\ConfigurablePageBuilderKitAccessChecker;
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
    public function assertLayoutDeniesWhenMissing(): void
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(false);

        $guard = new PageBuilderKitAccessGuard(
            new ConfigurablePageBuilderKitAccessChecker(
                $authorizationChecker,
                [],
                ['ROLE_PBK_LAYOUT'],
                ['ROLE_PBK_CONTENT'],
                ['ROLE_PBK_LAYOUT'],
                ['ROLE_PBK_LAYOUT'],
            ),
        );

        $this->expectException(AccessDeniedException::class);
        $guard->assertLayout();
    }
}
