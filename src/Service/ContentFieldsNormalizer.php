<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use Nowo\PageBuilderKitBundle\Enum\ContentFieldType;

use function array_key_exists;
use function count;
use function explode;
use function filter_var;
use function is_array;
use function is_float;
use function is_int;
use function is_numeric;
use function is_scalar;
use function is_string;
use function max;
use function preg_match;
use function sprintf;
use function trim;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOLEAN;

/**
 * Normalizes structure.fields (schema) and structure.fieldValues (per-locale payloads).
 */
final readonly class ContentFieldsNormalizer
{
    /**
     * Practical “unlimited” nesting with a DoS safety cap.
     * Composites nest until this depth; deeper composites coerce to string.
     */
    public const int MAX_NESTING_DEPTH = 32;

    /**
     * @return list<array{
     *     key: string,
     *     type: string,
     *     label: string,
     *     labels: array<string, string>,
     *     required: bool,
     *     options: list<string>,
     *     default: mixed,
     *     fields: list<array<string, mixed>>,
     *     min: int|null,
     *     max: int|null,
     *     reference: string
     * }>
     */
    public function normalizeSchema(mixed $raw, int $depth = 0): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $out  = [];
        $seen = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $key = is_string($item['key'] ?? null) ? trim($item['key']) : '';
            if ($key === '' || !preg_match('/^[a-z][a-z0-9_]*$/', $key) || isset($seen[$key])) {
                continue;
            }

            $typeRaw = is_string($item['type'] ?? null) ? $item['type'] : ContentFieldType::String->value;
            $type    = ContentFieldType::tryFrom($typeRaw) ?? ContentFieldType::String;

            if ($type->isComposite()) {
                if ($depth >= self::MAX_NESTING_DEPTH) {
                    $type = ContentFieldType::String;
                }
            }

            $options = [];
            if (is_array($item['options'] ?? null)) {
                foreach ($item['options'] as $option) {
                    if (is_string($option) && $option !== '') {
                        $options[] = $option;
                    }
                }
            }

            $labels = $this->normalizeLabels($item['labels'] ?? null);
            $label  = is_string($item['label'] ?? null) && $item['label'] !== ''
                ? $item['label']
                : ($labels !== [] ? reset($labels) : $key);

            $nested = [];
            if ($type->isComposite() && $depth < self::MAX_NESTING_DEPTH) {
                $nested = $this->normalizeSchema($item['fields'] ?? [], $depth + 1);
            }

            $min = null;
            $max = null;
            if ($type === ContentFieldType::Repeater) {
                $min = $this->normalizeBound($item['min'] ?? null);
                $max = $this->normalizeBound($item['max'] ?? null);
                if ($min !== null && $max !== null && $max < $min) {
                    $max = $min;
                }
            }

            $reference = 'page';
            if ($type === ContentFieldType::Reference && is_string($item['reference'] ?? null) && $item['reference'] !== '') {
                $reference = $item['reference'];
            }

            $seen[$key] = true;
            $out[]      = [
                'key'       => $key,
                'type'      => $type->value,
                'label'     => $label,
                'labels'    => $labels,
                'required'  => (bool) ($item['required'] ?? false),
                'options'   => $options,
                'default'   => $item['default'] ?? null,
                'fields'    => $nested,
                'min'       => $min,
                'max'       => $max,
                'reference' => $reference,
            ];
        }

        return $out;
    }

    /**
     * Resolve display label for a locale (labels[locale] → labels[fallback] → label → key).
     *
     * @param array{key?: string, label?: string, labels?: array<string, string>, ...} $field
     */
    public function resolveLabel(array $field, string $locale, string $fallbackLocale): string
    {
        $key    = is_string($field['key'] ?? null) ? $field['key'] : '';
        $labels = is_array($field['labels'] ?? null) ? $field['labels'] : [];

        if (is_string($labels[$locale] ?? null) && $labels[$locale] !== '') {
            return $labels[$locale];
        }
        if (is_string($labels[$fallbackLocale] ?? null) && $labels[$fallbackLocale] !== '') {
            return $labels[$fallbackLocale];
        }
        if (is_string($field['label'] ?? null) && $field['label'] !== '') {
            return $field['label'];
        }

        return $key !== '' ? $key : 'field';
    }

    /**
     * @param array{key: string, type: string, label: string, labels: array<string, string>, required: bool, options: list<string>, default: mixed, fields?: list<array<string, mixed>>, min?: int|null, max?: int|null, reference?: string} $field
     * @param array{label?: string, labels?: array<string, string>}|null $definition
     *
     * @return array{key: string, type: string, label: string, labels: array<string, string>, required: bool, options: list<string>, default: mixed, fields: list<array<string, mixed>>, min: int|null, max: int|null, reference: string}
     */
    public function mergeLabels(array $field, ?array $definition, ?string $localeForSingularLabel = null): array
    {
        $field = $this->normalizeSchema([$field])[0] ?? $this->emptyFieldDef($field['key'] ?? 'field');

        if ($definition === null) {
            return $field;
        }

        $labels = $field['labels'];
        if (is_string($definition['label'] ?? null) && trim($definition['label']) !== '') {
            $text           = trim($definition['label']);
            $field['label'] = $text;
            if ($localeForSingularLabel !== null && $localeForSingularLabel !== '') {
                $labels[$localeForSingularLabel] = $text;
            }
        }
        if (is_array($definition['labels'] ?? null)) {
            foreach ($definition['labels'] as $locale => $text) {
                if (!is_string($locale) || $locale === '' || !is_string($text) || trim($text) === '') {
                    continue;
                }
                $labels[$locale] = trim($text);
            }
        }

        $field['labels'] = $labels;
        if ($field['label'] === '' || $field['label'] === $field['key']) {
            $field['label'] = $labels !== [] ? reset($labels) : $field['key'];
        }

        return $field;
    }

    /**
     * Parse compact subfield defs: "question:string,answer:text".
     *
     * @return list<array{key: string, type: string, label: string}>
     */
    public function parseSubfieldsSpec(string $spec): array
    {
        $items = [];
        foreach (explode(',', $spec) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            $parts = explode(':', $chunk, 2);
            $key   = trim($parts[0]);
            $type  = isset($parts[1]) ? trim($parts[1]) : ContentFieldType::String->value;
            if ($key === '') {
                continue;
            }
            $items[] = [
                'key'   => $key,
                'type'  => $type !== '' ? $type : ContentFieldType::String->value,
                'label' => $key,
            ];
        }

        return $this->normalizeSchema($items, 1);
    }

    /**
     * @param array<string, mixed> $structure
     * @param list<string> $locales
     *
     * @return list<array{locale: string, key: string, message: string}>
     */
    public function validateRequired(array $structure, array $locales): array
    {
        $schema = $this->normalizeSchema($structure['fields'] ?? []);
        $values = $this->normalizeValues($structure['fieldValues'] ?? [], $schema);
        $errors = [];

        foreach ($locales as $locale) {
            if (!is_string($locale) || $locale === '') {
                continue;
            }
            $bag = $values[$locale] ?? [];
            foreach ($schema as $field) {
                $key   = $field['key'];
                $value = $bag[$key] ?? $this->emptyValue($field['type']);
                if ($field['required'] && $this->isEmptyForRequired($field, $value)) {
                    $errors[] = [
                        'locale'  => $locale,
                        'key'     => $key,
                        'message' => sprintf('Required field "%s" is empty for locale "%s".', $key, $locale),
                    ];
                }
                if ($field['type'] === ContentFieldType::Repeater->value && is_array($value)) {
                    $count = count($value);
                    if ($field['min'] !== null && $count < $field['min']) {
                        $errors[] = [
                            'locale'  => $locale,
                            'key'     => $key,
                            'message' => sprintf(
                                'Field "%s" needs at least %d row(s) for locale "%s".',
                                $key,
                                $field['min'],
                                $locale,
                            ),
                        ];
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function normalizeLabels(mixed $rawLabels): array
    {
        $labels = [];
        if (!is_array($rawLabels)) {
            return $labels;
        }

        foreach ($rawLabels as $locale => $text) {
            if (!is_string($locale) || $locale === '' || !is_string($text)) {
                continue;
            }
            $text = trim($text);
            if ($text === '') {
                continue;
            }
            $labels[$locale] = $text;
        }

        return $labels;
    }

    private function normalizeBound(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_int($raw)) {
            return max(0, $raw);
        }
        if (is_string($raw) && is_numeric($raw)) {
            return max(0, (int) $raw);
        }

        return null;
    }

    /**
     * @param list<array{key: string, type: string, ...}> $schema
     *
     * @return array<string, array<string, mixed>>
     */
    public function normalizeValues(mixed $raw, array $schema): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $keys = [];
        foreach ($schema as $field) {
            $keys[$field['key']] = $field;
        }

        $out = [];
        foreach ($raw as $locale => $values) {
            if (!is_string($locale) || $locale === '' || !is_array($values)) {
                continue;
            }

            $localeOut = [];
            foreach ($values as $key => $value) {
                if (!is_string($key) || !isset($keys[$key])) {
                    continue;
                }
                $localeOut[$key] = $this->normalizeValue($keys[$key], $value);
            }
            $out[$locale] = $localeOut;
        }

        return $out;
    }

    /**
     * Resolve field bag for Twig: fields.{key} with locale fallback.
     *
     * @param array<string, mixed> $structure
     *
     * @return array<string, mixed>
     */
    public function resolveForLocale(array $structure, string $locale, string $fallbackLocale): array
    {
        $schema = $this->normalizeSchema($structure['fields'] ?? []);
        $values = $this->normalizeValues($structure['fieldValues'] ?? [], $schema);

        $localeValues   = $values[$locale] ?? [];
        $fallbackValues = $values[$fallbackLocale] ?? [];

        $resolved = [];
        foreach ($schema as $field) {
            $key = $field['key'];
            if (array_key_exists($key, $localeValues)) {
                $resolved[$key] = $localeValues[$key];
            } elseif (array_key_exists($key, $fallbackValues)) {
                $resolved[$key] = $fallbackValues[$key];
            } elseif ($field['default'] !== null) {
                $resolved[$key] = $this->normalizeValue($field, $field['default']);
            } else {
                $resolved[$key] = $this->emptyValue($field['type']);
            }
        }

        return $resolved;
    }

    /**
     * @param array<string, mixed> $structure
     * @param list<array<string, mixed>>|null $fields
     * @param array<string, array<string, mixed>>|null $fieldValues
     *
     * @return array<string, mixed>
     */
    public function applyToStructure(array $structure, ?array $fields = null, ?array $fieldValues = null): array
    {
        $schema = $fields !== null
            ? $this->normalizeSchema($fields)
            : $this->normalizeSchema($structure['fields'] ?? []);

        $structure['fields']      = $schema;
        $structure['fieldValues'] = $fieldValues !== null
            ? $this->normalizeValues($fieldValues, $schema)
            : $this->normalizeValues($structure['fieldValues'] ?? [], $schema);

        return $structure;
    }

    /**
     * Strip or clear content-field payload from a structure (template apply).
     *
     * @param array<string, mixed> $structure
     *
     * @return array<string, mixed>
     */
    public function applyTemplateFieldOptions(
        array $structure,
        bool $includeFieldSchema,
        bool $includeFieldValues,
    ): array {
        if (!$includeFieldSchema) {
            $structure['fields']      = [];
            $structure['fieldValues'] = [];

            return $structure;
        }

        $schema                   = $this->normalizeSchema($structure['fields'] ?? []);
        $structure['fields']      = $schema;
        $structure['fieldValues'] = $includeFieldValues
            ? $this->normalizeValues($structure['fieldValues'] ?? [], $schema)
            : [];

        return $structure;
    }

    /**
     * @param array{type: string, fields?: list<array<string, mixed>>, max?: int|null, ...} $field
     */
    private function normalizeValue(array $field, mixed $value): mixed
    {
        $type = $field['type'];

        return match ($type) {
            ContentFieldType::Bool->value      => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value,
            ContentFieldType::Number->value    => $this->normalizeNumber($value),
            ContentFieldType::Repeater->value  => $this->normalizeRepeaterValue($field, $value),
            ContentFieldType::Group->value     => $this->normalizeGroupValue($field, $value),
            ContentFieldType::Reference->value => $this->normalizeReferenceValue($value),
            ContentFieldType::Html->value,
            ContentFieldType::Richtext->value,
            ContentFieldType::Raw->value,
            ContentFieldType::Text->value,
            ContentFieldType::String->value,
            ContentFieldType::Url->value,
            ContentFieldType::Image->value,
            ContentFieldType::Icon->value,
            ContentFieldType::Select->value => is_scalar($value) || $value === null
                ? (string) ($value ?? '')
                : '',
            default => is_scalar($value) || $value === null ? (string) ($value ?? '') : '',
        };
    }

    /**
     * @param array{fields?: list<array<string, mixed>>, max?: int|null, ...} $field
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeRepeaterValue(array $field, mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $subSchema = $this->normalizeSchema($field['fields'] ?? [], 1);
        $rows      = [];
        foreach ($value as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = [];
            foreach ($subSchema as $sub) {
                if (array_key_exists($sub['key'], $row)) {
                    $normalized[$sub['key']] = $this->normalizeValue($sub, $row[$sub['key']]);
                } else {
                    $normalized[$sub['key']] = $this->emptyValue($sub['type']);
                }
            }
            if ($this->rowIsEmpty($normalized, $subSchema)) {
                continue;
            }
            $rows[] = $normalized;
            $max    = $field['max'] ?? null;
            if ($max !== null && count($rows) >= $max) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @param array{fields?: list<array<string, mixed>>, ...} $field
     *
     * @return array<string, mixed>
     */
    private function normalizeGroupValue(array $field, mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $subSchema  = $this->normalizeSchema($field['fields'] ?? [], 1);
        $normalized = [];
        foreach ($subSchema as $sub) {
            if (array_key_exists($sub['key'], $value)) {
                $normalized[$sub['key']] = $this->normalizeValue($sub, $value[$sub['key']]);
            } else {
                $normalized[$sub['key']] = $this->emptyValue($sub['type']);
            }
        }

        return $normalized;
    }

    private function normalizeReferenceValue(mixed $value): string
    {
        if (!is_scalar($value) && $value !== null) {
            return '';
        }
        $raw = trim((string) ($value ?? ''));
        if ($raw === '' || !preg_match('/^[a-z0-9_-]+$/', $raw)) {
            return '';
        }

        return $raw;
    }

    private function normalizeNumber(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (!is_scalar($value) && $value !== null) {
            return '';
        }
        $raw = trim((string) ($value ?? ''));
        if ($raw === '' || !is_numeric($raw)) {
            return '';
        }

        return $raw;
    }

    private function emptyValue(string $type): mixed
    {
        return match ($type) {
            ContentFieldType::Bool->value     => false,
            ContentFieldType::Number->value   => '',
            ContentFieldType::Repeater->value => [],
            ContentFieldType::Group->value    => [],
            default                           => '',
        };
    }

    /**
     * @param array{type: string, fields?: list<array<string, mixed>>, ...} $field
     */
    private function isEmptyForRequired(array $field, mixed $value): bool
    {
        return match ($field['type']) {
            ContentFieldType::Bool->value     => false,
            ContentFieldType::Repeater->value => !is_array($value) || $value === [],
            ContentFieldType::Group->value    => !is_array($value) || $this->groupIsEmpty($field, $value),
            default                           => $value === null || $value === '',
        };
    }

    /**
     * @param array{fields?: list<array<string, mixed>>, ...} $field
     * @param array<string, mixed> $value
     */
    private function groupIsEmpty(array $field, array $value): bool
    {
        $subSchema = $this->normalizeSchema($field['fields'] ?? [], 1);
        if ($subSchema === []) {
            return $value === [];
        }
        foreach ($subSchema as $sub) {
            $subValue = $value[$sub['key']] ?? $this->emptyValue($sub['type']);
            if (!$this->isEmptyForRequired($sub, $subValue)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<array{key: string, type: string, ...}> $subSchema
     */
    private function rowIsEmpty(array $row, array $subSchema): bool
    {
        foreach ($subSchema as $sub) {
            $value = $row[$sub['key']] ?? $this->emptyValue($sub['type']);
            if (!$this->isEmptyForRequired($sub, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{key: string, type: string, label: string, labels: array<string, string>, required: bool, options: list<string>, default: mixed, fields: list<array<string, mixed>>, min: int|null, max: int|null, reference: string}
     */
    private function emptyFieldDef(string $key): array
    {
        return [
            'key'       => $key,
            'type'      => ContentFieldType::String->value,
            'label'     => $key,
            'labels'    => [],
            'required'  => false,
            'options'   => [],
            'default'   => null,
            'fields'    => [],
            'min'       => null,
            'max'       => null,
            'reference' => 'page',
        ];
    }
}
