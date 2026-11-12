<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use DateTimeImmutable;
use Nowo\PageBuilderKitBundle\Enum\PageStatus;

/**
 * The page being rendered, by id and key (1.15, REQ-ARCH-001 rule 1): what a {@see GrapesTwigPageContextProviderInterface}
 * gets instead of the `BuilderPage` entity. The title and slug are those of the rendered locale.
 */
final readonly class PageContext
{
    public function __construct(
        public ?int $id,
        public string $uuid,
        public string $pageKey,
        public PageStatus $status,
        public ?DateTimeImmutable $publishedAt = null,
        public ?string $title = null,
        public ?string $slug = null,
    ) {
    }

}
