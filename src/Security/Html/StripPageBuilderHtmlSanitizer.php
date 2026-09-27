<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Security\Html;

final class StripPageBuilderHtmlSanitizer implements PageBuilderHtmlSanitizerInterface
{
    public function sanitize(string $html): string
    {
        return strip_tags($html);
    }
}
