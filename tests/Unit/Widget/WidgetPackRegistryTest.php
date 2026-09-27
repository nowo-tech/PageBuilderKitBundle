<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Widget;

use ArrayIterator;
use Nowo\PageBuilderKitBundle\Widget\Type\HeadingWidgetType;
use Nowo\PageBuilderKitBundle\Widget\WidgetPackInterface;
use Nowo\PageBuilderKitBundle\Widget\WidgetPackRegistry;
use Nowo\PageBuilderKitBundle\Widget\WidgetTypeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WidgetPackRegistry::class)]
final class WidgetPackRegistryTest extends TestCase
{
    #[Test]
    public function summarizesPacksFromIterable(): void
    {
        $pack = new class implements WidgetPackInterface {
            public function getName(): string
            {
                return 'acme/demo';
            }

            public function getVersion(): string
            {
                return '1.0.0';
            }

            /** @return list<WidgetTypeInterface> */
            public function getWidgetTypes(): array
            {
                return [new HeadingWidgetType()];
            }

            public function getCapabilities(): array
            {
                return ['demo'];
            }
        };

        $registry = new WidgetPackRegistry(new ArrayIterator([$pack]));

        self::assertCount(1, $registry->all());
        self::assertSame([
            [
                'name'         => 'acme/demo',
                'version'      => '1.0.0',
                'capabilities' => ['demo'],
                'types'        => ['heading'],
            ],
        ], $registry->summarize());
    }

    #[Test]
    public function acceptsPlainArrayInput(): void
    {
        $pack = new class implements WidgetPackInterface {
            public function getName(): string
            {
                return 'acme/plain';
            }

            public function getVersion(): string
            {
                return '1.1.0';
            }

            public function getWidgetTypes(): array
            {
                return [];
            }

            public function getCapabilities(): array
            {
                return [];
            }
        };

        $registry = new WidgetPackRegistry([$pack]);

        self::assertSame([$pack], $registry->all());
    }
}
