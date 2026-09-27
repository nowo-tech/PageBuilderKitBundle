<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Entity;

use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocumentLocale;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BuilderDocument::class)]
final class BuilderDocumentTest extends TestCase
{
    #[Test]
    public function structureAndLocaleUpsert(): void
    {
        $page     = (new BuilderPage())->setPageKey('p');
        $document = (new BuilderDocument())
            ->setPage($page)
            ->setStructure(['version' => 1, 'sections' => []]);

        self::assertNull($document->getId());
        self::assertSame($page, $document->getPage());
        self::assertSame(['version' => 1, 'sections' => []], $document->getStructure());

        $created = $document->upsertLocale('es', ['w1' => ['text' => 'Hola']]);
        self::assertSame('es', $created->getLocale());
        self::assertCount(1, $document->getLocales());
        self::assertSame($created, $document->getLocaleDocument('es'));

        $updated = $document->upsertLocale('es', ['w1' => ['text' => 'Adiós']]);
        self::assertSame($created, $updated);
        self::assertSame(['w1' => ['text' => 'Adiós']], $updated->getWidgetProps());
    }

    #[Test]
    public function setPageLinksBidirectionally(): void
    {
        $page     = new BuilderPage();
        $document = new BuilderDocument();

        $document->setPage($page);

        self::assertSame($page, $document->getPage());
        self::assertSame($document, $page->getDocument());
    }
}
