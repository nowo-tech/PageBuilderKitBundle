<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use Nowo\PageBuilderKitBundle\Service\ContentFieldSlotReplacer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentFieldSlotReplacer::class)]
final class ContentFieldSlotReplacerTest extends TestCase
{
    #[Test]
    public function replacesSlotsWithEscapingAndRawHtml(): void
    {
        $replacer = new ContentFieldSlotReplacer();
        $fields   = [
            'hero_title' => 'Hello <b>x</b>',
            'body'       => '<p>Hi</p>',
            'hero'       => ['title' => 'Nested'],
            'faqs'       => [
                ['question' => 'Q1'],
            ],
        ];
        $schema = [
            ['key' => 'hero_title', 'type' => 'string'],
            ['key' => 'body', 'type' => 'html'],
            [
                'key'    => 'hero',
                'type'   => 'group',
                'fields' => [['key' => 'title', 'type' => 'string']],
            ],
            [
                'key'    => 'faqs',
                'type'   => 'repeater',
                'fields' => [['key' => 'question', 'type' => 'string']],
            ],
        ];

        $html = 'A [[fields.hero_title]] B [[@fields.body]] C [[fields.hero.title]] D [[fields.faqs.0.question]] E [[fields.missing]]';
        $out  = $replacer->replace($html, $fields, $schema);

        self::assertStringContainsString('Hello &lt;b&gt;x&lt;/b&gt;', $out);
        self::assertStringContainsString('<p>Hi</p>', $out);
        self::assertStringContainsString('Nested', $out);
        self::assertStringContainsString('Q1', $out);
        self::assertStringEndsWith('E ', $out);
        self::assertStringNotContainsString('[[fields', $out);
    }

    #[Test]
    public function leavesHtmlWithoutSlotsUntouched(): void
    {
        $replacer = new ContentFieldSlotReplacer();
        self::assertSame('<p>Hi</p>', $replacer->replace('<p>Hi</p>', ['x' => 'y']));
    }
}
