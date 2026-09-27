<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Widget\Type;

use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Widget\AbstractWidgetType;

use function is_string;

final class ImageWidgetType extends AbstractWidgetType
{
    public function getType(): string
    {
        return 'image';
    }

    public function getLabelKey(): string
    {
        return 'widget.image.label';
    }

    public function defaultProps(): array
    {
        return [
            'src' => '',
            'alt' => '',
        ];
    }

    protected function sanitizeMergedProps(array $props, PageBuilderProtection $protection): array
    {
        $props['src'] = is_string($props['src'] ?? null) ? trim($props['src']) : '';
        $props['alt'] = is_string($props['alt'] ?? null) ? trim($props['alt']) : '';

        return $props;
    }
}
