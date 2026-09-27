<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Security\Html;

final class NullPageBuilderHtmlSanitizer implements PageBuilderHtmlSanitizerInterface
{
    public function sanitize(string $html): string
    {
        return $html;
    }
}
