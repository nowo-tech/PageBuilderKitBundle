<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Security;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final readonly class ConfigurablePageBuilderKitAccessChecker implements PageBuilderKitAccessCheckerInterface
{
    /**
     * @param list<string> $accessRoles
     */
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private array $accessRoles,
    ) {
    }

    public function canAccess(): bool
    {
        if ($this->accessRoles === []) {
            return true;
        }

        foreach ($this->accessRoles as $role) {
            if ($this->authorizationChecker->isGranted($role)) {
                return true;
            }
        }

        return false;
    }
}
