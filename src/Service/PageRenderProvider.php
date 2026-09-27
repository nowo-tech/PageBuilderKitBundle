<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepositoryInterface;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Widget\WidgetTypeRegistry;
use RuntimeException;
use Traversable;

use function is_array;
use function is_string;
use function iterator_to_array;
use function sprintf;

final class PageRenderProvider implements PageRenderProviderInterface
{
    /** @var list<GrapesTwigContextProviderInterface> */
    private readonly array $twigContextProviders;

    /**
     * @param iterable<GrapesTwigContextProviderInterface> $twigContextProviders
     */
    public function __construct(
        private readonly BuilderPageRepositoryInterface $pageRepository,
        private readonly DocumentNormalizer $documentNormalizer,
        private readonly BuilderLocales $builderLocales,
        private readonly WidgetTypeRegistry $widgetTypeRegistry,
        private readonly PageBuilderProtection $protection,
        private readonly WidgetPropsMerger $widgetPropsMerger,
        private readonly GrapesDocumentSanitizer $grapesDocumentSanitizer = new GrapesDocumentSanitizer(),
        private readonly GrapesTwigRenderer $grapesTwigRenderer = new GrapesTwigRenderer(),
        private readonly PageSeoBuilder $pageSeoBuilder = new PageSeoBuilder(),
        iterable $twigContextProviders = [],
    ) {
        $providers = $twigContextProviders instanceof Traversable
            ? iterator_to_array($twigContextProviders, false)
            : $twigContextProviders;
        $this->twigContextProviders = array_values($providers);
    }

    /**
     * @param array<string, mixed> $context Extra Twig variables merged into GrapesJS HTML rendering
     *
     * @return array<string, mixed>
     */
    public function getRenderedTree(string $pageKey, ?string $locale = null, array $context = []): array
    {
        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if (!$page instanceof BuilderPage) {
            throw new RuntimeException(sprintf('Unknown page "%s".', $pageKey));
        }

        $locale         = $locale ?? $this->builderLocales->getDefault();
        $fallbackLocale = $this->builderLocales->getDefault();

        $document = $page->getDocument();
        if (!$document instanceof BuilderDocument) {
            throw new RuntimeException(sprintf('Page "%s" has no document.', $pageKey));
        }

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

        if ($this->documentNormalizer->isGrapesStructure($structure)) {
            return $base + $this->renderGrapes($page, $structure, $locale, $fallbackLocale, $base, $context);
        }

        return $base + $this->renderClassic($document, $structure, $locale, $fallbackLocale);
    }

    /**
     * @param array<string, mixed> $structure
     * @param array<string, mixed> $base
     * @param array<string, mixed> $extraContext
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
        $twigResult  = $this->grapesTwigRenderer->render($html, $twigContext);

        return [
            'engine'      => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'        => $twigResult['html'],
            'css'         => $this->grapesDocumentSanitizer->sanitizeCss($css),
            'sections'    => [],
            'twigApplied' => $twigResult['twigApplied'],
            'twigError'   => $twigResult['twigError'],
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
}
