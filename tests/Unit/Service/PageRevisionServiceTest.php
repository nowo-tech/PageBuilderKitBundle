<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageRevision;
use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepositoryInterface;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRevisionRepositoryInterface;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtectionConfig;
use Nowo\PageBuilderKitBundle\Service\DocumentNormalizer;
use Nowo\PageBuilderKitBundle\Service\DocumentService;
use Nowo\PageBuilderKitBundle\Service\PageRevisionService;
use Nowo\PageBuilderKitBundle\Service\PageRevisionStore;
use Nowo\PageBuilderKitBundle\Service\WidgetPropsMerger;
use Nowo\PageBuilderKitBundle\Tests\Support\WidgetTypesFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(PageRevisionService::class)]
final class PageRevisionServiceTest extends TestCase
{
    #[Test]
    public function createThrowsWhenDisabled(): void
    {
        $service = new PageRevisionService(
            $this->store(enabled: false),
            $this->documentService(),
        );

        $this->expectException(InvalidArgumentException::class);
        $service->create(new BuilderPage());
    }

    #[Test]
    public function restoreAppliesRevisionThroughDocumentService(): void
    {
        $page = (new BuilderPage())->setPageKey('home');
        $page->setDocument((new BuilderDocument())->setPage($page)->setStructure([
            'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'          => '<p>live</p>',
            'css'           => '',
            'grapes'        => [],
            'localeContent' => [],
        ]));

        $oldStructure = [
            'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'          => '<p>old</p>',
            'css'           => '',
            'grapes'        => [],
            'localeContent' => [],
        ];
        $revision = (new BuilderPageRevision())
            ->setPage($page)
            ->setStructure($oldStructure)
            ->setWidgetPropsByLocale([]);
        $idProp = new ReflectionProperty(BuilderPageRevision::class, 'id');
        $idProp->setValue($revision, 7);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::atLeastOnce())->method('flush');

        $repo = new class($revision) implements BuilderPageRevisionRepositoryInterface {
            public function __construct(private readonly BuilderPageRevision $revision)
            {
            }

            public function findByPageNewestFirst(BuilderPage $page): array
            {
                return [];
            }

            public function findLatestForPage(BuilderPage $page): ?BuilderPageRevision
            {
                return null;
            }

            public function findOneForPage(BuilderPage $page, int $revisionId): ?BuilderPageRevision
            {
                return $revisionId === 7 ? $this->revision : null;
            }
        };

        $store   = new PageRevisionStore($em, $repo, enabled: true, onSave: true, onPublish: true);
        $service = new PageRevisionService($store, $this->documentService($em));

        $restored = $service->restore($page, 7);

