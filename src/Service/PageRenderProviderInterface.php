<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

interface PageRenderProviderInterface
{
    /**
     * @param array<string, mixed> $context Extra Twig variables for GrapesJS HTML (ignored for classic engine)
     *
     * @return array<string, mixed>
     */
    public function getRenderedTree(string $pageKey, ?string $locale = null, array $context = []): array;
}
