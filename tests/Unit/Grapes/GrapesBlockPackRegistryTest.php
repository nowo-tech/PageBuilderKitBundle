<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Grapes;

use ArrayIterator;
use Nowo\PageBuilderKitBundle\Grapes\GrapesBlockPackInterface;
use Nowo\PageBuilderKitBundle\Grapes\GrapesBlockPackRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GrapesBlockPackRegistry::class)]
final class GrapesBlockPackRegistryTest extends TestCase
{
    #[Test]
    public function summarizesAndSerializesFrontendBlocks(): void
    {
        $pack = new class implements GrapesBlockPackInterface {
            public function getName(): string
            {
                return 'acme/blocks';
            }

            public function getVersion(): string
            {
                return '1.2.0';
            }

            public function getCapabilities(): array
            {
                return ['marketing'];
            }

            public function getBlocks(): array
            {
                // Includes invalid rows on purpose to exercise registry filters.
                // @phpstan-ignore return.type
                return [
                    [
                        'id'         => 'acme-hero',
                        'label'      => 'Hero',
                        'category'   => 'Acme',
                        'content'    => '<section>Hero</section>',
                        'media'      => '<svg></svg>',
                        'attributes' => ['draggable' => true],
                    ],
                    [
                        'id'       => '',
                        'label'    => 'skip',
                        'category' => 'Acme',
                        'content'  => '<div></div>',
                    ],
                    'not-an-array',
                    [
                        'id'      => 'acme-plain',
                        'content' => 123,
                    ],
                    [
                        'id'       => 'acme-cta',
                        'label'    => 'CTA',
                        'category' => 'Acme',
                        'content'  => ['type' => 'text', 'content' => 'Go'],
                    ],
                ];
            }
        };

        $registry = new GrapesBlockPackRegistry(new ArrayIterator([$pack]));

        self::assertSame([
            [
                'name'         => 'acme/blocks',
                'version'      => '1.2.0',
                'capabilities' => ['marketing'],
                'blocks'       => ['acme-hero', 'acme-plain', 'acme-cta'],
            ],
        ], $registry->summarize());

        $frontend = $registry->toFrontend();
        self::assertCount(1, $frontend);
        self::assertCount(3, $frontend[0]['blocks']);
        self::assertSame('acme-hero', $frontend[0]['blocks'][0]['id']);
        self::assertArrayHasKey('media', $frontend[0]['blocks'][0]);
        self::assertSame('<svg></svg>', $frontend[0]['blocks'][0]['media']);
        self::assertArrayHasKey('attributes', $frontend[0]['blocks'][0]);
        self::assertTrue($frontend[0]['blocks'][0]['attributes']['draggable']);
        self::assertSame('acme-plain', $frontend[0]['blocks'][1]['id']);
        self::assertSame('<div></div>', $frontend[0]['blocks'][1]['content']);
        self::assertSame('acme/blocks', $frontend[0]['blocks'][1]['category']);
        self::assertSame(['type' => 'text', 'content' => 'Go'], $frontend[0]['blocks'][2]['content']);
    }

    #[Test]
    public function acceptsPlainArrayInput(): void
    {
        $pack = new class implements GrapesBlockPackInterface {
            public function getName(): string
            {
                return 'plain';
            }

            public function getVersion(): string
            {
                return '0.1.0';
            }

            public function getCapabilities(): array
            {
                return [];
            }

            public function getBlocks(): array
            {
                return [];
            }
        };

        $registry = new GrapesBlockPackRegistry([$pack]);

        self::assertSame([$pack], $registry->all());
        self::assertSame([], $registry->toFrontend()[0]['blocks']);
    }
}
