<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Widget\WidgetTypeRegistry;

use function is_array;
use function is_string;
use function sprintf;

/**
 * Engine-aware document structure validation (Grapes v2 vs classic v1).
 */
final readonly class DocumentStructureValidator
{
    public function __construct(
        private WidgetTypeRegistry $widgetTypeRegistry,
    ) {
    }

    /**
     * @param array<string, mixed> $structure
     */
    public function validate(array $structure, DocumentNormalizer $normalizer): void
    {
        if ($normalizer->isGrapesStructure($structure)) {
            $this->validateGrapes($structure);

            return;
        }

        $this->validateClassic($structure);
    }

    /**
     * @param array<string, mixed> $structure
     */
    private function validateGrapes(array $structure): void
    {
        if ((int) ($structure['version'] ?? 0) !== DocumentNormalizer::GRAPES_SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported document version.');
        }

        if (($structure['engine'] ?? null) !== DocumentNormalizer::ENGINE_GRAPESJS) {
            throw new InvalidArgumentException('GrapesJS documents require engine=grapesjs.');
        }

        if (!is_string($structure['html'] ?? null)) {
            throw new InvalidArgumentException('GrapesJS html must be a string.');
        }

        if (!is_string($structure['css'] ?? null)) {
            throw new InvalidArgumentException('GrapesJS css must be a string.');
        }

        if (!is_array($structure['grapes'] ?? null)) {
            throw new InvalidArgumentException('GrapesJS grapes project must be an object.');
        }

        $localeContent = $structure['localeContent'] ?? [];
        if (!is_array($localeContent)) {
            throw new InvalidArgumentException('GrapesJS localeContent must be an object.');
        }

        foreach ($localeContent as $locale => $content) {
            if (!is_string($locale) || $locale === '') {
                throw new InvalidArgumentException('Invalid locale key in localeContent.');
            }
            if (!is_array($content)) {
                throw new InvalidArgumentException(sprintf('localeContent.%s must be an object.', $locale));
            }
            if (!is_string($content['html'] ?? null) || !is_string($content['css'] ?? null) || !is_array($content['grapes'] ?? null)) {
                throw new InvalidArgumentException(sprintf('localeContent.%s must contain html, css and grapes.', $locale));
            }
        }
    }

    /**
     * @param array<string, mixed> $structure
     */
    private function validateClassic(array $structure): void
    {
        if ((int) ($structure['version'] ?? 0) !== DocumentNormalizer::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported document version.');
        }

        $sections = $structure['sections'] ?? null;
        if (!is_array($sections)) {
            throw new InvalidArgumentException('Document sections must be an array.');
        }

        foreach ($sections as $section) {
            if (!is_array($section)) {
                throw new InvalidArgumentException('Each section must be an object.');
            }

            $columns = $section['columns'] ?? null;
            if (!is_array($columns) || $columns === []) {
                throw new InvalidArgumentException('Each section must contain at least one column.');
            }

            foreach ($columns as $column) {
                if (!is_array($column)) {
                    throw new InvalidArgumentException('Each column must be an object.');
                }

                $widgets = $column['widgets'] ?? null;
                if (!is_array($widgets)) {
                    throw new InvalidArgumentException('Each column must contain a widgets array.');
                }

                foreach ($widgets as $widget) {
                    $this->validateWidgetNode($widget, 0);
                }
            }
        }
    }

    private function validateWidgetNode(mixed $widget, int $depth): void
    {
        if ($depth > DocumentNormalizer::MAX_NESTING_DEPTH) {
            throw new InvalidArgumentException('Widget nesting exceeds maximum depth.');
        }

        if (!is_array($widget)) {
            throw new InvalidArgumentException('Each widget must be an object.');
        }

        $type = $widget['type'] ?? null;
        if (!is_string($type) || $type === '') {
            throw new InvalidArgumentException('Widget type is required.');
        }

        if (!$this->widgetTypeRegistry->has($type)) {
            throw new InvalidArgumentException(sprintf('Unknown widget type "%s".', $type));
        }

        $children = $widget['children'] ?? [];
        if (!is_array($children)) {
            throw new InvalidArgumentException('Widget children must be an array.');
        }

        $allowsChildren = $this->widgetTypeRegistry->get($type)->allowsChildren();
        if (!$allowsChildren && $children !== []) {
            throw new InvalidArgumentException(sprintf('Widget type "%s" does not allow nested children.', $type));
        }

        foreach ($children as $child) {
            $this->validateWidgetNode($child, $depth + 1);
        }
    }
}
