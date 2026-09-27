<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Widget\Type;

use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Widget\AbstractWidgetType;

use function in_array;
use function is_string;

/**
 * Nestable container (Elementor-like): holds child widgets recursively.
 */
final class ContainerWidgetType extends AbstractWidgetType
{
    public function getType(): string
    {
        return 'container';
    }

    public function getLabelKey(): string
    {
        return 'widget.container.label';
    }

    public function allowsChildren(): bool
    {
        return true;
    }

    public function defaultProps(): array
    {
        return [
            'tag' => 'div',
        ];
    }

    protected function sanitizeMergedProps(array $props, PageBuilderProtection $protection): array
    {
        $tag = is_string($props['tag'] ?? null) ? strtolower($props['tag']) : 'div';
        if (!in_array($tag, ['div', 'section', 'article', 'aside', 'nav', 'header', 'footer', 'main'], true)) {
            $tag = 'div';
        }
        $props['tag'] = $tag;

        return $props;
    }
}
