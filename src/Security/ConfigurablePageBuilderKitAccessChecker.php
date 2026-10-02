<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Security;

use Nowo\PageBuilderKitBundle\Enum\PageBuilderCapability;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use function is_string;

/**
 * Role-based access driven by nowo_page_builder_kit.security.*_roles (BlogKit-style).
 *
 * `access_roles` is a shortcut that grants every capability when held.
 * Each `*_roles` list is OR'd with that shortcut; an empty `*_roles` list allows
 * that capability for anyone who already passed `access_roles`, or for everyone
 * when `access_roles` is also empty (same emptyAllows semantics as BlogKit).
 */
final readonly class ConfigurablePageBuilderKitAccessChecker implements PageBuilderKitAccessCheckerInterface
{
    /**
     * @param list<string> $accessRoles
     * @param list<string> $layoutRoles
     * @param list<string> $contentRoles
     * @param list<string> $publishRoles
     * @param list<string> $templatesRoles
     */
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private array $accessRoles,
        private array $layoutRoles = [],
        private array $contentRoles = [],
        private array $publishRoles = [],
        private array $templatesRoles = [],
    ) {
    }

    public function canAccess(): bool
    {
        return $this->canLayout()
            || $this->canContent()
            || $this->canPublish()
            || $this->canTemplates();
    }

    public function canLayout(): bool
    {
        return $this->grantedAny($this->accessRoles, false)
            || $this->grantedAny($this->layoutRoles, true);
    }

    public function canContent(): bool
    {
        return $this->grantedAny($this->accessRoles, false)
            || $this->grantedAny($this->contentRoles, true)
            || $this->canLayout();
    }

    public function canPublish(): bool
    {
        return $this->grantedAny($this->accessRoles, false)
            || $this->grantedAny($this->publishRoles, true)
            || $this->canLayout();
    }

    public function canTemplates(): bool
    {
        return $this->grantedAny($this->accessRoles, false)
            || $this->grantedAny($this->templatesRoles, true)
            || $this->canLayout();
    }

    public function can(PageBuilderCapability|string $capability): bool
    {
        $key = $capability instanceof PageBuilderCapability
            ? $capability->value
            : $capability;

        if (!is_string($key) || $key === '') {
            return false;
        }

        return match ($key) {
            PageBuilderCapability::Layout->value    => $this->canLayout(),
            PageBuilderCapability::Content->value   => $this->canContent(),
            PageBuilderCapability::Publish->value   => $this->canPublish(),
            PageBuilderCapability::Templates->value => $this->canTemplates(),
            default                                 => false,
        };
    }

    /**
     * @param list<string> $roles
     */
    private function grantedAny(array $roles, bool $emptyAllows): bool
    {
        if ($roles === []) {
            return $emptyAllows;
        }

        foreach ($roles as $role) {
            if ($this->authorizationChecker->isGranted($role)) {
                return true;
            }
        }

        return false;
    }
}
