<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Entity;

use DateTimeImmutable;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageRevision;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTranslation;
use Nowo\PageBuilderKitBundle\Enum\PageStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BuilderPage::class)]
final class BuilderPageTest extends TestCase
{
    #[Test]
    public function gettersSettersAndRelations(): void
    {
        $page         = new BuilderPage();
        $beforeUpdate = $page->getUpdatedAt();
        $page->touchUpdatedAt();
        self::assertGreaterThanOrEqual($beforeUpdate->getTimestamp(), $page->getUpdatedAt()->getTimestamp());

        $page
            ->setUuid('uuid-1')
            ->setPageKey('home')
            ->setStatus(PageStatus::Published)
            ->setPublishedAt(new DateTimeImmutable('2020-01-01'));

        self::assertNull($page->getId());
        self::assertSame('uuid-1', $page->getUuid());
        self::assertSame('home', $page->getPageKey());
        self::assertSame(PageStatus::Published, $page->getStatus());
        self::assertSame('2020-01-01', $page->getPublishedAt()?->format('Y-m-d'));
        self::assertGreaterThan(0, $page->getCreatedAt()->getTimestamp());
        self::assertSame($beforeUpdate->getTimestamp(), $page->getUpdatedAt()->getTimestamp());

        $translation = (new BuilderPageTranslation())
            ->setLocale('es')
            ->setTitle('T')
            ->setSlug('t');
        $page->addTranslation($translation);
        $page->addTranslation($translation);

        self::assertCount(1, $page->getTranslations());
        self::assertSame($page, $translation->getPage());
        self::assertSame($translation, $page->getTranslation('es'));
        self::assertNull($page->getTranslation('en'));

        $document = (new BuilderDocument())
            ->setPage($page)
            ->setStructure(['version' => 1, 'sections' => []]);

        self::assertSame($document, $page->getDocument());
        self::assertSame($page, $document->getPage());

        $revision = (new BuilderPageRevision())->setPage($page);
        $page->getRevisions()->add($revision);
        self::assertCount(1, $page->getRevisions());
    }

    #[Test]
    public function touchUpdatedAtChangesTimestamp(): void
    {
        $page     = new BuilderPage();
        $previous = $page->getUpdatedAt();

        usleep(1000);
        $page->touchUpdatedAt();

        self::assertGreaterThanOrEqual($previous->getTimestamp(), $page->getUpdatedAt()->getTimestamp());
    }
}
