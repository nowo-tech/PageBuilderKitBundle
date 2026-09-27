<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use Nowo\PageBuilderKitBundle\Service\DocumentDiff;
use Nowo\PageBuilderKitBundle\Service\DocumentNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentDiff::class)]
final class DocumentDiffTest extends TestCase
{
    #[Test]
    public function detectsIdenticalDocuments(): void
    {
        $structure = [
            'version' => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'  => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'    => '<p>Hi</p>',
            'css'     => '',
            'grapes'  => [],
        ];

        $diff = (new DocumentDiff())->compare($structure, $structure);

        self::assertTrue($diff['identical']);
        self::assertFalse($diff['structureChanged']);
    }

    #[Test]
    public function detectsHtmlLengthChange(): void
    {
        $left = [
            'version' => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'  => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'    => '<p>A</p>',
            'css'     => '',
            'grapes'  => [],
        ];
        $right         = $left;
        $right['html'] = '<p>Longer content</p>';

        $diff = (new DocumentDiff())->compare($left, $right);

        self::assertFalse($diff['identical']);
        self::assertTrue($diff['structureChanged']);
        self::assertNotEmpty($diff['summary']);
    }

    #[Test]
    public function reportsEngineAndPropsDifferences(): void
    {
        $left = [
            'version' => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'  => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'    => ['unexpected'],
            'grapes'  => [],
        ];
        $right = [
            'version'  => 1,
            'sections' => [],
        ];

        $diff = (new DocumentDiff())->compare(
            $left,
            $right,
            ['es' => ['hero' => ['title' => 'Hola']]],
            ['en' => ['hero' => ['title' => 'Hello']]],
        );

        self::assertFalse($diff['identical']);
        self::assertSame('grapesjs', $diff['leftEngine']);
        self::assertSame('classic', $diff['rightEngine']);
        self::assertTrue($diff['propsChanged']);
        self::assertContains('engine', array_map('strtolower', $diff['changedPaths']));
        self::assertStringContainsString('Engine:', implode(' | ', $diff['summary']));
        self::assertStringContainsString('Widget props locales:', implode(' | ', $diff['summary']));
    }

    #[Test]
    public function reportsClassicSectionAndNestedPathDifferences(): void
    {
        $left = [
            'version'  => 1,
            'sections' => [
                ['widgets' => [['type' => 'text']]],
            ],
            'meta' => ['theme' => 'light'],
        ];
        $right = [
            'version'  => 1,
            'sections' => [],
            'meta'     => ['theme' => 'dark'],
            'extra'    => true,
        ];

        $diff = (new DocumentDiff())->compare($left, $right);

        self::assertFalse($diff['identical']);
        self::assertContains('extra', $diff['changedPaths']);
        self::assertContains('meta.theme', $diff['changedPaths']);
        self::assertStringContainsString('Sections: 1 → 0', implode(' | ', $diff['summary']));
    }
}
