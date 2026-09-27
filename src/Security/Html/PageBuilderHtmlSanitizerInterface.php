<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Security\Html;

interface PageBuilderHtmlSanitizerInterface
{
    public function sanitize(string $html): string;
}
