<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Security\Html;

use function preg_match;
use function preg_replace;
use function str_starts_with;
use function strtolower;

/**
 * URL scheme policy shared by the HTML sanitizer, the content field normalizer and the `pbk_safe_url` Twig filter.
 *
 * Browsers ignore ASCII whitespace / control characters inside a URL scheme (`java\tscript:`), so they are removed
 * before the scheme is checked. `javascript:` / `vbscript:` are refused; `data:` only for raster images in `src`.
 */
final class SafeUrl
{
    public static function isSafe(string $value, bool $imageSource = false): bool
    {
        $normalized = strtolower((string) preg_replace('/[\x00-\x20\x7f]+/', '', $value));
        if (str_starts_with($normalized, 'javascript:') || str_starts_with($normalized, 'vbscript:')) {
            return false;
        }
        if (str_starts_with($normalized, 'data:')) {
            return $imageSource && preg_match('#^data:image/(png|jpe?g|gif|webp|avif);#', $normalized) === 1;
        }

        return true;
    }

    /**
     * @return string The value when safe, an empty string otherwise
     */
    public static function filter(string $value, bool $imageSource = false): string
    {
        return self::isSafe($value, $imageSource) ? $value : '';
    }
}
