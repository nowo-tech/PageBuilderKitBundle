<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use Nowo\PageBuilderKitBundle\Debug\NullPageBuilderKitTrace;
use Nowo\PageBuilderKitBundle\Debug\PageBuilderKitTraceInterface;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Html\PublicHtmlHardener;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepositoryInterface;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Widget\WidgetTypeRegistry;
use RuntimeException;
use Traversable;

use function array_keys;
use function array_map;
use function array_values;
use function hrtime;
use function is_array;
use function is_string;
use function iterator_to_array;
use function round;
use function sprintf;

final readonly class PageRenderProvider implements PageRenderProviderInterface
{
    /** @var list<GrapesTwigContextProviderInterface> */
    private array $twigContextProviders;

    private PageBuilderKitTraceInterface $trace;

    private ContentFieldsNormalizer $contentFieldsNormalizer;

    private ContentFieldSlotReplacer $contentFieldSlotReplacer;

    private ClassicPageTreeBuilder $classicPageTreeBuilder;

    private PublicHtmlHardener $publicHtmlHardener;

    /**
     * @param iterable<GrapesTwigContextProviderInterface> $twigContextProviders
     */
    public function __construct(
        private BuilderPageRepositoryInterface $pageRepository,
        private DocumentNormalizer $documentNormalizer,
        private BuilderLocales $builderLocales,
        WidgetTypeRegistry $widgetTypeRegistry,
        PageBuilderProtection $protection,
        WidgetPropsMerger $widgetPropsMerger,
        private GrapesDocumentSanitizer $grapesDocumentSanitizer = new GrapesDocumentSanitizer(),
        private GrapesTwigRenderer $grapesTwigRenderer = new GrapesTwigRenderer(),
        private PageSeoBuilder $pageSeoBuilder = new PageSeoBuilder(),
        iterable $twigContextProviders = [],
        ?PageBuilderKitTraceInterface $trace = null,
        ?ContentFieldsNormalizer $contentFieldsNormalizer = null,
        ?ContentFieldSlotReplacer $contentFieldSlotReplacer = null,
        ?ClassicPageTreeBuilder $classicPageTreeBuilder = null,
        ?PublicHtmlHardener $publicHtmlHardener = null,
    ) {
        $providers = $twigContextProviders instanceof Traversable
            ? iterator_to_array($twigContextProviders, false)
            : $twigContextProviders;
        $this->twigContextProviders     = array_values($providers);
        $this->trace                    = $trace ?? new NullPageBuilderKitTrace();
        $this->contentFieldsNormalizer  = $contentFieldsNormalizer ?? new ContentFieldsNormalizer();
        $this->contentFieldSlotReplacer = $contentFieldSlotReplacer ?? new ContentFieldSlotReplacer();
        $this->classicPageTreeBuilder   = $classicPageTreeBuilder ?? new ClassicPageTreeBuilder(
            $widgetTypeRegistry,
            $protection,
            $widgetPropsMerger,
        );
        $this->publicHtmlHardener = $publicHtmlHardener ?? new PublicHtmlHardener();
    }

    /**
     * @param array<string, mixed> $context Extra Twig variables merged into GrapesJS HTML rendering
     *
     * @return array<string, mixed>
     */
    public function getRenderedTree(string $pageKey, ?string $locale = null, array $context = [], bool $draftPreview = false): array
    {
        $t0 = hrtime(true);

        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if (!$page instanceof BuilderPage) {
            throw new RuntimeException(sprintf('Unknown page "%s".', $pageKey));
        }

        $locale ??= $this->builderLocales->getDefault();
        $fallbackLocale = $this->builderLocales->getDefault();

        $document = $page->getDocument();
        if (!$document instanceof BuilderDocument) {
            throw new RuntimeException(sprintf('Page "%s" has no document.', $pageKey));
        }

        $tLoad = hrtime(true);

        $structure   = $this->documentNormalizer->normalize($document->getStructure());
        $translation = $page->getTranslation($locale) ?? $page->getTranslation($fallbackLocale);
        $title       = $translation?->getTitle() ?? $pageKey;
        $fields      = $this->contentFieldsNormalizer->resolveForLocale($structure, $locale, $fallbackLocale);

        $base = [
            'pageKey' => $pageKey,
            'locale'  => $locale,
            'status'  => $page->getStatus()->value,
            'title'   => $title,
            'slug'    => $translation?->getSlug() ?? $pageKey,
            'seo'     => $this->pageSeoBuilder->build($translation, $pageKey, $locale, $title),
            'fields'  => $fields,
        ];

        $timings = [
            'load' => $this->msSince($t0, $tLoad),
        ];

        if ($this->documentNormalizer->isGrapesStructure($structure)) {
            $result = $base + $this->renderGrapes($page, $structure, $locale, $fallbackLocale, $base, $context, $timings);
        } else {
            $tClassic            = hrtime(true);
            $result              = $base + $this->classicPageTreeBuilder->build($document, $structure, $locale, $fallbackLocale);
            $timings['classic']  = $this->msSince($tClassic);
            $result['timingsMs'] = $timings;
        }

        /** @var array<string, mixed> $seo */
        $seo = $result['seo'];
        /** @var list<string> $contextKeys */
        $contextKeys = array_values(
            array_map(
                static fn (mixed $key): string => (string) $key,
                is_array($result['contextKeys'] ?? null) ? array_values($result['contextKeys']) : [],
            ),
        );
        /** @var array<string, float> $eventTimings */
        $eventTimings = is_array($result['timingsMs'] ?? null) ? $result['timingsMs'] : $timings;

        // @igor-ignore - Request-scoped debug trace; ResetInterface clears between worker requests.
        $this->trace->addRender([
            'pageKey'      => $pageKey,
            'locale'       => $locale,
            'status'       => (string) $result['status'],
            'engine'       => (string) ($result['engine'] ?? 'classic'),
            'slug'         => (string) $result['slug'],
            'title'        => (string) $result['title'],
            'seoKeys'      => array_keys($seo),
            'fieldKeys'    => array_keys($fields),
            'twigApplied'  => (bool) ($result['twigApplied'] ?? false),
            'twigError'    => is_string($result['twigError'] ?? null) ? $result['twigError'] : null,
            'contextKeys'  => $contextKeys,
            'timingsMs'    => $eventTimings,
            'draftPreview' => $draftPreview,
        ]);

        unset($result['contextKeys'], $result['timingsMs']);

        return $result;
    }

    /**
     * @param array<string, mixed> $structure
     * @param array<string, mixed> $base
     * @param array<string, mixed> $extraContext
     * @param array<string, float> $timings
     *
     * @return array<string, mixed>
     */
    private function renderGrapes(
        BuilderPage $page,
        array $structure,
        string $locale,
        string $fallbackLocale,
        array $base,
        array $extraContext,
        array &$timings,
    ): array {
        $localeContent = is_array($structure['localeContent'] ?? null) ? $structure['localeContent'] : [];
        $content       = null;

        if (is_array($localeContent[$locale] ?? null)) {
            $content = $localeContent[$locale];
        } elseif (is_array($localeContent[$fallbackLocale] ?? null)) {
            $content = $localeContent[$fallbackLocale];
        }

        $html = is_string($content['html'] ?? null) ? $content['html'] : (is_string($structure['html'] ?? null) ? $structure['html'] : '');
        $css  = is_string($content['css'] ?? null) ? $content['css'] : (is_string($structure['css'] ?? null) ? $structure['css'] : '');

        $twigContext = $this->buildTwigContext($page, $locale, $base, $extraContext);
        $contextKeys = array_keys($twigContext);

        $tTwig           = hrtime(true);
        $twigResult      = $this->grapesTwigRenderer->render($html, $twigContext);
        $timings['twig'] = $this->msSince($tTwig);

        $schema  = $this->contentFieldsNormalizer->normalizeSchema($structure['fields'] ?? []);
        $htmlOut = $this->contentFieldSlotReplacer->replace(
            $twigResult['html'],
            is_array($base['fields'] ?? null) ? $base['fields'] : [],
            $schema,
        );
        $slotsApplied = $htmlOut !== $twigResult['html'];

        $tSanitize = hrtime(true);
        $cssOut    = $this->publicHtmlHardener->hardenCss($this->grapesDocumentSanitizer->sanitizeCss($css));
        // Always-on final gate (HTML5 parser): whatever Twig, slots or a fallback produced, the tree
        // handed to templates/hosts never carries executable markup.
        $htmlOut             = $this->publicHtmlHardener->harden($htmlOut);
        $timings['sanitize'] = $this->msSince($tSanitize);

        return [
            'engine'       => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'         => $htmlOut,
            'css'          => $cssOut,
            'sections'     => [],
            'twigApplied'  => $twigResult['twigApplied'],
            'twigError'    => $twigResult['twigError'],
            'slotsApplied' => $slotsApplied,
            'contextKeys'  => $contextKeys,
            'timingsMs'    => $timings,
        ];
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $extraContext
     *
     * @return array<string, mixed>
     */
    private function buildTwigContext(BuilderPage $page, string $locale, array $base, array $extraContext): array
    {
        $context = [
            'pageKey' => $base['pageKey'],
            'locale'  => $base['locale'],
            'status'  => $base['status'],
            'title'   => $base['title'],
            'slug'    => $base['slug'],
            'seo'     => $base['seo'] ?? [],
            'fields'  => is_array($base['fields'] ?? null) ? $base['fields'] : [],
            'page'    => [
                'key'    => $base['pageKey'],
                'locale' => $base['locale'],
                'status' => $base['status'],
                'title'  => $base['title'],
                'slug'   => $base['slug'],
            ],
        ];

        foreach ($this->twigContextProviders as $provider) {
            $context = [...$context, ...$provider->getContext($page, $locale)];
        }

        return [...$context, ...$extraContext];
    }

    private function msSince(int $start, ?int $end = null): float
    {
        $end ??= hrtime(true);

        return round(($end - $start) / 1_000_000, 3);
    }
}
