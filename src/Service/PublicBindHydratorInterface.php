<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

interface PublicBindHydratorInterface
{
    /**
     * Wrap `<span data-pbk-bind="key">…</span>` slots with inline-edit controls for editors.
     * Visitors (no `content` capability) get the HTML back untouched.
     */
    public function hydrate(string $html, string $pageKey): string;
}
