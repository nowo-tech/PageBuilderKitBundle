<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Security;

final class AllowAllPageBuilderKitAccessChecker implements PageBuilderKitAccessCheckerInterface
{
    public function canAccess(): bool
    {
        return true;
    }
}
