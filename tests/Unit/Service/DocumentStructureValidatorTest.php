<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Service\DocumentNormalizer;
use Nowo\PageBuilderKitBundle\Service\DocumentStructureValidator;
use Nowo\PageBuilderKitBundle\Tests\Support\WidgetTypesFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentStructureValidator::class)]
final class DocumentStructureValidatorTest extends TestCase
{
    private DocumentStructureValidator $validator;

    private DocumentNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->validator  = new DocumentStructureValidator(WidgetTypesFixture::registry());
        $this->normalizer = new DocumentNormalizer();
    }

    #[Test]
    public function acceptsValidGrapesStructure(): void
    {
        $this->validator->validate([
            'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'          => '<p>Hi</p>',
            'css'           => '.a{}',
            'grapes'        => ['pages' => []],
            'localeContent' => [
                'es' => [
                    'html'   => '<p>Hola</p>',
                    'css'    => '',
                    'grapes' => [],
                ],
            ],
        ], $this->normalizer);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function rejectsGrapesWithWrongVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported document version.');

        $this->validator->validate([
            'version' => 99,
            'engine'  => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'    => '',
            'css'     => '',
            'grapes'  => [],
        ], $this->normalizer);
    }

    #[Test]
    public function rejectsGrapesMissingEngine(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('GrapesJS documents require engine=grapesjs.');

        $this->validator->validate([
            'version' => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'html'    => '',
            'css'     => '',
            'grapes'  => [],
        ], $this->normalizer);
    }

    #[Test]
    public function rejectsGrapesNonStringHtmlCss(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('GrapesJS html must be a string.');

        $this->validator->validate([
            'version' => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'  => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'    => ['x'],
            'css'     => '',
            'grapes'  => [],
        ], $this->normalizer);
    }

    #[Test]
    public function rejectsGrapesNonObjectProject(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('GrapesJS grapes project must be an object.');

        $this->validator->validate([
            'version' => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'  => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'    => '',
            'css'     => '',
            'grapes'  => 'bad',
        ], $this->normalizer);
    }

    #[Test]
    public function rejectsInvalidLocaleContent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('GrapesJS localeContent must be an object.');

        $this->validator->validate([
            'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'          => '',
            'css'           => '',
            'grapes'        => [],
            'localeContent' => 'nope',
        ], $this->normalizer);
    }

    #[Test]
    public function rejectsEmptyLocaleKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid locale key in localeContent.');

        $this->validator->validate([
            'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'          => '',
            'css'           => '',
            'grapes'        => [],
            'localeContent' => [
                '' => ['html' => '', 'css' => '', 'grapes' => []],
            ],
        ], $this->normalizer);
    }

    #[Test]
    public function rejectsIncompleteLocalePayload(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('localeContent.es must contain html, css and grapes.');

        $this->validator->validate([
            'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'          => '',
            'css'           => '',
            'grapes'        => [],
            'localeContent' => [
                'es' => ['html' => ''],
            ],
        ], $this->normalizer);
    }

    #[Test]
    public function acceptsValidClassicStructure(): void
    {
        $this->validator->validate([
            'version'  => DocumentNormalizer::SCHEMA_VERSION,
            'sections' => [
                [
                    'id'      => 'sec',
                    'columns' => [
                        [
                            'id'      => 'col',
                            'widgets' => [
                                ['id' => 'w1', 'type' => 'heading', 'children' => []],
                                [
                                    'id'       => 'c1',
                                    'type'     => 'container',
                                    'children' => [
                                        ['id' => 'w2', 'type' => 'text', 'children' => []],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $this->normalizer);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function rejectsClassicWithoutSections(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Document sections must be an array.');

        $this->validator->validate([
            'version' => DocumentNormalizer::SCHEMA_VERSION,
        ], $this->normalizer);
    }

    #[Test]
    public function rejectsClassicSectionWithoutColumns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Each section must contain at least one column.');

        $this->validator->validate([
            'version'  => DocumentNormalizer::SCHEMA_VERSION,
            'sections' => [['id' => 'sec', 'columns' => []]],
        ], $this->normalizer);
    }

    #[Test]
    public function rejectsUnknownWidgetType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown widget type "nope".');

        $this->validator->validate([
            'version'  => DocumentNormalizer::SCHEMA_VERSION,
            'sections' => [[
                'id'      => 'sec',
                'columns' => [[
                    'id'      => 'col',
                    'widgets' => [['id' => 'w1', 'type' => 'nope']],
                ]],
            ]],
        ], $this->normalizer);
    }

    #[Test]
    public function rejectsChildrenOnLeafWidget(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Widget type "heading" does not allow nested children.');

        $this->validator->validate([
            'version'  => DocumentNormalizer::SCHEMA_VERSION,
            'sections' => [[
                'id'      => 'sec',
                'columns' => [[
                    'id'      => 'col',
                    'widgets' => [[
                        'id'       => 'w1',
                        'type'     => 'heading',
                        'children' => [['id' => 'w2', 'type' => 'text']],
                    ]],
                ]],
            ]],
        ], $this->normalizer);
    }

    #[Test]
    public function rejectsNestingBeyondMaxDepth(): void
    {
        $widget = ['id' => 'leaf', 'type' => 'heading', 'children' => []];
        for ($i = 0; $i <= DocumentNormalizer::MAX_NESTING_DEPTH; ++$i) {
            $widget = [
                'id'       => 'c' . $i,
                'type'     => 'container',
                'children' => [$widget],
            ];
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Widget nesting exceeds maximum depth.');

        $this->validator->validate([
            'version'  => DocumentNormalizer::SCHEMA_VERSION,
            'sections' => [[
                'id'      => 'sec',
                'columns' => [[
                    'id'      => 'col',
                    'widgets' => [$widget],
                ]],
            ]],
        ], $this->normalizer);
    }
}
