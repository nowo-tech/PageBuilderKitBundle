<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Event;

/**
 * A page template was saved, imported or deleted.
 *
 * Plain value object (IDs and scalars only, no Doctrine or Symfony types): dispatched after the write is committed
 * (1.14, rule 4/7). With `doctrine.transaction: host` it is dispatched after the host's own commit.
 */
final readonly class PageTemplateChangedEvent
{
    public const string ACTION_SAVED = 'saved';

    public const string ACTION_IMPORTED = 'imported';

    public const string ACTION_DELETED = 'deleted';

    public function __construct(
        public string $templateKey,
        /** One of the ACTION_* constants. */
        public string $action,
    ) {
    }
}
