<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Widget;

use Traversable;

use function array_map;
use function array_values;
use function iterator_to_array;

/**
 * Registry of {@see WidgetPackInterface} modules for diagnostics and docs.
 */
final readonly class WidgetPackRegistry
{
    /** @var list<WidgetPackInterface> */
    private array $packs;

    /**
     * @param iterable<WidgetPackInterface> $packs
     */
    public function __construct(iterable $packs = [])
    {
        $list = $packs instanceof Traversable
            ? iterator_to_array($packs, false)
            : $packs;
        $this->packs = array_values($list);
    }

    /**
     * @return list<WidgetPackInterface>
     */
    public function all(): array
    {
        return $this->packs;
    }

    /**
     * @return list<array{name: string, version: string, capabilities: list<string>, types: list<string>}>
     */
    public function summarize(): array
    {
        $rows = [];
        foreach ($this->packs as $pack) {
            $rows[] = [
                'name'         => $pack->getName(),
                'version'      => $pack->getVersion(),
                'capabilities' => $pack->getCapabilities(),
                'types'        => array_map(
                    static fn (WidgetTypeInterface $type): string => $type->getType(),
                    $pack->getWidgetTypes(),
                ),
            ];
        }

        return $rows;
    }
}
