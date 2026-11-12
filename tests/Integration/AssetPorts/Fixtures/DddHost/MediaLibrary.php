<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\AssetPorts\Fixtures\DddHost;

use function count;
use function stream_get_contents;

/**
 * A DDD host's own domain service (plain PHP, no framework type): keeps media by content.
 */
final class MediaLibrary
{
    /** @var array<string, array{name: string, mime: string|null, content: string}> */
    public array $items = [];

    /**
     * @param resource $content
     */
    public function add(string $name, ?string $mime, $content): string
    {
        $id               = 'media-' . (count($this->items) + 1);
        $this->items[$id] = ['name' => $name, 'mime' => $mime, 'content' => (string) stream_get_contents($content)];

        return $id;
    }
}
