<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Event;

use DateTimeImmutable;

/**
 * A page was published.
 *
 * Plain value object (IDs and scalars only, no Doctrine or Symfony types): dispatched after the write is committed
 * (1.14, rule 4/7). With `doctrine.transaction: host` it is dispatched after the host's own commit.
 */
final readonly class PagePublishedEvent
{
    public function __construct(
        public string $pageKey,
        public string $pageUuid,
        public DateTimeImmutable $publishedAt,
    ) {
    }
}
