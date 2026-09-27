<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Security;

use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;

final readonly class PageBuilderProtectionConfig
{
    public function __construct(
        public HtmlSanitizeStrategy $htmlSanitizeStrategy,
        public ?string $htmlSanitizeService,
    ) {
    }
}
