<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Widget;

use InvalidArgumentException;

use function sprintf;

final class WidgetTypeRegistry
{
    /** @var array<string, WidgetTypeInterface> */
    private array $typesByName = [];

    /**
     * @param iterable<WidgetTypeInterface> $types
     */
    public function __construct(iterable $types)
    {
        foreach ($types as $type) {
            $this->typesByName[$type->getType()] = $type;
        }
    }

    public function get(string $type): WidgetTypeInterface
    {
        if (!$this->has($type)) {
            throw new InvalidArgumentException(sprintf('Unknown widget type "%s".', $type));
        }

        return $this->typesByName[$type];
    }

    public function has(string $type): bool
    {
        return isset($this->typesByName[$type]);
    }

    /**
     * @return list<WidgetTypeInterface>
     */
    public function all(): array
    {
        return array_values($this->typesByName);
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->typesByName);
    }
}
