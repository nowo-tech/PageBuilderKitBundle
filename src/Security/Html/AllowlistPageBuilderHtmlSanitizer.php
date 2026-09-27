<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Security\Html;

final class AllowlistPageBuilderHtmlSanitizer implements PageBuilderHtmlSanitizerInterface
{
    private const ALLOWED_TAGS = '<p><br><strong><em><a><ul><ol><li><h1><h2><h3><h4><h5><h6><img><span>';

    public function sanitize(string $html): string
    {
        return strip_tags($html, self::ALLOWED_TAGS);
    }
}
