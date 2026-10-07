<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Util;

use function is_array;
use function is_string;
use function preg_match;
use function str_starts_with;
use function strtolower;
use function trim;

/**
 * Sanitize and apply `?section=` focus for Page Builder Content / canvas deep-links.
 */
final class ContentSectionFilter
{
    /**
     * Accept only lowercase slug prefixes used in content field keys / `data-pbk-section`.
     */
    public static function sanitize(mixed $raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }

        $section = strtolower(trim($raw));
        if ($section === '' || preg_match('/^[a-z0-9_]+$/', $section) !== 1) {
            return null;
        }

        return $section;
    }

    /**
     * Keep schema rows whose key equals the section or starts with "{section}_".
     *
     * @param list<array<string, mixed>> $schema
     *
     * @return list<array<string, mixed>>
     */
    public static function filterSchema(array $schema, string $section): array
    {
        $prefix = $section . '_';
        $out    = [];
        foreach ($schema as $field) {
            if (!is_array($field) || !isset($field['key']) || !is_string($field['key'])) {
                continue;
            }

            $key = $field['key'];
            if ($key === $section || str_starts_with($key, $prefix)) {
                $out[] = $field;
            }
        }

        return $out;
    }
}
