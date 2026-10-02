<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Enum;

use Nowo\PageBuilderKitBundle\Enum\ContentFieldType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;

#[CoversClass(ContentFieldType::class)]
final class ContentFieldTypeTest extends TestCase
{
    #[Test]
    public function valuesListsAllCases(): void
    {
        $values = ContentFieldType::values();

        self::assertContains('string', $values);
        self::assertContains('html', $values);
        self::assertContains('repeater', $values);
        self::assertCount(count(ContentFieldType::cases()), $values);
    }

    #[Test]
    public function htmlOutputAndSanitizeFlags(): void
    {
        self::assertTrue(ContentFieldType::Html->isHtmlOutput());
        self::assertTrue(ContentFieldType::Richtext->sanitizeOnPersist());
        self::assertTrue(ContentFieldType::Raw->isHtmlOutput());
        self::assertFalse(ContentFieldType::Raw->sanitizeOnPersist());
        self::assertFalse(ContentFieldType::String->isHtmlOutput());
        self::assertFalse(ContentFieldType::String->sanitizeOnPersist());
    }

    #[Test]
    public function compositesAllowNesting(): void
    {
        self::assertTrue(ContentFieldType::Repeater->isComposite());
        self::assertTrue(ContentFieldType::Group->isComposite());
        self::assertFalse(ContentFieldType::String->isComposite());
        self::assertTrue(ContentFieldType::String->allowedAsNested());
    }
}
