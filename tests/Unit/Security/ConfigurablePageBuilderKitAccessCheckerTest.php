<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Security;

use Nowo\PageBuilderKitBundle\Security\ConfigurablePageBuilderKitAccessChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[CoversClass(ConfigurablePageBuilderKitAccessChecker::class)]
final class ConfigurablePageBuilderKitAccessCheckerTest extends TestCase
{
    #[Test]
    public function everyListEmptyDeniesEverything(): void
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects(self::never())->method('isGranted');

        $checker = new ConfigurablePageBuilderKitAccessChecker($authorizationChecker, [], [], [], [], []);

        self::assertFalse($checker->canAccess());
        self::assertFalse($checker->canLayout());
        self::assertFalse($checker->canContent());
        self::assertFalse($checker->canPublish());
        self::assertFalse($checker->canTemplates());
    }

    #[Test]
    public function emptyCapabilityListDoesNotGrantUsersWithoutAccessRole(): void
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(false);

        $checker = new ConfigurablePageBuilderKitAccessChecker(
            $authorizationChecker,
            accessRoles: ['ROLE_EDITOR'],
            layoutRoles: [],
            contentRoles: [],
            publishRoles: [],
            templatesRoles: [],
        );

        self::assertFalse($checker->canAccess());
        self::assertFalse($checker->canLayout());
        self::assertFalse($checker->canContent());
        self::assertFalse($checker->canPublish());
        self::assertFalse($checker->canTemplates());
    }

    #[Test]
    public function emptyCapabilityListLeavesAccessRolesShortcut(): void
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturnCallback(
            static fn (string $role): bool => $role === 'ROLE_EDITOR',
        );

        $checker = new ConfigurablePageBuilderKitAccessChecker(
            $authorizationChecker,
            accessRoles: ['ROLE_EDITOR'],
            layoutRoles: [],
        );

        self::assertTrue($checker->canLayout());
        self::assertTrue($checker->canAccess());
    }

    #[Test]
    public function grantsWhenAnyConfiguredRoleIsGranted(): void
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker
            ->method('isGranted')
            ->willReturnMap([
                ['ROLE_VIEWER', false],
                ['ROLE_EDITOR', true],
            ]);

        $checker = new ConfigurablePageBuilderKitAccessChecker(
            $authorizationChecker,
            ['ROLE_VIEWER', 'ROLE_EDITOR'],
            ['ROLE_EDITOR'],
            ['ROLE_EDITOR'],
            ['ROLE_EDITOR'],
            ['ROLE_EDITOR'],
        );

        self::assertTrue($checker->canAccess());
    }

    #[Test]
    public function deniesWhenNoConfiguredRoleIsGranted(): void
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker
            ->method('isGranted')
            ->willReturn(false);

        $checker = new ConfigurablePageBuilderKitAccessChecker(
            $authorizationChecker,
            ['ROLE_EDITOR'],
            ['ROLE_EDITOR'],
            ['ROLE_EDITOR'],
            ['ROLE_EDITOR'],
            ['ROLE_EDITOR'],
        );

        self::assertFalse($checker->canAccess());
    }
}
