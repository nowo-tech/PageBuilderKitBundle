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
}
