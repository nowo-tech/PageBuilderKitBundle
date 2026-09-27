<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use Symfony\Component\Uid\Uuid;

use function is_array;
use function is_string;

final readonly class DocumentNormalizer
{
    /** Classic section/column/widget tree (legacy). */
    public const int SCHEMA_VERSION = 1;

    /** GrapesJS project + exported HTML/CSS. */
    public const int GRAPES_SCHEMA_VERSION = 2;

    public const string ENGINE_GRAPESJS = 'grapesjs';

    public const int MAX_NESTING_DEPTH = 12;

    public function __construct(
        private ElementAppearanceNormalizer $appearanceNormalizer = new ElementAppearanceNormalizer(),
    ) {
    }

    /** @return array<string, mixed> */
    public function emptyStructure(): array
    {
        return [
            'version'       => self::GRAPES_SCHEMA_VERSION,
            'engine'        => self::ENGINE_GRAPESJS,
            'html'          => '',
            'css'           => '',
            'grapes'        => [],
            'localeContent' => [],
            'sections'      => [],
        ];
    }

    /**
     * @param array<string, mixed> $structure
     */
    public function isGrapesStructure(array $structure): bool
    {
        if (($structure['engine'] ?? null) === self::ENGINE_GRAPESJS) {
            return true;
        }

        return (int) ($structure['version'] ?? 0) === self::GRAPES_SCHEMA_VERSION;
    }

    /**
     * @param array<string, mixed> $structure
     *
     * @return array<string, mixed>
     */
    public function normalize(array $structure): array
    {
        if ($this->isGrapesStructure($structure)) {
            return $this->normalizeGrapes($structure);
        }

        return $this->normalizeClassic($structure);
    }

    /**
     * @param array<string, mixed> $structure
     *
     * @return array<string, mixed>
     */
    private function normalizeGrapes(array $structure): array
    {
        $html   = is_string($structure['html'] ?? null) ? $structure['html'] : '';
        $css    = is_string($structure['css'] ?? null) ? $structure['css'] : '';
        $grapes = is_array($structure['grapes'] ?? null) ? $structure['grapes'] : [];

        $localeContent = [];
        $rawLocales    = $structure['localeContent'] ?? [];
        if (is_array($rawLocales)) {
            foreach ($rawLocales as $locale => $content) {
                if (!is_string($locale) || $locale === '' || !is_array($content)) {
                    continue;
                }
                $localeContent[$locale] = [
                    'html'   => is_string($content['html'] ?? null) ? $content['html'] : '',
                    'css'    => is_string($content['css'] ?? null) ? $content['css'] : '',
                    'grapes' => is_array($content['grapes'] ?? null) ? $content['grapes'] : [],
                ];
            }
        }

        return [
            'version'       => self::GRAPES_SCHEMA_VERSION,
            'engine'        => self::ENGINE_GRAPESJS,
            'html'          => $html,
            'css'           => $css,
            'grapes'        => $grapes,
            'localeContent' => $localeContent,
            'sections'      => [],
        ];
    }

    /**
     * @param array<string, mixed> $structure
     *
     * @return array<string, mixed>
     */
    private function normalizeClassic(array $structure): array
    {
        $version = (int) ($structure['version'] ?? self::SCHEMA_VERSION);
        if ($version !== self::SCHEMA_VERSION) {
            $structure['version'] = self::SCHEMA_VERSION;
        }

        $sections = $structure['sections'] ?? [];
        if (!is_array($sections)) {
            $sections = [];
        }

        $normalizedSections = [];
        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }

            $sectionId = $this->stringOrUuid($section['id'] ?? null);
            $settings  = $this->appearanceNormalizer->normalize(
                is_array($section['settings'] ?? null) ? $section['settings'] : [],
            );

            $columns = $section['columns'] ?? null;
            if (!is_array($columns) || $columns === []) {
                $columns = [[
                    'id'       => Uuid::v4()->toRfc4122(),
                    'settings' => ['width' => 12],
                    'widgets'  => [],
                ]];
            }

            $normalizedColumns = [];
            foreach ($columns as $column) {
                if (!is_array($column)) {
                    continue;
                }

                $columnSettings = $this->appearanceNormalizer->normalize(
                    is_array($column['settings'] ?? null) ? $column['settings'] : [],
                );
                $columnSettings['width'] ??= 12;

                $widgets = $column['widgets'] ?? [];
                if (!is_array($widgets)) {
                    $widgets = [];
                }

                $normalizedWidgets = [];
                foreach ($widgets as $widget) {
                    $normalized = $this->normalizeWidget($widget, 0);
                    if ($normalized !== null) {
                        $normalizedWidgets[] = $normalized;
                    }
                }

                $normalizedColumns[] = [
                    'id'       => $this->stringOrUuid($column['id'] ?? null),
                    'settings' => $columnSettings,
                    'widgets'  => $normalizedWidgets,
                ];
            }

            if ($normalizedColumns === []) {
                $normalizedColumns[] = [
                    'id'       => Uuid::v4()->toRfc4122(),
                    'settings' => ['width' => 12],
                    'widgets'  => [],
                ];
            }

            $normalizedSections[] = [
                'id'       => $sectionId,
                'settings' => $settings,
                'columns'  => $normalizedColumns,
            ];
        }

        return [
            'version'  => self::SCHEMA_VERSION,
            'sections' => $normalizedSections,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function normalizeWidget(mixed $widget, int $depth): ?array
    {
        if (!is_array($widget) || $depth > self::MAX_NESTING_DEPTH) {
            return null;
        }

        $widgetType = is_string($widget['type'] ?? null) ? $widget['type'] : '';
        if ($widgetType === '') {
            return null;
        }

        $normalized = [
            'id'       => $this->stringOrUuid($widget['id'] ?? null),
            'type'     => $widgetType,
            'settings' => $this->appearanceNormalizer->normalize(
                is_array($widget['settings'] ?? null) ? $widget['settings'] : [],
            ),
            'children' => [],
        ];

        $children = $widget['children'] ?? [];
        if (is_array($children)) {
            foreach ($children as $child) {
                $normalizedChild = $this->normalizeWidget($child, $depth + 1);
                if ($normalizedChild !== null) {
                    $normalized['children'][] = $normalizedChild;
                }
            }
        }

        return $normalized;
    }

    private function stringOrUuid(mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return Uuid::v4()->toRfc4122();
    }
}
