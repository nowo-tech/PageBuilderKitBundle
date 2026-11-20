<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use RuntimeException;

/**
 * 1.18 (REQ-RUNTIME-003 rule 4): an editor saved from a read that another editor's committed save has replaced since.
 *
 * Raised under the page lock, before anything is written, so the unit stores nothing. It is a domain conflict, not a lock
 * conflict: the answer carries {@see self::CODE} and never an `X-Conflict-Code` value. The editor reloads the page and
 * applies the change again.
 *
 * - `document`: the canvas or the classic sections editor saved a document that is no longer the one it loaded
 *   (`$current` is the token of the stored document);
 * - `content`: the content form changed a field another editor changed to another value meanwhile (`$fields` lists the keys);
 * - `seo`: the SEO form changed a field another editor changed to another value meanwhile (`$fields` lists the names).
 *
 * Stateless: the exception only carries the values it is built with.
 */
final class PageStaleEditException extends RuntimeException
{
    /** Domain code of the answer (JSON `code`, never an `X-Conflict-Code` value). */
    public const string CODE = 'PAGE_BUILDER_STALE_EDIT';

    /** JSON `error` key of the answer (409). */
    public const string ERROR = 'stale_edit';

    /**
     * @param string $scope document, content or seo
     * @param string|null $current token of the stored document (document scope)
     * @param list<string> $fields keys or names of the fields that conflict (content and seo scope)
     */
    public function __construct(
        public readonly string $scope,
        public readonly ?string $current = null,
        public readonly array $fields = [],
    ) {
        parent::__construct('Another editor saved this page since it was opened. Reload it and apply the change again.');
    }
}
