<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Security;

use Nowo\PageBuilderKitBundle\Security\AllowAllPageBuilderKitAccessChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AllowAllPageBuilderKitAccessChecker::class)]
final class AllowAllPageBuilderKitAccessCheckerTest extends TestCase
{
    #[Test]
    public function alwaysAllowsAccess(): void
    {
        self::assertTrue((new AllowAllPageBuilderKitAccessChecker())->canAccess());
    }
}
