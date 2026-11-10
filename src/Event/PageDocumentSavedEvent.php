<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Event;

/**
 * The document (layout, widget props or content fields) of a page was saved.
 *
 * Plain value object (IDs and scalars only, no Doctrine or Symfony types): dispatched after the write is committed
 * (1.14, rule 4/7). With `doctrine.transaction: host` it is dispatched after the host's own commit.
 */
final readonly class PageDocumentSavedEvent
{
    /** Layout / widget props saved through the canvas, sections editor, import or revision restore. */
    public const string CHANGE_DOCUMENT = 'document';

    /** Content-field schema or values saved through the content editor. */
    public const string CHANGE_CONTENT_FIELDS = 'content_fields';

    public function __construct(
        public string $pageKey,
        public string $pageUuid,
        /** One of the CHANGE_* constants. */
        public string $change = self::CHANGE_DOCUMENT,
    ) {
    }
}
