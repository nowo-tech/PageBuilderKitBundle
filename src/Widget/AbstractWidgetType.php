<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Widget;

use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;

use function is_string;
use function sprintf;

abstract class AbstractWidgetType implements WidgetTypeInterface
{
    public function getPublicTemplate(): string
    {
        return sprintf('@NowoPageBuilderKitBundle/widgets/%s.html.twig', $this->getType());
    }

    public function allowsChildren(): bool
    {
        return false;
    }

    /** {@inheritDoc} */
    public function sanitizeProps(array $props, PageBuilderProtection $protection): array
    {
        $merged = array_merge($this->defaultProps(), $props);

        return $this->sanitizeMergedProps($merged, $protection);
    }

    /**
     * @param array<string, mixed> $props
     *
     * @return array<string, mixed>
     */
    protected function sanitizeMergedProps(array $props, PageBuilderProtection $protection): array
    {
        return $props;
    }

    protected function sanitizeHtmlField(mixed $value, PageBuilderProtection $protection): string
    {
        if (!is_string($value)) {
            return '';
        }

        return $protection->htmlSanitizer()->sanitize($value);
    }
}
