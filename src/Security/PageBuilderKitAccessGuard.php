<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Security;

use Nowo\PageBuilderKitBundle\Enum\PageBuilderCapability;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use function sprintf;

/**
 * Thin assert/deny helper for controllers (RoutingKit PanelAccessGuard pattern).
 *
 * Prefer this in controllers; the admin subscriber still uses the checker directly.
 * Hosts that need custom logic implement {@see PageBuilderKitAccessCheckerInterface}
 * and set `security.access_checker`.
 */
final readonly class PageBuilderKitAccessGuard
{
    public function __construct(
        private PageBuilderKitAccessCheckerInterface $accessChecker,
    ) {
    }

    public function assertAccess(): void
    {
        if (!$this->accessChecker->canAccess()) {
            throw new AccessDeniedException('Page builder admin requires an authorized user.');
        }
    }

    public function assertLayout(): void
    {
        if (!$this->accessChecker->canLayout()) {
            throw new AccessDeniedException('Page builder layout capability required.');
        }
    }

    public function assertContent(): void
    {
        if (!$this->accessChecker->canContent()) {
            throw new AccessDeniedException('Page builder content capability required.');
        }
    }

    public function assertPublish(): void
    {
        if (!$this->accessChecker->canPublish()) {
            throw new AccessDeniedException('Page builder publish capability required.');
        }
    }

    public function assertTemplates(): void
    {
        if (!$this->accessChecker->canTemplates()) {
            throw new AccessDeniedException('Page builder templates capability required.');
        }
    }

    public function assertCan(PageBuilderCapability|string $capability): void
    {
        if (!$this->accessChecker->can($capability)) {
            $label = $capability instanceof PageBuilderCapability ? $capability->value : $capability;
            throw new AccessDeniedException(sprintf('Page builder capability "%s" required.', $label));
        }
    }

    public function checker(): PageBuilderKitAccessCheckerInterface
    {
        return $this->accessChecker;
    }
}
