<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Security;

use Nowo\PageBuilderKitBundle\Enum\PageBuilderCapability;

/**
 * Host-provided (or role-based) access for page-builder admin surfaces (REQ-UI-002).
 *
 * Configure via `security.*_roles` **or** point `security.access_checker` at a custom service
 * (same pattern as BlogKit / MarketingKit).
 */
interface PageBuilderKitAccessCheckerInterface
{
    /** Entry gate for any admin_page_builder_* route (list, etc.). */
    public function canAccess(): bool;

    public function canLayout(): bool;

    public function canContent(): bool;

    public function canPublish(): bool;

    public function canTemplates(): bool;

    /**
     * Convenience mapper for Twig / route tables (`layout` | `content` | `publish` | `templates`).
     */
    public function can(PageBuilderCapability|string $capability): bool;
}
