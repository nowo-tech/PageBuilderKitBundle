<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use function array_key_exists;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function preg_match;
use function preg_replace;
use function str_contains;
use function str_starts_with;
use function strtolower;
use function trim;

/**
 * Normalizes Elementor-like appearance settings (Style + Advanced) on sections/columns/widgets.
 *
 * Structure (shared across locales, like Elementor):
 * - cssId / cssClasses
 * - attributes: list of {name, value}
 * - style: CSS properties map (desktop; optional tablet/mobile nested later)
 * - width (columns only, grid units 1–12)
 */
final class ElementAppearanceNormalizer
{
    /** @var list<string> */
    private const array ALLOWED_STYLE_KEYS = [
        'marginTop', 'marginRight', 'marginBottom', 'marginLeft',
        'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft',
        'backgroundColor', 'backgroundImage', 'backgroundSize', 'backgroundPosition', 'backgroundRepeat',
        'color', 'fontSize', 'fontWeight', 'fontFamily', 'lineHeight', 'letterSpacing', 'textAlign',
        'borderWidth', 'borderStyle', 'borderColor', 'borderRadius',
        'boxShadow', 'width', 'maxWidth', 'minWidth', 'height', 'minHeight', 'maxHeight',
        'opacity', 'zIndex', 'overflow', 'display', 'position', 'top', 'right', 'bottom', 'left',
        'gap', 'flexDirection', 'justifyContent', 'alignItems',
    ];

    /** @var list<string> */
    private const array BLOCKED_ATTR_NAMES = [
        'style', 'srcdoc', 'formaction', 'xlink:href',
    ];

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    public function normalize(array $settings): array
    {
        $out = [];

        if (isset($settings['width']) && (is_int($settings['width']) || is_numeric($settings['width']))) {
            $width = (int) $settings['width'];
            if ($width >= 1 && $width <= 12) {
                $out['width'] = $width;
            }
        }

        $cssId = $this->sanitizeCssId($settings['cssId'] ?? $settings['css_id'] ?? null);
        if ($cssId !== '') {
            $out['cssId'] = $cssId;
        }

        $cssClasses = $this->sanitizeCssClasses($settings['cssClasses'] ?? $settings['css_classes'] ?? $settings['class'] ?? null);
        if ($cssClasses !== '') {
            $out['cssClasses'] = $cssClasses;
        }

        $attributes = $this->normalizeAttributes($settings['attributes'] ?? null);
        if ($attributes !== []) {
            $out['attributes'] = $attributes;
        }

        $style = $this->normalizeStyleMap($settings['style'] ?? null);
        if ($style !== []) {
            $out['style'] = $style;
        }

        // Preserve responsive buckets if present (Phase 2+); sanitize each.
        foreach (['tablet', 'mobile'] as $breakpoint) {
            $bucket = $settings[$breakpoint] ?? null;
            if (!is_array($bucket)) {
                continue;
            }
            $normalizedBucket = $this->normalizeStyleMap($bucket['style'] ?? $bucket);
            if ($normalizedBucket !== []) {
                $out[$breakpoint] = ['style' => $normalizedBucket];
            }
        }

        return $out;
    }

    /**
     * Build inline CSS from a normalized style map.
     *
     * @param array<string, mixed> $style
     */
    public function toInlineCss(array $style): string
    {
        $parts = [];
        foreach ($style as $key => $value) {
            if (!is_string($key) || !is_string($value) || $value === '') {
                continue;
            }
            $cssProp = $this->camelToKebab($key);
            $parts[] = $cssProp . ':' . $value;
        }

        return implode(';', $parts);
    }

