<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Widget\Type;

use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Widget\AbstractWidgetType;

use function is_string;

final class SpacerWidgetType extends AbstractWidgetType
{
    public function getType(): string
    {
        return 'spacer';
    }

    public function getLabelKey(): string
    {
        return 'widget.spacer.label';
    }

    public function defaultProps(): array
    {
        return [
            'height' => '1rem',
        ];
    }

    protected function sanitizeMergedProps(array $props, PageBuilderProtection $protection): array
    {
        $props['height'] = is_string($props['height'] ?? null) && $props['height'] !== ''
            ? $props['height']
            : '1rem';

        return $props;
    }
}
