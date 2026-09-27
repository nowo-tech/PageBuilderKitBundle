<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\DataCollector;

use Nowo\PageBuilderKitBundle\Debug\PageBuilderKitTraceInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Throwable;

use function count;

/**
 * Web Profiler panel for Page Builder Kit render / public / admin activity.
 *
 * @phpstan-type CollectedData array{
 *     renders: list<array<string, mixed>>,
 *     publicOutcomes: list<array{pageKey: string, outcome: string, locale: ?string}>,
 *     adminActions: list<array{action: string, pageKey: string, revisionId: ?int}>,
 *     pathPrefix: string,
 *     revisionsEnabled: bool
 * }
 */
final class PageBuilderKitDataCollector extends DataCollector
{
    public const string NAME = 'nowo_page_builder_kit';

    public function __construct(
        private readonly PageBuilderKitTraceInterface $trace,
        private readonly string $pathPrefix = '/admin/page-builder',
        private readonly bool $revisionsEnabled = false,
    ) {
        $this->data = $this->emptyData();
    }

    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        unset($request, $response, $exception);

        // @igor-ignore - Symfony DataCollector snapshot; reset() clears between worker requests.
        $this->data = [
            'renders'          => $this->trace->getRenders(),
            'publicOutcomes'   => $this->trace->getPublicOutcomes(),
            'adminActions'     => $this->trace->getAdminActions(),
            'pathPrefix'       => $this->pathPrefix,
            'revisionsEnabled' => $this->revisionsEnabled,
        ];
    }

    public function reset(): void
    {
        $this->data = $this->emptyData();
        $this->trace->reset();
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /** @return list<array<string, mixed>> */
    public function getRenders(): array
    {
        /** @var list<array<string, mixed>> $renders */
        $renders = $this->data['renders'] ?? [];

        return $renders;
    }

    /** @return list<array{pageKey: string, outcome: string, locale: ?string}> */
    public function getPublicOutcomes(): array
    {
        /** @var list<array{pageKey: string, outcome: string, locale: ?string}> $outcomes */
        $outcomes = $this->data['publicOutcomes'] ?? [];

        return $outcomes;
    }

    /** @return list<array{action: string, pageKey: string, revisionId: ?int}> */
    public function getAdminActions(): array
    {
        /** @var list<array{action: string, pageKey: string, revisionId: ?int}> $actions */
        $actions = $this->data['adminActions'] ?? [];

        return $actions;
    }

    public function getPathPrefix(): string
    {
        return (string) ($this->data['pathPrefix'] ?? '/admin/page-builder');
    }

    public function isRevisionsEnabled(): bool
    {
        return (bool) ($this->data['revisionsEnabled'] ?? false);
    }

    public function getRenderCount(): int
    {
        return count($this->getRenders());
    }

    /**
     * @return CollectedData
     */
    private function emptyData(): array
    {
        return [
            'renders'          => [],
            'publicOutcomes'   => [],
            'adminActions'     => [],
            'pathPrefix'       => $this->pathPrefix,
            'revisionsEnabled' => $this->revisionsEnabled,
        ];
    }
}
