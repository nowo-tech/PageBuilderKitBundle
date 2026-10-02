<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtectionConfig;
use Nowo\PageBuilderKitBundle\Service\ClassicPageTreeBuilder;
use Nowo\PageBuilderKitBundle\Service\DocumentNormalizer;
use Nowo\PageBuilderKitBundle\Service\WidgetPropsMerger;
use Nowo\PageBuilderKitBundle\Tests\Support\WidgetTypesFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClassicPageTreeBuilder::class)]
final class ClassicPageTreeBuilderTest extends TestCase
{
    #[Test]
    public function buildsSectionsWithMergedLocalePropsAndNestedChildren(): void
    {
        $document = new BuilderDocument();
        $document->upsertLocale('es', [
            'w1' => ['text' => 'Hola ES'],
            'w2' => ['html' => 'Inner ES'],
        ]);
        $document->upsertLocale('en', [
            'w1' => ['text' => 'Hello EN'],
            'w2' => ['html' => 'Inner EN'],
            'w3' => ['text' => 'Only EN'],
        ]);

        $structure = [
            'version'  => DocumentNormalizer::SCHEMA_VERSION,
            'sections' => [
                [
                    'id'       => 'sec-1',
                    'settings' => ['bg' => '#fff'],
                    'columns'  => [
                        [
                            'id'       => 'col-1',
                            'settings' => ['width' => 6],
                            'widgets'  => [
                                ['id' => 'w1', 'type' => 'heading', 'settings' => ['align' => 'left']],
                                [
                                    'id'       => 'c1',
                                    'type'     => 'container',
                                    'settings' => [],
                                    'children' => [
                                        ['id' => 'w2', 'type' => 'text'],
                                        ['id' => 'bad', 'type' => 'unknown'],
                                    ],
                                ],
                                'not-an-array',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $tree = $this->builder()->build($document, $structure, 'es', 'en');

        self::assertSame('classic', $tree['engine']);
        self::assertCount(1, $tree['sections']);
        $section = $tree['sections'][0];
        self::assertSame('sec-1', $section['id']);
        self::assertSame(['bg' => '#fff'], $section['settings']);
        self::assertSame(['bg' => '#fff'], $section['appearance']);
        self::assertCount(1, $section['columns']);
        $column = $section['columns'][0];
        self::assertSame('col-1', $column['id']);
        self::assertSame(['width' => 6], $column['settings']);
        self::assertCount(2, $column['widgets']);

        $heading = $column['widgets'][0];
        self::assertSame('w1', $heading['id']);
        self::assertSame('heading', $heading['type']);
        self::assertSame(['align' => 'left'], $heading['appearance']);
        self::assertSame('Hola ES', $heading['settings']['text'] ?? null);

        $container = $column['widgets'][1];
        self::assertSame('c1', $container['id']);
        self::assertSame('container', $container['type']);
        self::assertCount(1, $container['children']);
        self::assertSame('w2', $container['children'][0]['id']);
        self::assertSame('Inner ES', $container['children'][0]['settings']['html'] ?? null);
    }

    #[Test]
    public function fallsBackToDefaultColumnWidthAndSkipsUnknownWidgets(): void
    {
        $document = new BuilderDocument();
        $document->upsertLocale('en', []);

        $tree = $this->builder()->build($document, [
            'version'  => DocumentNormalizer::SCHEMA_VERSION,
            'sections' => [[
                'columns' => [[
                    'widgets' => [
                        ['id' => 'x', 'type' => 'missing'],
                        ['id' => null, 'type' => 'heading'],
                    ],
                ]],
            ]],
        ], 'en', 'en');

        self::assertSame([['width' => 12]], array_column($tree['sections'][0]['columns'], 'settings'));
        self::assertSame([], $tree['sections'][0]['columns'][0]['widgets']);
        self::assertSame('', $tree['sections'][0]['id']);
    }

    private function builder(): ClassicPageTreeBuilder
    {
        return new ClassicPageTreeBuilder(
            WidgetTypesFixture::registry(),
            new PageBuilderProtection(new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null)),
            new WidgetPropsMerger(),
        );
    }
}
