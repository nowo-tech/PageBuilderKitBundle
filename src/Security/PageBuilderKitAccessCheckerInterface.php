<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Security;

interface PageBuilderKitAccessCheckerInterface
{
    public function canAccess(): bool;
}
