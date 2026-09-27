<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use Nowo\PageBuilderKitBundle\Debug\NullPageBuilderKitTrace;
use Nowo\PageBuilderKitBundle\Debug\PageBuilderKitTraceInterface;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
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

    /**
     * @param iterable<GrapesTwigContextProviderInterface> $twigContextProviders
     */
    public function __construct(
        private BuilderPageRepositoryInterface $pageRepository,
        private DocumentNormalizer $documentNormalizer,
        private BuilderLocales $builderLocales,
        private WidgetTypeRegistry $widgetTypeRegistry,
        private PageBuilderProtection $protection,
        private WidgetPropsMerger $widgetPropsMerger,
        private GrapesDocumentSanitizer $grapesDocumentSanitizer = new GrapesDocumentSanitizer(),
        private GrapesTwigRenderer $grapesTwigRenderer = new GrapesTwigRenderer(),
        private PageSeoBuilder $pageSeoBuilder = new PageSeoBuilder(),
        iterable $twigContextProviders = [],
        ?PageBuilderKitTraceInterface $trace = null,
    ) {
        $providers = $twigContextProviders instanceof Traversable
            ? iterator_to_array($twigContextProviders, false)
            : $twigContextProviders;
        $this->twigContextProviders = array_values($providers);
        $this->trace                = $trace ?? new NullPageBuilderKitTrace();
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

        $base = [
            'pageKey' => $pageKey,
            'locale'  => $locale,
            'status'  => $page->getStatus()->value,
            'title'   => $title,
            'slug'    => $translation?->getSlug() ?? $pageKey,
            'seo'     => $this->pageSeoBuilder->build($translation, $pageKey, $locale, $title),
        ];

        $timings = [
            'load' => $this->msSince($t0, $tLoad),
        ];

        if ($this->documentNormalizer->isGrapesStructure($structure)) {
            $result = $base + $this->renderGrapes($page, $structure, $locale, $fallbackLocale, $base, $context, $timings);
        } else {
            $tClassic            = hrtime(true);
            $result              = $base + $this->renderClassic($document, $structure, $locale, $fallbackLocale);
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

        $tSanitize           = hrtime(true);
        $cssOut              = $this->grapesDocumentSanitizer->sanitizeCss($css);
        $timings['sanitize'] = $this->msSince($tSanitize);

        return [
            'engine'      => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'        => $twigResult['html'],
            'css'         => $cssOut,
            'sections'    => [],
            'twigApplied' => $twigResult['twigApplied'],
            'twigError'   => $twigResult['twigError'],
            'contextKeys' => $contextKeys,
            'timingsMs'   => $timings,
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

    /**
     * @param array<string, mixed> $structure
     *
     * @return array<string, mixed>
     */
    private function renderClassic(
        BuilderDocument $document,
        array $structure,
        string $locale,
        string $fallbackLocale,
    ): array {
        $localeProps   = $document->getLocaleDocument($locale)?->getWidgetProps() ?? [];
        $fallbackProps = $document->getLocaleDocument($fallbackLocale)?->getWidgetProps() ?? [];
        $mergedProps   = $this->widgetPropsMerger->mergePropsWithFallbackLocale(
            $localeProps,
            $fallbackProps,
            $locale,
            $fallbackLocale,
        );

        $renderedSections = [];
        foreach ($structure['sections'] as $section) {
            if (!is_array($section)) {
                continue; // @codeCoverageIgnore
            }

            $renderedColumns = [];
            foreach ($section['columns'] ?? [] as $column) {
                if (!is_array($column)) {
                    continue; // @codeCoverageIgnore
                }

                $renderedWidgets = [];
                foreach ($column['widgets'] ?? [] as $widget) {
                    $rendered = $this->renderWidgetNode($widget, $mergedProps);
                    if ($rendered !== null) {
                        $renderedWidgets[] = $rendered;
                    }
                }

                $renderedColumns[] = [
                    'id'         => is_string($column['id'] ?? null) ? $column['id'] : '',
                    'settings'   => is_array($column['settings'] ?? null) ? $column['settings'] : ['width' => 12],
                    'appearance' => is_array($column['settings'] ?? null) ? $column['settings'] : ['width' => 12],
                    'widgets'    => $renderedWidgets,
                ];
            }

            $renderedSections[] = [
                'id'         => $section['id'] ?? '',
                'settings'   => is_array($section['settings'] ?? null) ? $section['settings'] : [],
                'appearance' => is_array($section['settings'] ?? null) ? $section['settings'] : [],
                'columns'    => $renderedColumns,
            ];
        }

        return [
            'engine'   => 'classic',
            'sections' => $renderedSections,
        ];
    }

    /**
     * @param array<string, mixed> $mergedProps
     *
     * @return array<string, mixed>|null
     */
    private function renderWidgetNode(mixed $widget, array $mergedProps): ?array
    {
        if (!is_array($widget)) {
            return null; // @codeCoverageIgnore
        }

        $widgetId = $widget['id'] ?? null;
        $typeName = $widget['type'] ?? null;
        if (!is_string($widgetId) || !is_string($typeName) || !$this->widgetTypeRegistry->has($typeName)) {
            return null;
        }

        $widgetType  = $this->widgetTypeRegistry->get($typeName);
        $appearance  = is_array($widget['settings'] ?? null) ? $widget['settings'] : [];
        $storedProps = is_array($mergedProps[$widgetId] ?? null) ? $mergedProps[$widgetId] : [];
        $content     = $widgetType->sanitizeProps($storedProps, $this->protection);

        $children = [];
        if ($widgetType->allowsChildren()) {
            foreach ($widget['children'] ?? [] as $child) {
                $renderedChild = $this->renderWidgetNode($child, $mergedProps);
                if ($renderedChild !== null) {
                    $children[] = $renderedChild;
                }
            }
        }

        return [
            'id'         => $widgetId,
            'type'       => $typeName,
            'settings'   => $content,
            'appearance' => $appearance,
            'template'   => $widgetType->getPublicTemplate(),
            'children'   => $children,
        ];
    }

    private function msSince(int $start, ?int $end = null): float
    {
        $end ??= hrtime(true);

        return round(($end - $start) / 1_000_000, 3);
    }
}
