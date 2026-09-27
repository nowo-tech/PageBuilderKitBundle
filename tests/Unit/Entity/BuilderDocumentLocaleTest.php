<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Entity;

use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocumentLocale;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BuilderDocumentLocale::class)]
final class BuilderDocumentLocaleTest extends TestCase
{
    #[Test]
    public function gettersAndSetters(): void
    {
        $document       = (new BuilderDocument())->setPage(new BuilderPage());
        $localeDocument = (new BuilderDocumentLocale())
            ->setLocale('en')
            ->setWidgetProps(['w' => ['x' => 1]])
            ->setDocument($document);

        self::assertNull($localeDocument->getId());
        self::assertSame('en', $localeDocument->getLocale());
        self::assertSame(['w' => ['x' => 1]], $localeDocument->getWidgetProps());
        self::assertSame($document, $localeDocument->getDocument());
    }
}
