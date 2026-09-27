<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Widget\Type;

use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Widget\AbstractWidgetType;

final class HtmlWidgetType extends AbstractWidgetType
{
    public function getType(): string
    {
        return 'html';
    }

    public function getLabelKey(): string
    {
        return 'widget.html.label';
    }

    public function defaultProps(): array
    {
        return [
            'html' => '',
        ];
    }

    protected function sanitizeMergedProps(array $props, PageBuilderProtection $protection): array
    {
        $props['html'] = $this->sanitizeHtmlField($props['html'] ?? null, $protection);

        return $props;
    }
}
