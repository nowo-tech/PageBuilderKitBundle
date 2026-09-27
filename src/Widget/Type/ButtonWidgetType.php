<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Widget\Type;

use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Widget\AbstractWidgetType;

use function in_array;
use function is_string;

final class ButtonWidgetType extends AbstractWidgetType
{
    public function getType(): string
    {
        return 'button';
    }

    public function getLabelKey(): string
    {
        return 'widget.button.label';
    }

    public function defaultProps(): array
    {
        return [
            'label'  => '',
            'url'    => '',
            'target' => '_self',
        ];
    }

    protected function sanitizeMergedProps(array $props, PageBuilderProtection $protection): array
    {
        $props['label']  = is_string($props['label'] ?? null) ? trim($props['label']) : '';
        $props['url']    = is_string($props['url'] ?? null) ? trim($props['url']) : '';
        $target          = is_string($props['target'] ?? null) ? $props['target'] : '_self';
        $props['target'] = in_array($target, ['_self', '_blank'], true) ? $target : '_self';

        return $props;
    }
}
