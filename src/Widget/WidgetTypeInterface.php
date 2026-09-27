<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Widget;

use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;

interface WidgetTypeInterface
{
    public function getType(): string;

    public function getLabelKey(): string;

    /** @return array<string, mixed> */
    public function defaultProps(): array;

    /**
     * @param array<string, mixed> $props
     *
     * @return array<string, mixed>
     */
    public function sanitizeProps(array $props, PageBuilderProtection $protection): array;

    public function getPublicTemplate(): string;

    /**
     * Whether this widget may contain nested child widgets (Elementor-like containers).
     */
    public function allowsChildren(): bool;
}
