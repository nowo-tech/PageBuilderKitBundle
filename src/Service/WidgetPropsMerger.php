<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use function is_array;
use function is_string;

final class WidgetPropsMerger
{
    /**
     * @param array<string, mixed> $localeWidgetProps
     * @param array<string, mixed> $fallbackWidgetProps
     *
     * @return array<string, mixed>
     */
    public function mergePropsWithFallbackLocale(
        array $localeWidgetProps,
        array $fallbackWidgetProps,
        string $locale,
        string $fallbackLocale,
    ): array {
        if ($locale === $fallbackLocale) {
            return $localeWidgetProps;
        }

        $merged = $fallbackWidgetProps;
        foreach ($localeWidgetProps as $widgetId => $props) {
            if (!is_string($widgetId) || !is_array($props)) {
                continue;
            }

            $base              = is_array($merged[$widgetId] ?? null) ? $merged[$widgetId] : [];
            $merged[$widgetId] = array_merge($base, $props);
        }

        return $merged;
    }
}
