<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use Nowo\PageBuilderKitBundle\Service\ContentFieldsNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentFieldsNormalizer::class)]
final class ContentFieldsNormalizerTest extends TestCase
{
    #[Test]
    public function normalizesSchemaAndResolvesLocaleFallback(): void
    {
        $normalizer = new ContentFieldsNormalizer();
        $schema     = $normalizer->normalizeSchema([
            ['key' => 'Hero Title', 'type' => 'string'], // invalid key
            ['key' => 'hero_title', 'type' => 'string', 'label' => 'Hero', 'required' => true],
            ['key' => 'hero_title', 'type' => 'string'], // duplicate
            ['key' => 'show_cta', 'type' => 'bool', 'default' => true],
            ['key' => 'tone', 'type' => 'select', 'options' => ['soft', '', 1, 'loud']],
        ]);

        self::assertCount(3, $schema);
        self::assertSame('hero_title', $schema[0]['key']);
        self::assertSame('Hero', $schema[0]['label']);
        self::assertSame([], $schema[0]['labels']);
        self::assertSame(['soft', 'loud'], $schema[2]['options']);

        $withLabels = $normalizer->normalizeSchema([
            [
                'key'    => 'hero_title',
                'type'   => 'string',
                'label'  => 'Hero',
                'labels' => ['es' => 'Título', 'en' => 'Title'],
            ],
        ]);
        self::assertSame(['es' => 'Título', 'en' => 'Title'], $withLabels[0]['labels']);
        self::assertSame('Título', $normalizer->resolveLabel($withLabels[0], 'es', 'en'));
        self::assertSame('Title', $normalizer->resolveLabel($withLabels[0], 'en', 'es'));
        self::assertSame('Title', $normalizer->resolveLabel($withLabels[0], 'fr', 'en'));
        self::assertSame('Hero', $normalizer->resolveLabel(
            ['key' => 'x', 'label' => 'Hero', 'labels' => []],
            'es',
            'en',
        ));

        $merged = $normalizer->mergeLabels($withLabels[0], [
            'labels' => ['fr' => 'Titre'],
            'label'  => 'Titre FR',
        ], 'fr');
        self::assertSame('Titre', $merged['labels']['fr']);
        self::assertSame('Titre FR', $merged['label']);

        $structure = $normalizer->applyToStructure([], $schema, [
            'es' => ['hero_title' => 'Hola', 'show_cta' => '1'],
            'en' => ['hero_title' => 'Hello'],
        ]);

        $es = $normalizer->resolveForLocale($structure, 'es', 'en');
        $en = $normalizer->resolveForLocale($structure, 'en', 'es');
        $fr = $normalizer->resolveForLocale($structure, 'fr', 'es');

        self::assertSame('Hola', $es['hero_title']);
        self::assertTrue($es['show_cta']);
        self::assertSame('Hello', $en['hero_title']);
        self::assertTrue($en['show_cta']); // default
        self::assertSame('Hola', $fr['hero_title']); // fallback locale
    }

    #[Test]
    public function normalizesRepeaterGroupReferenceAndValidatesRequired(): void
    {
        $normalizer = new ContentFieldsNormalizer();
        $schema     = $normalizer->normalizeSchema([
            [
                'key'      => 'faqs',
                'type'     => 'repeater',
                'label'    => 'FAQs',
                'required' => true,
                'min'      => 1,
                'fields'   => [
                    ['key' => 'question', 'type' => 'string'],
                    ['key' => 'answer', 'type' => 'text'],
                    ['key' => 'nested', 'type' => 'repeater', 'fields' => [['key' => 'x', 'type' => 'string']]],
                ],
            ],
            [
                'key'    => 'hero',
                'type'   => 'group',
                'fields' => [
                    ['key' => 'title', 'type' => 'string'],
                    ['key' => 'cta', 'type' => 'url'],
                ],
            ],
            ['key' => 'related', 'type' => 'reference', 'required' => true],
        ]);

        self::assertSame('repeater', $schema[0]['type']);
        self::assertSame(1, $schema[0]['min']);
        self::assertCount(3, $schema[0]['fields']);
        self::assertSame('repeater', $schema[0]['fields'][2]['type']); // nested repeater allowed
        self::assertSame('group', $schema[1]['type']);
        self::assertSame('reference', $schema[2]['type']);

        $deep = $normalizer->normalizeSchema([
            [
                'key'    => 'level0',
                'type'   => 'repeater',
                'fields' => [
                    [
                        'key'    => 'level1',
                        'type'   => 'group',
                        'fields' => [
                            [
                                'key'    => 'level2',
                                'type'   => 'repeater',
                                'fields' => [
                                    ['key' => 'title', 'type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        self::assertSame('repeater', $deep[0]['type']);
        self::assertSame('group', $deep[0]['fields'][0]['type']);
        self::assertSame('repeater', $deep[0]['fields'][0]['fields'][0]['type']);
        self::assertSame('string', $deep[0]['fields'][0]['fields'][0]['fields'][0]['type']);

        $overCap = ['key' => 'x', 'type' => 'group', 'fields' => [['key' => 'y', 'type' => 'string']]];
        for ($i = 0; $i < ContentFieldsNormalizer::MAX_NESTING_DEPTH + 2; ++$i) {
            $overCap = ['key' => 'd' . $i, 'type' => 'group', 'fields' => [$overCap]];
        }
        $capped = $normalizer->normalizeSchema([$overCap]);
        self::assertSame('group', $capped[0]['type']);
        $node = $capped[0];
        for ($i = 0; $i < ContentFieldsNormalizer::MAX_NESTING_DEPTH; ++$i) {
            self::assertSame('group', $node['type'], 'depth ' . $i);
            self::assertNotEmpty($node['fields']);
            $node = $node['fields'][0];
        }
        self::assertSame('string', $node['type'], 'beyond safety cap composites coerce to string');

        $sub = $normalizer->parseSubfieldsSpec('question:string,answer:text');
        self::assertSame(['question', 'answer'], array_column($sub, 'key'));

        $structure = $normalizer->applyToStructure([], $schema, [
            'es' => [
                'faqs' => [
                    ['question' => 'Q1', 'answer' => 'A1'],
                    ['question' => '', 'answer' => ''], // discarded
                ],
                'hero'    => ['title' => 'Hola', 'cta' => 'https://example.test'],
                'related' => 'home',
            ],
            'en' => [
                'faqs'    => [],
                'related' => '',
            ],
        ]);

        $es = $normalizer->resolveForLocale($structure, 'es', 'en');
        self::assertCount(1, $es['faqs']);
        self::assertSame('Q1', $es['faqs'][0]['question']);
        self::assertSame('Hola', $es['hero']['title']);
        self::assertSame('home', $es['related']);

        $errors = $normalizer->validateRequired($structure, ['es', 'en']);
        $keys   = array_map(static fn (array $e): string => $e['locale'] . ':' . $e['key'], $errors);
        self::assertContains('en:faqs', $keys);
        self::assertContains('en:related', $keys);
        self::assertNotContains('es:faqs', $keys);

        $layoutOnly = $normalizer->applyTemplateFieldOptions($structure, false, false);
        self::assertSame([], $layoutOnly['fields']);
        self::assertSame([], $layoutOnly['fieldValues']);

        $schemaOnly = $normalizer->applyTemplateFieldOptions($structure, true, false);
        self::assertNotEmpty($schemaOnly['fields']);
        self::assertSame([], $schemaOnly['fieldValues']);
    }
}