    /**
     * @param array<string, mixed> $appearance
     *
     * @return array<string, string>
     */
    public function toHtmlAttributes(array $appearance): array
    {
        $attrs = [];

        $cssId = $appearance['cssId'] ?? '';
        if (is_string($cssId) && $cssId !== '') {
            $attrs['id'] = $cssId;
        }

        $classes    = ['pbk-element'];
        $cssClasses = $appearance['cssClasses'] ?? '';
        if (is_string($cssClasses) && $cssClasses !== '') {
            $classes[] = $cssClasses;
        }
        $attrs['class'] = trim(implode(' ', $classes));

        $style = $appearance['style'] ?? [];
        if (is_array($style)) {
            $inline = $this->toInlineCss($style);
            if ($inline !== '') {
                $attrs['style'] = $inline;
            }
        }

        $custom = $appearance['attributes'] ?? [];
        if (is_array($custom)) {
            foreach ($custom as $pair) {
                if (!is_array($pair)) {
                    continue;
                }
                $name  = $pair['name'] ?? null;
                $value = $pair['value'] ?? '';
                if (!is_string($name) || $name === '' || !is_string($value)) {
                    continue;
                }
                if (array_key_exists($name, $attrs) && ($name === 'id' || $name === 'class' || $name === 'style')) {
                    continue;
                }
                $attrs[$name] = $value;
            }
        }

        return $attrs;
    }

    /**
     * @return list<array{name: string, value: string}>
     */
    private function normalizeAttributes(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $pair) {
            if (!is_array($pair)) {
                continue;
            }
            $name  = $pair['name'] ?? $pair['key'] ?? null;
            $value = $pair['value'] ?? '';
            if (!is_string($name) || !is_string($value)) {
                continue;
            }
            $name = strtolower(trim($name));
            if ($name === '' || !$this->isAllowedAttributeName($name)) {
                continue;
            }
            $value = $this->sanitizeAttributeValue($value);
            $out[] = ['name' => $name, 'value' => $value];
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function normalizeStyleMap(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach (self::ALLOWED_STYLE_KEYS as $key) {
            if (!array_key_exists($key, $raw)) {
                continue;
            }
            $value = $raw[$key];
            if (!is_string($value) && !is_numeric($value)) {
                continue;
            }
            $sanitized = $this->sanitizeCssValue((string) $value);
            if ($sanitized === '') {
                continue;
            }
            $out[$key] = $sanitized;
        }

        return $out;
    }

    private function sanitizeCssId(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        $value = trim($value);
        if ($value === '' || !preg_match('/^[A-Za-z][\w\-:.]*$/', $value)) {
            return '';
        }

        return $value;
    }

    private function sanitizeCssClasses(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        $parts = preg_split('/\s+/', trim($value)) ?: [];
        $clean = [];
        foreach ($parts as $part) {
            if ($part !== '' && preg_match('/^-?[_a-zA-Z]+[_a-zA-Z0-9-]*$/', $part)) {
                $clean[] = $part;
            }
        }

        return implode(' ', $clean);
    }

    private function isAllowedAttributeName(string $name): bool
    {
        if (str_starts_with($name, 'on')) {
            return false;
        }
        foreach (self::BLOCKED_ATTR_NAMES as $blocked) {
            if ($name === $blocked) {
                return false;
            }
        }
        // Elementor Advanced: data-*, aria-*, role, title, lang, dir, tabindex, rel, target (careful)
        if (str_starts_with($name, 'data-') || str_starts_with($name, 'aria-')) {
            return (bool) preg_match('/^(data|aria)-[a-z0-9_\-:]+$/', $name);
        }

        return (bool) preg_match('/^[a-z][a-z0-9_\-:]*$/', $name);
    }

    private function sanitizeAttributeValue(string $value): string
    {
        $value = trim($value);
        if (str_contains(strtolower($value), 'javascript:')) {
            return '';
        }

        return preg_replace('/[\x00-\x1F\x7F]/', '', $value) ?? '';
    }

    private function sanitizeCssValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $lower = strtolower($value);
        if (
            str_contains($lower, 'expression(')
            || str_contains($lower, 'javascript:')
            || str_contains($lower, 'behavior:')
            || str_contains($lower, '-moz-binding')
            || str_contains($lower, '</')
        ) {
            return '';
        }
        // Allow common CSS tokens: lengths, colors, keywords, urls(https|/)
        if (!preg_match('/^[a-zA-Z0-9#%.,\s_\/()\-+"\']+$/', $value)) {
            return '';
        }

        return $value;
    }

    private function camelToKebab(string $key): string
    {
        $kebab = preg_replace('/[A-Z]/', '-$0', $key) ?? $key;

        return strtolower($kebab);
    }
}
