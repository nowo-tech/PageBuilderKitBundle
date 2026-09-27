<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use Nowo\PageBuilderKitBundle\Service\DocumentNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentNormalizer::class)]
final class DocumentNormalizerTest extends TestCase
{
    private DocumentNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new DocumentNormalizer();
    }

    #[Test]
    public function emptyStructure(): void
    {
        self::assertSame(
            [
                'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
                'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
                'html'          => '',
                'css'           => '',
                'grapes'        => [],
                'localeContent' => [],
                'sections'      => [],
            ],
            $this->normalizer->emptyStructure(),
        );
    }

    #[Test]
    public function normalizeGrapesStructure(): void
    {
        $result = $this->normalizer->normalize([
            'version'       => 2,
            'engine'        => 'grapesjs',
            'html'          => '<p>Hi</p>',
            'css'           => '.x{color:red}',
            'grapes'        => ['pages' => []],
            'localeContent' => [
                'es' => ['html' => '<p>Hola</p>', 'css' => '', 'grapes' => ['a' => 1]],
                0    => 'bad',
            ],
        ]);

        self::assertTrue($this->normalizer->isGrapesStructure($result));
        self::assertSame(DocumentNormalizer::GRAPES_SCHEMA_VERSION, $result['version']);
        self::assertSame('<p>Hi</p>', $result['html']);
        self::assertSame(['pages' => []], $result['grapes']);
        self::assertSame('<p>Hola</p>', $result['localeContent']['es']['html']);
        self::assertArrayNotHasKey(0, $result['localeContent']);
    }

    #[Test]
    public function normalizeMissingColumnsCreatesOneColumn(): void
    {
        $result = $this->normalizer->normalize([
            'sections' => [
                [
                    'id' => 'section-1',
                ],
            ],
        ]);

        self::assertCount(1, $result['sections']);
        self::assertCount(1, $result['sections'][0]['columns']);
        self::assertSame(12, $result['sections'][0]['columns'][0]['settings']['width']);
        self::assertSame([], $result['sections'][0]['columns'][0]['widgets']);
        self::assertIsString($result['sections'][0]['columns'][0]['id']);
    }

    #[Test]
    public function normalizePreservesMultiColumnLayout(): void
    {
        $structure = [
            'sections' => [
                [
                    'id'      => 'sec-a',
                    'columns' => [
                        [
                            'id'       => 'col-1',
                            'settings' => ['width' => 6],
                            'widgets'  => [],
                        ],
                        [
                            'id'       => 'col-2',
                            'settings' => ['width' => 6],
                            'widgets'  => [
                                [
                                    'id'   => 'w-1',
                                    'type' => 'heading',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->normalizer->normalize($structure);

        self::assertCount(2, $result['sections'][0]['columns']);
        self::assertSame('col-1', $result['sections'][0]['columns'][0]['id']);
        self::assertSame('col-2', $result['sections'][0]['columns'][1]['id']);
        self::assertCount(1, $result['sections'][0]['columns'][1]['widgets']);
        self::assertSame('heading', $result['sections'][0]['columns'][1]['widgets'][0]['type']);
    }

    #[Test]
    public function normalizeSkipsInvalidSectionsAndWidgetsWithoutType(): void
    {
        $result = $this->normalizer->normalize([
            'sections' => [
                'invalid',
                [
                    'columns' => [
                        [
                            'widgets' => [
                                ['id' => 'w1'],
                                ['id' => 'w2', 'type' => 'heading', 'settings' => ['cssClasses' => 'hero', 'x' => 1]],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertCount(1, $result['sections']);
        self::assertCount(1, $result['sections'][0]['columns'][0]['widgets']);
        self::assertSame('heading', $result['sections'][0]['columns'][0]['widgets'][0]['type']);
        self::assertSame(['cssClasses' => 'hero'], $result['sections'][0]['columns'][0]['widgets'][0]['settings']);
    }

    #[Test]
    public function normalizeAssignsUuidsWhenIdsMissing(): void
    {
        $result = $this->normalizer->normalize([
            'sections' => [
                [
                    'columns' => [
                        [
                            'widgets' => [
                                ['type' => 'heading'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertMatchesRegularExpression(
            '/^[0-9a-f-]{36}$/i',
            $result['sections'][0]['id'],
        );
        self::assertMatchesRegularExpression(
            '/^[0-9a-f-]{36}$/i',
            $result['sections'][0]['columns'][0]['id'],
        );
        self::assertMatchesRegularExpression(
            '/^[0-9a-f-]{36}$/i',
            $result['sections'][0]['columns'][0]['widgets'][0]['id'],
        );
    }

    #[Test]
    public function isGrapesStructureByVersionOnly(): void
    {
        self::assertTrue($this->normalizer->isGrapesStructure(['version' => DocumentNormalizer::GRAPES_SCHEMA_VERSION]));
        self::assertFalse($this->normalizer->isGrapesStructure(['version' => DocumentNormalizer::SCHEMA_VERSION]));
    }

    #[Test]
    public function normalizeClassicAppliesAppearanceSettingsOnNodes(): void
    {
        $result = $this->normalizer->normalize([
            'sections' => [
                [
                    'settings' => ['cssId' => 'sec1', 'width' => 12],
                    'columns'  => [
                        [
                            'settings' => ['cssClasses' => 'col-a'],
                            'widgets'  => [
                                [
                                    'type'     => 'heading',
                                    'settings' => ['cssId' => 'head1'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame('sec1', $result['sections'][0]['settings']['cssId']);
        self::assertSame('col-a', $result['sections'][0]['columns'][0]['settings']['cssClasses']);
        self::assertSame('head1', $result['sections'][0]['columns'][0]['widgets'][0]['settings']['cssId']);
    }
}
