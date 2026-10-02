<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Security;

use Nowo\PageBuilderKitBundle\Enum\PageBuilderCapability;

final class AllowAllPageBuilderKitAccessChecker implements PageBuilderKitAccessCheckerInterface
{
    public function canAccess(): bool
    {
        return true;
    }

    public function canLayout(): bool
    {
        return true;
    }

    public function canContent(): bool
    {
        return true;
    }

    public function canPublish(): bool
    {
        return true;
    }

    public function canTemplates(): bool
    {
        return true;
    }

    public function can(PageBuilderCapability|string $capability): bool
    {
        return true;
    }
}
