<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Security;

use Nowo\PageBuilderKitBundle\Enum\PageBuilderCapability;
use Nowo\PageBuilderKitBundle\Security\ConfigurablePageBuilderKitAccessChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[CoversClass(ConfigurablePageBuilderKitAccessChecker::class)]
final class ConfigurablePageBuilderKitAccessCheckerCapabilitiesTest extends TestCase
{
    #[Test]
    public function accessRolesShortcutGrantsEveryCapability(): void
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturnMap([
            ['ROLE_ADMIN', true],
            ['ROLE_PBK_LAYOUT', false],
            ['ROLE_PBK_CONTENT', false],
        ]);

        $checker = new ConfigurablePageBuilderKitAccessChecker(
            $authorizationChecker,
            accessRoles: ['ROLE_ADMIN'],
            layoutRoles: ['ROLE_PBK_LAYOUT'],
            contentRoles: ['ROLE_PBK_CONTENT'],
            publishRoles: ['ROLE_PBK_LAYOUT'],
            templatesRoles: ['ROLE_PBK_LAYOUT'],
        );

        self::assertTrue($checker->canAccess());
        self::assertTrue($checker->canLayout());
        self::assertTrue($checker->canContent());
        self::assertTrue($checker->canPublish());
        self::assertTrue($checker->canTemplates());
        self::assertTrue($checker->can(PageBuilderCapability::Layout));
    }

    #[Test]
    public function contentOnlyRoleDoesNotGrantLayout(): void
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturnCallback(
            static fn (string $role): bool => $role === 'ROLE_PBK_CONTENT',
        );

        $checker = new ConfigurablePageBuilderKitAccessChecker(
            $authorizationChecker,
            accessRoles: [],
            layoutRoles: ['ROLE_PBK_LAYOUT'],
            contentRoles: ['ROLE_PBK_CONTENT'],
            publishRoles: ['ROLE_PBK_LAYOUT'],
            templatesRoles: ['ROLE_PBK_LAYOUT'],
        );

        self::assertTrue($checker->canAccess());
        self::assertFalse($checker->canLayout());
        self::assertTrue($checker->canContent());
        self::assertFalse($checker->canPublish());
        self::assertFalse($checker->can('templates'));
    }
}
