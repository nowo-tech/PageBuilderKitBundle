<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Debug;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Collects page-builder events for the current request (FrankenPHP-safe via ResetInterface).
 */
final class PageBuilderKitTrace implements PageBuilderKitTraceInterface, ResetInterface
{
    /** @var list<array<string, mixed>> */
    private array $renders = [];

    /** @var list<array{pageKey: string, outcome: string, locale: ?string}> */
    private array $publicOutcomes = [];

    /** @var list<array{action: string, pageKey: string, revisionId: ?int}> */
    private array $adminActions = [];

    public function addRender(array $event): void
    {
        $this->renders[] = $event;
    }

    public function addPublicOutcome(string $pageKey, string $outcome, ?string $locale = null): void
    {
        $this->publicOutcomes[] = [
            'pageKey' => $pageKey,
            'outcome' => $outcome,
            'locale'  => $locale,
        ];
    }

    public function addAdminAction(string $action, string $pageKey, ?int $revisionId = null): void
    {
        $this->adminActions[] = [
            'action'     => $action,
            'pageKey'    => $pageKey,
            'revisionId' => $revisionId,
        ];
    }

    public function getRenders(): array
    {
        return $this->renders;
    }

    public function getPublicOutcomes(): array
    {
        return $this->publicOutcomes;
    }

    public function getAdminActions(): array
    {
        return $this->adminActions;
    }

    public function reset(): void
    {
        $this->renders        = [];
        $this->publicOutcomes = [];
        $this->adminActions   = [];
    }
}
