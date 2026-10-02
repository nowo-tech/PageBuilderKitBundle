<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Security;

use Nowo\PageBuilderKitBundle\Enum\PageBuilderCapability;
use Nowo\PageBuilderKitBundle\Security\AllowAllPageBuilderKitAccessChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AllowAllPageBuilderKitAccessChecker::class)]
final class AllowAllPageBuilderKitAccessCheckerTest extends TestCase
{
    #[Test]
    public function alwaysAllowsEveryCapability(): void
    {
        $checker = new AllowAllPageBuilderKitAccessChecker();

        self::assertTrue($checker->canAccess());
        self::assertTrue($checker->canLayout());
        self::assertTrue($checker->canContent());
        self::assertTrue($checker->canPublish());
        self::assertTrue($checker->canTemplates());
        self::assertTrue($checker->can(PageBuilderCapability::Layout));
        self::assertTrue($checker->can('content'));
    }
}
