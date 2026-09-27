<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Entity;

use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageRevision;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BuilderPageRevision::class)]
final class BuilderPageRevisionTest extends TestCase
{
    #[Test]
    public function gettersAndSetters(): void
    {
        $page     = new BuilderPage();
        $revision = (new BuilderPageRevision())
            ->setPage($page)
            ->setStructure(['version' => 1])
            ->setWidgetPropsByLocale(['es' => []])
            ->setLabel('v1');

        self::assertNull($revision->getId());
        self::assertSame($page, $revision->getPage());
        self::assertSame(['version' => 1], $revision->getStructure());
        self::assertSame(['es' => []], $revision->getWidgetPropsByLocale());
        self::assertSame('v1', $revision->getLabel());
        self::assertGreaterThan(0, $revision->getCreatedAt()->getTimestamp());

        $page->addRevision($revision);
        self::assertTrue($page->getRevisions()->contains($revision));
        $page->removeRevision($revision);
        self::assertFalse($page->getRevisions()->contains($revision));
    }
}
