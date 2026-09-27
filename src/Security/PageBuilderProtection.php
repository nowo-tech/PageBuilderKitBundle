<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Security;

use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageBuilderKitBundle\Security\Html\AllowlistPageBuilderHtmlSanitizer;
use Nowo\PageBuilderKitBundle\Security\Html\NullPageBuilderHtmlSanitizer;
use Nowo\PageBuilderKitBundle\Security\Html\PageBuilderHtmlSanitizerInterface;
use Nowo\PageBuilderKitBundle\Security\Html\StripPageBuilderHtmlSanitizer;

final readonly class PageBuilderProtection
{
    public function __construct(
        private PageBuilderProtectionConfig $config,
        private ?PageBuilderHtmlSanitizerInterface $customSanitizer = null,
    ) {
    }

    public function htmlSanitizer(): PageBuilderHtmlSanitizerInterface
    {
        return match ($this->config->htmlSanitizeStrategy) {
            HtmlSanitizeStrategy::None      => new NullPageBuilderHtmlSanitizer(),
            HtmlSanitizeStrategy::Strip     => new StripPageBuilderHtmlSanitizer(),
            HtmlSanitizeStrategy::Allowlist => new AllowlistPageBuilderHtmlSanitizer(),
            HtmlSanitizeStrategy::Service   => $this->customSanitizer ?? new NullPageBuilderHtmlSanitizer(),
        };
    }
}
