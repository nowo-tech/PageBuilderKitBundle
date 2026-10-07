<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Util;

use Nowo\PageBuilderKitBundle\Util\ContentSectionFilter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContentSectionFilterTest extends TestCase
{
    #[Test]
    public function sanitizeAcceptsAndNormalizesSlugs(): void
    {
        self::assertSame('audiences', ContentSectionFilter::sanitize('audiences'));
        self::assertSame('what_it_solves', ContentSectionFilter::sanitize('  What_It_Solves '));
    }

    #[Test]
    public function sanitizeRejectsInvalidInput(): void
    {
        self::assertNull(ContentSectionFilter::sanitize(''));
        self::assertNull(ContentSectionFilter::sanitize('   '));
        self::assertNull(ContentSectionFilter::sanitize('../x'));
        self::assertNull(ContentSectionFilter::sanitize('a-b'));
        self::assertNull(ContentSectionFilter::sanitize(null));
        self::assertNull(ContentSectionFilter::sanitize(['audiences']));
    }

    #[Test]
    public function filterSchemaKeepsExactAndPrefixedKeys(): void
    {
        $schema = [
            ['key' => 'audiences_title', 'type' => 'string'],
            ['key' => 'audiences', 'type' => 'string'],
            ['key' => 'audiences2_title', 'type' => 'string'],
            ['key' => 'hero_title', 'type' => 'string'],
            ['type' => 'string'],
            ['key' => 12],
        ];

        $out = ContentSectionFilter::filterSchema($schema, 'audiences');

        self::assertSame(['audiences_title', 'audiences'], array_column($out, 'key'));
        self::assertSame([], ContentSectionFilter::filterSchema($schema, 'missing'));
    }
}
