<?php

declare(strict_types=1);

namespace App\Demo;

use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTranslation;
use Nowo\PageBuilderKitBundle\Enum\PageStatus;
use Nowo\PageBuilderKitBundle\Service\DocumentService;

use function is_array;
use function is_string;
use function sprintf;
use function str_contains;

/**
 * Seeds / reseeds every demo use-case page.
 */
final class DemoPageSeeder
{
    public function __construct(
        private readonly DocumentService $documentService,
    ) {
    }

    public function ensureAll(): void
    {
        foreach (DemoUseCases::all() as $case) {
            $this->ensure($case['key']);
        }
    }

    public function ensure(string $pageKey): void
    {
        $case = DemoUseCases::byKey($pageKey);
        if ($case === null) {
            return;
        }

        $page = $this->documentService->loadPageByKey($pageKey);
        if ($page instanceof BuilderPage && !$this->needsReseed($page, $case['engine'], $pageKey)) {
            $this->applySeoDefaults($page, $case);
            $this->syncPublishState($page, $case['publish']);

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
            $this->documentService->saveDocument($page, [
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
            ], []);
        }

        $this->syncPublishState($page, $case['publish']);
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
