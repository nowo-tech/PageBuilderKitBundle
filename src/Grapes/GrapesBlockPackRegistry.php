<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Grapes;

use Nowo\PageBuilderKitBundle\Service\GrapesJsFrontendConfig;
use Traversable;

use function array_values;
use function is_array;
use function is_string;
use function iterator_to_array;
use function trim;

/**
 * Registry of {@see GrapesBlockPackInterface} modules for the GrapesJS canvas.
 */
final readonly class GrapesBlockPackRegistry
{
    /** @var list<GrapesBlockPackInterface> */
    private array $packs;

    /**
     * @param iterable<GrapesBlockPackInterface> $packs
     */
    public function __construct(iterable $packs = [])
    {
        $list = $packs instanceof Traversable
            ? iterator_to_array($packs, false)
            : $packs;
        $this->packs = array_values($list);
    }

    /**
     * @return list<GrapesBlockPackInterface>
     */
    public function all(): array
    {
        return $this->packs;
    }

    /**
     * @return list<array{name: string, version: string, capabilities: list<string>, blocks: list<string>}>
     */
    public function summarize(): array
    {
        $rows = [];
        foreach ($this->packs as $pack) {
            $ids = [];
            foreach ($pack->getBlocks() as $block) {
                if (is_array($block) && is_string($block['id'] ?? null) && $block['id'] !== '') {
                    $ids[] = $block['id'];
                }
            }
            $rows[] = [
                'name'         => $pack->getName(),
                'version'      => $pack->getVersion(),
                'capabilities' => $pack->getCapabilities(),
                'blocks'       => $ids,
            ];
        }

        return $rows;
    }

    /**
     * Payload embedded in {@see GrapesJsFrontendConfig::toArray()}.
     *
     * @return list<array{
     *     name: string,
     *     version: string,
     *     capabilities: list<string>,
     *     blocks: list<array{
     *         id: string,
     *         label: string,
     *         category: string,
     *         content: array<string, mixed>|string,
     *         media?: string,
     *         attributes?: array<string, mixed>
     *     }>
     * }>
     */
    public function toFrontend(): array
    {
        $rows = [];
        foreach ($this->packs as $pack) {
            $blocks = [];
            foreach ($pack->getBlocks() as $block) {
                if (!is_array($block)) {
                    continue;
                }
                $id = is_string($block['id'] ?? null) ? trim($block['id']) : '';
                if ($id === '') {
                    continue;
                }
                $label    = is_string($block['label'] ?? null) ? $block['label'] : $id;
                $category = is_string($block['category'] ?? null) ? $block['category'] : $pack->getName();
                $content  = $block['content'] ?? '<div></div>';
                if (!is_string($content) && !is_array($content)) {
                    $content = '<div></div>';
                }

                $entry = [
                    'id'       => $id,
                    'label'    => $label,
                    'category' => $category,
                    'content'  => $content,
                ];
                if (is_string($block['media'] ?? null) && $block['media'] !== '') {
                    $entry['media'] = $block['media'];
                }
                if (is_array($block['attributes'] ?? null)) {
                    /** @var array<string, mixed> $attrs */
                    $attrs               = $block['attributes'];
                    $entry['attributes'] = $attrs;
                }
                $blocks[] = $entry;
            }

            $rows[] = [
                'name'         => $pack->getName(),
                'version'      => $pack->getVersion(),
                'capabilities' => $pack->getCapabilities(),
                'blocks'       => $blocks,
            ];
        }

        return $rows;
    }
}
