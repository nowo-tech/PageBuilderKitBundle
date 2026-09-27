<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Locale;

/**
 * Config-backed locale catalog for the page builder.
 *
 * Prefer injecting this service. Legacy static access is routed through
 * {@see BuilderLocalesLegacyBinding} (bound in {@see NowoPageBuilderKitBundle::boot()},
 * cleared via {@see BuilderLocalesLegacyBinding::reset()} / {@see NowoPageBuilderKitBundle\EventSubscriber\WorkerStateResetSubscriber}).
 */
final readonly class BuilderLocales
{
    /**
     * @param list<string> $locales
     */
    public function __construct(
        private string $defaultLocale,
        private array $locales,
    ) {
    }

    public function getDefault(): string
    {
        return $this->defaultLocale;
    }

    /** @return list<string> */
    public function getAll(): array
    {
        return $this->locales;
    }
}