        self::assertSame($revision, $restored);
        self::assertSame('<p>old</p>', $page->getDocument()?->getStructure()['html'] ?? null);
    }

    #[Test]
    public function restoreThrowsWhenRevisionMissing(): void
    {
        $service = new PageRevisionService(
            $this->store(enabled: true),
            $this->documentService(),
        );

        $this->expectException(InvalidArgumentException::class);
        $service->restore((new BuilderPage())->setPageKey('x'), 99);
    }

    #[Test]
    public function restoreThrowsWhenDisabled(): void
    {
        $service = new PageRevisionService(
            $this->store(enabled: false),
            $this->documentService(),
        );

        $this->expectException(InvalidArgumentException::class);
        $service->restore((new BuilderPage())->setPageKey('x'), 1);
    }

    #[Test]
    public function diffComparesLiveDocumentAgainstRevision(): void
    {
        $page = (new BuilderPage())->setPageKey('home');
        $page->setDocument((new BuilderDocument())->setPage($page)->setStructure([
            'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'          => '<p>live</p>',
            'css'           => '',
            'grapes'        => [],
            'localeContent' => [],
        ]));

        $revision = (new BuilderPageRevision())
            ->setPage($page)
            ->setStructure([
                'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
                'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
                'html'          => '<p>old</p>',
                'css'           => '',
                'grapes'        => [],
                'localeContent' => [],
            ])
            ->setWidgetPropsByLocale([]);
        $idProp = new ReflectionProperty(BuilderPageRevision::class, 'id');
        $idProp->setValue($revision, 3);

        $repo = new class($revision) implements BuilderPageRevisionRepositoryInterface {
            public function __construct(private readonly BuilderPageRevision $revision)
            {
            }

            public function findByPageNewestFirst(BuilderPage $page): array
            {
                return [];
            }

            public function findLatestForPage(BuilderPage $page): ?BuilderPageRevision
            {
                return null;
            }

            public function findOneForPage(BuilderPage $page, int $revisionId): ?BuilderPageRevision
            {
                return $revisionId === 3 ? $this->revision : null;
            }
        };

        $store   = new PageRevisionStore($this->createStub(EntityManagerInterface::class), $repo, enabled: true);
        $service = new PageRevisionService($store, $this->documentService());
        $diff    = $service->diff($page, 3);

        self::assertSame(3, $diff['revisionId']);
        self::assertFalse($diff['identical']);
        self::assertTrue($diff['structureChanged']);
    }

    #[Test]
    public function diffThrowsWhenDisabled(): void
    {
        $service = new PageRevisionService(
            $this->store(enabled: false),
            $this->documentService(),
        );

        $this->expectException(InvalidArgumentException::class);
        $service->diff((new BuilderPage())->setPageKey('x'), 1);
    }

    #[Test]
    public function diffThrowsWhenRevisionMissing(): void
    {
        $service = new PageRevisionService(
            $this->store(enabled: true),
            $this->documentService(),
        );

        $this->expectException(InvalidArgumentException::class);
        $service->diff((new BuilderPage())->setPageKey('home'), 404);
    }

    #[Test]
    public function diffCollectsLiveLocaleProps(): void
    {
        $page     = (new BuilderPage())->setPageKey('home');
        $document = (new BuilderDocument())->setPage($page)->setStructure([
            'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'          => '<p>live</p>',
            'css'           => '',
            'grapes'        => [],
            'localeContent' => [],
        ]);
        $document->upsertLocale('es', ['w1' => ['text' => 'Hola']]);
        $page->setDocument($document);

        $revision = (new BuilderPageRevision())
            ->setPage($page)
            ->setStructure($document->getStructure())
            ->setWidgetPropsByLocale(['es' => ['w1' => ['text' => 'Hola']]]);
        $idProp = new ReflectionProperty(BuilderPageRevision::class, 'id');
        $idProp->setValue($revision, 9);

        $repo = new class($revision) implements BuilderPageRevisionRepositoryInterface {
            public function __construct(private readonly BuilderPageRevision $revision)
            {
            }

            public function findByPageNewestFirst(BuilderPage $page): array
            {
                return [];
            }

            public function findLatestForPage(BuilderPage $page): ?BuilderPageRevision
            {
                return null;
            }

            public function findOneForPage(BuilderPage $page, int $revisionId): ?BuilderPageRevision
            {
                return $revisionId === 9 ? $this->revision : null;
            }
        };

        $store   = new PageRevisionStore($this->createStub(EntityManagerInterface::class), $repo, enabled: true);
        $service = new PageRevisionService($store, $this->documentService());
        $diff    = $service->diff($page, 9);

        self::assertSame(9, $diff['revisionId']);
        self::assertTrue($diff['identical']);
    }

    #[Test]
    public function accessorsAndCreateUseUnderlyingStore(): void
    {
        $page = (new BuilderPage())->setPageKey('home');
        $page->setDocument((new BuilderDocument())->setPage($page)->setStructure([
            'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'          => '<p>live</p>',
            'css'           => '',
            'grapes'        => [],
            'localeContent' => [],
        ]));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::atLeastOnce())->method('flush');

        $listed = new BuilderPageRevision();
        $listed->setPage($page)->setStructure(['version' => 2])->setWidgetPropsByLocale([]);
        $idProp = new ReflectionProperty(BuilderPageRevision::class, 'id');
        $idProp->setValue($listed, 11);

        $repo = new class($listed) implements BuilderPageRevisionRepositoryInterface {
            public function __construct(private readonly BuilderPageRevision $listed)
            {
            }

            public function findByPageNewestFirst(BuilderPage $page): array
            {
                return [$this->listed];
            }

            public function findLatestForPage(BuilderPage $page): ?BuilderPageRevision
            {
                return null;
            }

            public function findOneForPage(BuilderPage $page, int $revisionId): ?BuilderPageRevision
            {
                return $revisionId === 11 ? $this->listed : null;
            }
        };

        $store   = new PageRevisionStore($em, $repo, enabled: true, onSave: true, onPublish: false);
        $service = new PageRevisionService($store, $this->documentService($em));

        self::assertTrue($service->isEnabled());
        self::assertSame([$listed], $service->list($page));

        $created = $service->create($page, 'Manual');

        self::assertInstanceOf(BuilderPageRevision::class, $created);
        self::assertSame('Manual', $created->getLabel());
    }

    private function store(bool $enabled): PageRevisionStore
    {
        $repo = new class implements BuilderPageRevisionRepositoryInterface {
            public function findByPageNewestFirst(BuilderPage $page): array
            {
                return [];
            }

            public function findLatestForPage(BuilderPage $page): ?BuilderPageRevision
            {
                return null;
            }

            public function findOneForPage(BuilderPage $page, int $revisionId): ?BuilderPageRevision
            {
                return null;
            }
        };

        return new PageRevisionStore(
            $this->createStub(EntityManagerInterface::class),
            $repo,
            enabled: $enabled,
        );
    }

    private function documentService(?EntityManagerInterface $em = null): DocumentService
    {
        $pages = new class implements BuilderPageRepositoryInterface {
            public function findOneByPageKey(string $pageKey): ?BuilderPage
            {
                return null;
            }

            public function findAllOrdered(): array
            {
                return [];
            }
        };

        return new DocumentService(
            $pages,
            $em ?? $this->createStub(EntityManagerInterface::class),
            new BuilderLocales('es', ['es', 'en']),
            new DocumentNormalizer(),
            WidgetTypesFixture::registry(),
            new PageBuilderProtection(
                new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null),
            ),
            new WidgetPropsMerger(),
        );
    }
}
