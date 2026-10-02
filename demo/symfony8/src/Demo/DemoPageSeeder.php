<?php

declare(strict_types=1);

namespace App\Demo;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageRevision;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTranslation;
use Nowo\PageBuilderKitBundle\Enum\PageStatus;
use Nowo\PageBuilderKitBundle\Service\DocumentService;
use Nowo\PageBuilderKitBundle\Service\PageRevisionStore;

use function array_key_exists;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;
use function preg_replace;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;

/**
 * Seeds / reseeds every demo use-case page (including labeled revision history).
 */
final class DemoPageSeeder
{
    private const string DEMO_REVISION_PREFIX = 'Demo version';

    public function __construct(
        private readonly DocumentService $documentService,
        private readonly PageRevisionStore $revisionStore,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function ensureAll(): void
    {
        foreach (DemoUseCases::all() as $case) {
            if (!DemoUseCases::shouldSeed($case)) {
                continue;
            }
            $this->ensure($case['key']);
        }
    }

    public function ensure(string $pageKey): void
    {
        $case = DemoUseCases::byKey($pageKey);
        if ($case === null || !DemoUseCases::shouldSeed($case)) {
            return;
        }

        $page = $this->documentService->loadPageByKey($pageKey);
        if ($page instanceof BuilderPage && !$this->needsReseed($page, $case['engine'], $pageKey)) {
            $this->applySeoDefaults($page, $case);
            $this->syncPublishState($page, $case['publish']);
            $this->ensureDemoRevisions($page, $case);

            return;
        }

        if (!$page instanceof BuilderPage) {
            $page = $this->documentService->createPage($pageKey, $case['title_en'], 'en');
        }

        $this->upsertTranslation($page, 'en', $case['title_en'], $pageKey);
        $this->upsertTranslation($page, 'es', $case['title_es'], $pageKey);
        $this->applySeoDefaults($page, $case);

        if ($case['engine'] === 'classic') {
            $payload = match ($pageKey) {
                'sections' => DemoSectionsSeed::document(),
                default => DemoClassicSeed::document(),
            };
            $this->documentService->saveDocument($page, $payload['structure'], $payload['widgetPropsByLocale']);
        } else {
            $en = DemoContentCatalog::contentFor($pageKey, 'en');
            $es = DemoContentCatalog::contentFor($pageKey, 'es');
            $structure = [
                'version'       => 2,
                'engine'        => 'grapesjs',
                'html'          => $en['html'],
                'css'           => $en['css'],
                'grapes'        => [],
                'localeContent' => [
                    'en' => ['html' => $en['html'], 'css' => $en['css'], 'grapes' => []],
                    'es' => ['html' => $es['html'], 'css' => $es['css'], 'grapes' => []],
                ],
                'sections' => [],
            ];
            if ($pageKey === 'fields') {
                $structure['fields']      = DemoContentFieldsSeed::schema();
                $structure['fieldValues'] = DemoContentFieldsSeed::values();
            }
            $this->documentService->saveDocument($page, $structure, []);
        }

        $this->syncPublishState($page, $case['publish']);
        $this->ensureDemoRevisions($page, $case);
    }

    /**
     * Ensure each demo page has labeled historical revisions for Versions / Diff UI.
     *
     * @param array<string, mixed> $case
     */
    private function ensureDemoRevisions(BuilderPage $page, array $case): void
    {
        if (!$this->revisionStore->isEnabled()) {
            return;
        }

        $labeled = 0;
        foreach ($this->revisionStore->listForPage($page) as $revision) {
            if (str_starts_with((string) $revision->getLabel(), self::DEMO_REVISION_PREFIX)) {
                ++$labeled;
            }
        }
        if ($labeled >= 2) {
            return;
        }

        $live = $this->revisionStore->extractLivePayload($page);
        if ($live === null) {
            return;
        }

        foreach ($this->buildDemoRevisionHistory($case, $live) as [$label, $structure, $props]) {
            $revision = (new BuilderPageRevision())
                ->setPage($page)
                ->setStructure($structure)
                ->setWidgetPropsByLocale($props)
                ->setLabel($label);
            $page->addRevision($revision);
            $this->entityManager->persist($revision);
        }

        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $case
     * @param array{structure: array<string, mixed>, widgetPropsByLocale: array<string, mixed>, fingerprint: string} $live
     *
     * @return list<array{0: string, 1: array<string, mixed>, 2: array<string, mixed>}>
     */
    private function buildDemoRevisionHistory(array $case, array $live): array
    {
        $engine = is_string($case['engine'] ?? null) ? $case['engine'] : 'grapesjs';
        $key    = is_string($case['key'] ?? null) ? $case['key'] : 'page';

        if ($engine === 'classic') {
            return [
                [
                    self::DEMO_REVISION_PREFIX . ' 1 — first classic draft',
                    $this->mutateClassicStructure($live['structure'], 'v1'),
                    $this->mutateClassicProps($live['widgetPropsByLocale'], 'First draft'),
                ],
                [
                    self::DEMO_REVISION_PREFIX . ' 2 — before publish polish',
                    $this->mutateClassicStructure($live['structure'], 'v2'),
                    $this->mutateClassicProps($live['widgetPropsByLocale'], 'Pre-publish'),
                ],
            ];
        }

        return [
            [
                self::DEMO_REVISION_PREFIX . ' 1 — early draft',
                $this->mutateGrapesStructure($live['structure'], $key, 'early'),
                $live['widgetPropsByLocale'],
            ],
            [
                self::DEMO_REVISION_PREFIX . ' 2 — copy refresh',
                $this->mutateGrapesStructure($live['structure'], $key, 'refresh'),
                $live['widgetPropsByLocale'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $structure
     *
     * @return array<string, mixed>
     */
    private function mutateGrapesStructure(array $structure, string $pageKey, string $stage): array
    {
        $marker = sprintf('data-pbk-demo-revision="%s"', $stage);
        $badge  = sprintf(
            '<p class="pbk-muted" %s>Demo revision snapshot · %s · %s</p>',
            $marker,
            $pageKey,
            $stage,
        );

        $html = $structure['html'] ?? '';
        if (is_string($html) && $html !== '') {
            $structure['html'] = $this->injectRevisionBanner($html, $badge);
        }

        $localeContent = $structure['localeContent'] ?? null;
        if (is_array($localeContent)) {
            foreach ($localeContent as $locale => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $localeHtml = $row['html'] ?? '';
                if (is_string($localeHtml) && $localeHtml !== '') {
                    $row['html']                 = $this->injectRevisionBanner($localeHtml, $badge);
                    $localeContent[$locale]      = $row;
                }
            }
            $structure['localeContent'] = $localeContent;
        }

        return $structure;
    }

    private function injectRevisionBanner(string $html, string $badge): string
    {
        if (str_contains($html, 'data-pbk-demo-revision=')) {
            $replaced = preg_replace(
                '/<p class="pbk-muted" data-pbk-demo-revision="[^"]*"[^>]*>.*?<\/p>/s',
                $badge,
                $html,
                1,
            );

            return is_string($replaced) ? $replaced : ($badge . $html);
        }

        if (preg_match('/(<div[^>]*data-pbk-demo-seed="[^"]*"[^>]*>)/', $html, $matches) === 1) {
            return str_replace($matches[1], $matches[1] . $badge, $html);
        }

        return $badge . $html;
    }

    /**
     * @param array<string, mixed> $structure
     *
     * @return array<string, mixed>
     */
    private function mutateClassicStructure(array $structure, string $suffix): array
    {
        $json = (string) json_encode($structure);
        $json = str_replace('demo-seed-v' . DemoUseCases::SEED_VERSION, 'demo-seed-v' . DemoUseCases::SEED_VERSION . '-' . $suffix, $json);
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : $structure;
    }

    /**
     * @param array<string, mixed> $props
     *
     * @return array<string, mixed>
     */
    private function mutateClassicProps(array $props, string $headingSuffix): array
    {
        foreach ($props as $locale => $localeProps) {
            if (!is_array($localeProps)) {
                continue;
            }
            foreach ($localeProps as $widgetId => $widgetProps) {
                if (!is_array($widgetProps) || !array_key_exists('text', $widgetProps) || !is_string($widgetProps['text'])) {
                    continue;
                }
                $props[$locale][$widgetId]['text'] = $widgetProps['text'] . ' · ' . $headingSuffix;
                break;
            }
        }

        return $props;
    }

    private function syncPublishState(BuilderPage $page, bool $publish): void
    {
        if ($publish) {
            if ($page->getStatus() !== PageStatus::Published) {
                $this->documentService->publish($page);
            }

            return;
        }

        if ($page->getStatus() === PageStatus::Published) {
            $this->documentService->unpublish($page);
        }
    }

    private function needsReseed(BuilderPage $page, string $engine, string $pageKey = ''): bool
    {
        $structure = $page->getDocument()?->getStructure() ?? [];
        $marker    = sprintf('data-pbk-demo-seed="%d"', DemoUseCases::SEED_VERSION);

        if ($engine === 'classic') {
            $sections = $structure['sections'] ?? null;
            if (!is_array($sections) || $sections === []) {
                return true;
            }
            $json = (string) json_encode($structure);
            if (!str_contains($json, 'demo-seed-v' . DemoUseCases::SEED_VERSION)) {
                return true;
            }
            // Multi-section demo must contain the hero section id prefix.
            if ($pageKey === 'sections' && !str_contains($json, 'sec-hero-')) {
                return true;
            }

            return false;
        }

        $html = $structure['html'] ?? '';
        if (!is_string($html) || $html === '') {
            return true;
        }

        return !str_contains($html, $marker);
    }

    private function upsertTranslation(BuilderPage $page, string $locale, string $title, string $slug): void
    {
        $translation = $page->getTranslation($locale);
        if ($translation === null) {
            $page->addTranslation(
                (new BuilderPageTranslation())
                    ->setLocale($locale)
                    ->setTitle($title)
                    ->setSlug($slug),
            );

            return;
        }

        $translation->setTitle($title);
        $translation->setSlug($slug);
    }

    /**
     * @param array<string, mixed> $case
     */
    private function applySeoDefaults(BuilderPage $page, array $case): void
    {
        if (($case['key'] ?? '') !== 'seo') {
            return;
        }

        $en = $page->getTranslation('en');
        if ($en !== null) {
            $en
                ->setMetaTitle('SEO & accessibility · Page Builder Kit')
                ->setMetaDescription('Demo page with meta title/description, Open Graph, robots, landmarks and accessible media.')
                ->setOgTitle('Ship SEO-ready pages with GrapesJS')
                ->setOgDescription('Page-level SEO fields plus A11y blocks in the canvas.')
                ->setOgImage('https://picsum.photos/seed/pbk-seo-og/1200/630')
                ->setCanonicalUrl('http://localhost:8137/p/seo')
                ->setRobots('index,follow');
        }

        $es = $page->getTranslation('es');
        if ($es !== null) {
            $es
                ->setMetaTitle('SEO y accesibilidad · Page Builder Kit')
                ->setMetaDescription('Página demo con meta title/description, Open Graph, robots, landmarks y media accesible.')
                ->setOgTitle('Páginas SEO-ready con GrapesJS')
                ->setOgDescription('Campos SEO a nivel de página más bloques A11y en el canvas.')
                ->setOgImage('https://picsum.photos/seed/pbk-seo-og/1200/630')
                ->setCanonicalUrl('http://localhost:8137/p/seo')
                ->setRobots('index,follow');
        }

        $this->documentService->flushPage($page);
    }
}
