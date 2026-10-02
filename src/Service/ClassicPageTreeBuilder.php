<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Widget\WidgetTypeRegistry;

use function is_array;
use function is_string;

/**
 * Builds the classic (schema v1) sections tree for public/admin render.
 */
final readonly class ClassicPageTreeBuilder
{
    public function __construct(
        private WidgetTypeRegistry $widgetTypeRegistry,
        private PageBuilderProtection $protection,
        private WidgetPropsMerger $widgetPropsMerger,
    ) {
    }

    /**
     * @param array<string, mixed> $structure
     *
     * @return array{engine: string, sections: list<array<string, mixed>>}
     */
    public function build(
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
        $sections         = is_array($structure['sections'] ?? null) ? $structure['sections'] : [];
        foreach ($sections as $section) {
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
