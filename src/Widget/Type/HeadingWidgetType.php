<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Widget\Type;

use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Widget\AbstractWidgetType;

use function in_array;
use function is_string;

final class HeadingWidgetType extends AbstractWidgetType
{
    public function getType(): string
    {
        return 'heading';
    }

    public function getLabelKey(): string
    {
        return 'widget.heading.label';
    }

    public function defaultProps(): array
    {
        return [
            'text' => '',
            'tag'  => 'h2',
        ];
    }

    protected function sanitizeMergedProps(array $props, PageBuilderProtection $protection): array
    {
        $tag = is_string($props['tag'] ?? null) ? strtolower($props['tag']) : 'h2';
        if (!in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
            $tag = 'h2';
        }

        $props['tag']  = $tag;
        $props['text'] = is_string($props['text'] ?? null) ? trim($props['text']) : '';

        return $props;
    }
}
