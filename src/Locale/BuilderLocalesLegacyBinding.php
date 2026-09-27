<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Locale;

/**
 * Optional legacy binding cleared between requests (FrankenPHP worker safety).
 */
final class BuilderLocalesLegacyBinding
{
    private ?BuilderLocales $boundInstance = null;

    public function bind(BuilderLocales $instance): void
    {
        // @igor-ignore - Request-scoped legacy binding; cleared on kernel terminate.
        $this->boundInstance = $instance;
    }

    public function getBoundInstance(): ?BuilderLocales
    {
        return $this->boundInstance;
    }

    public function reset(): void
    {
        // @igor-ignore - Request-scoped legacy binding; cleared on kernel terminate.
        $this->boundInstance = null;
    }
}
