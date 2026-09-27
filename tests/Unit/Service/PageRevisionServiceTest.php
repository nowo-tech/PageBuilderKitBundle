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
