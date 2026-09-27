<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Debug;

/**
 * No-op trace used when the Web Profiler collector is disabled.
 */
final class NullPageBuilderKitTrace implements PageBuilderKitTraceInterface
{
    public function addRender(array $event): void
    {
    }

    public function addPublicOutcome(string $pageKey, string $outcome, ?string $locale = null): void
    {
    }

    public function addAdminAction(string $action, string $pageKey, ?int $revisionId = null): void
    {
    }

    public function getRenders(): array
    {
        return [];
    }

    public function getPublicOutcomes(): array
    {
        return [];
    }

    public function getAdminActions(): array
    {
        return [];
    }

    public function reset(): void
    {
    }
}
