<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Debug;

/**
 * Request-scoped debug trace for the Web Profiler DataCollector.
 */
interface PageBuilderKitTraceInterface
{
    /**
     * @param array{
     *     pageKey: string,
     *     locale: string,
     *     status: string,
     *     engine: string,
     *     slug?: string,
     *     title?: string,
     *     seoKeys?: list<string>,
     *     fieldKeys?: list<string>,
     *     twigApplied?: bool,
     *     twigError?: string|null,
     *     contextKeys?: list<string>,
     *     timingsMs?: array<string, float>,
     *     draftPreview?: bool
     * } $event
     */
    public function addRender(array $event): void;

    /**
     * @param 'draft_preview'|'not_found_draft'|'not_found_missing'|'published' $outcome
     */
    public function addPublicOutcome(string $pageKey, string $outcome, ?string $locale = null): void;

    /**
     * @param 'duplicate'|'export'|'import'|'publish'|'restore'|'save'|'template_apply'|'template_export'|'template_save'|'templates_export_all'|'templates_import'|'unpublish' $action
     */
    public function addAdminAction(string $action, string $pageKey, ?int $revisionId = null): void;

    /**
     * @return list<array<string, mixed>>
     */
    public function getRenders(): array;

    /**
     * @return list<array{pageKey: string, outcome: string, locale: ?string}>
     */
    public function getPublicOutcomes(): array;

    /**
     * @return list<array{action: string, pageKey: string, revisionId: ?int}>
     */
    public function getAdminActions(): array;

    public function reset(): void;
}
