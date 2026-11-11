<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use InvalidArgumentException;

/**
 * Key-based write port of the page builder (1.14, rule 1): hosts address pages by `pageKey` only, no entity.
 *
 * Implemented by {@see DocumentService} (autowire this interface). The writes run on the entity manager that maps
 * the bundle entities; with `doctrine.transaction: host` they are not flushed and the host's use case commits them.
 * Each call records an ID-only event (`Nowo\PageBuilderKitBundle\Event\*`) dispatched after the commit.
 */
interface PagePublisherInterface
{
    /**
     * Creates a draft page and returns its UUID.
     *
     * @throws InvalidArgumentException When the key is invalid or already used
     */
    public function createPageByKey(string $pageKey, string $title, string $locale): string;

    /**
     * @param array<string, mixed> $structure
     * @param array<string, array<string, mixed>> $widgetPropsByLocale
     *
     * @throws InvalidArgumentException When the page does not exist or the structure is invalid
     */
    public function saveDocumentByKey(string $pageKey, array $structure, array $widgetPropsByLocale = []): void;

    /**
     * @throws InvalidArgumentException When the page does not exist or a required content field is empty
     */
    public function publishByKey(string $pageKey): void;

    /**
     * @throws InvalidArgumentException When the page does not exist
     */
    public function unpublishByKey(string $pageKey): void;
}
