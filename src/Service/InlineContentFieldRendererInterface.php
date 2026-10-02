<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

interface InlineContentFieldRendererInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function render(string $pageKey, string $fieldKey, array $options = []): string;
}
