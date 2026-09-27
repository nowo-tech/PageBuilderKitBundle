<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocumentLocale;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTranslation;
use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageBuilderKitBundle\Enum\PageStatus;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepositoryInterface;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtectionConfig;
use Nowo\PageBuilderKitBundle\Service\DocumentImportExportService;
use Nowo\PageBuilderKitBundle\Service\DocumentNormalizer;
use Nowo\PageBuilderKitBundle\Service\DocumentService;
use Nowo\PageBuilderKitBundle\Service\WidgetPropsMerger;
use Nowo\PageBuilderKitBundle\Tests\Support\WidgetTypesFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentImportExportService::class)]
final class DocumentImportExportServiceTest extends TestCase
{
    #[Test]
    public function exportAndImportRoundTripCreatesDraft(): void
    {
        $page = (new BuilderPage())
            ->setPageKey('home')
            ->setStatus(PageStatus::Published)
            ->setUuid('11111111-1111-4111-8111-111111111111');
        $page->addTranslation(
            (new BuilderPageTranslation())->setLocale('es')->setTitle('Inicio')->setSlug('inicio'),
        );
        $document = (new BuilderDocument())->setPage($page)->setStructure([
            'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'          => '<p>Hi</p>',
            'css'           => '',
            'grapes'        => [],
            'localeContent' => [],
        ]);
        $document->getLocales()->add(
            (new BuilderDocumentLocale())
                ->setLocale('es')
                ->setWidgetProps(['hero' => ['title' => 'Inicio']])
                ->setDocument($document),
        );
        $page->setDocument($document);

        $store = [];
        $repo  = new class($store) implements BuilderPageRepositoryInterface {
            /** @param array<string, BuilderPage> $store */
            public function __construct(private array &$store)
            {
            }

            public function findOneByPageKey(string $pageKey): ?BuilderPage
            {
                return $this->store[$pageKey] ?? null;
            }

            public function findAllOrdered(): array
            {
                return array_values($this->store);
            }

            public function put(BuilderPage $page): void
            {
                $this->store[$page->getPageKey()] = $page;
            }
        };
        $repo->put($page);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use ($repo): void {
            if ($entity instanceof BuilderPage) {
                $repo->put($entity);
            }
        });

        $documents = new DocumentService(
            $repo,
            $em,
            new BuilderLocales('es', ['es', 'en']),
            new DocumentNormalizer(),
            WidgetTypesFixture::registry(),
            new PageBuilderProtection(new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null)),
            new WidgetPropsMerger(),
        );

        $service = new DocumentImportExportService($documents, new DocumentNormalizer(), new BuilderLocales('es', ['es', 'en']));
        $payload = $service->export($page);

        self::assertSame(1, $payload['formatVersion']);
        self::assertSame('home', $payload['pageKey']);
        self::assertSame(['hero' => ['title' => 'Inicio']], $payload['widgetPropsByLocale']['es']);

        $imported = $service->import($payload, 'home-import');
        self::assertSame('home-import', $imported->getPageKey());
        self::assertSame(PageStatus::Draft, $imported->getStatus());
    }

    #[Test]
    public function exportRejectsPagesWithoutDocument(): void
    {
        $store   = [];
        $service = new DocumentImportExportService(
            $this->documentServiceWithStore($store),
            new DocumentNormalizer(),
            new BuilderLocales('es', ['es', 'en']),
        );

        $this->expectException(InvalidArgumentException::class);
        $service->export((new BuilderPage())->setPageKey('missing-doc'));
    }

    #[Test]
    public function importRejectsInvalidPayloadShapes(): void
    {
        $store   = [];
        $service = new DocumentImportExportService(
            $this->documentServiceWithStore($store),
            new DocumentNormalizer(),
            new BuilderLocales('es', ['es', 'en']),
        );

        $cases = [
            [['formatVersion' => 99], 'Unsupported import formatVersion.'],
            [['formatVersion' => 1, 'structure' => []], 'pageKey is required.'],
            [['formatVersion' => 1, 'pageKey' => 'home', 'structure' => 'bad'], 'structure must be an object.'],
            [['formatVersion' => 1, 'pageKey' => 'home', 'structure' => [], 'widgetPropsByLocale' => 'bad'], 'widgetPropsByLocale must be an object.'],
        ];

        foreach ($cases as [$payload, $message]) {
            try {
                $service->import($payload);
                self::fail('Expected import validation to fail.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    #[Test]
    public function importUpdatesExistingPageAppliesTranslationsAndPublishes(): void
    {
        $page = (new BuilderPage())
            ->setPageKey('existing')
            ->setStatus(PageStatus::Draft)
            ->setUuid('22222222-2222-4222-8222-222222222222');
        $page->setDocument((new BuilderDocument())->setPage($page)->setStructure([
            'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'          => '<p>Old</p>',
            'css'           => '',
            'grapes'        => [],
            'localeContent' => [],
        ]));

        $store   = ['existing' => $page];
        $service = new DocumentImportExportService(
            $this->documentServiceWithStore($store),
            new DocumentNormalizer(),
            new BuilderLocales('es', ['es', 'en']),
        );

        $imported = $service->import([
            'formatVersion' => 1,
            'pageKey'       => 'existing',
            'structure'     => [
                'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
                'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
                'html'          => '<p>Updated</p>',
                'css'           => '',
                'grapes'        => [],
                'localeContent' => [],
            ],
            'widgetPropsByLocale' => [
                'es'   => ['hero' => ['title' => 'Hola']],
                'skip' => 'not-an-array',
            ],
            'translations' => [
                'skip-me',
                ['locale' => 'fr', 'title' => 'Ignored'],
                [
                    'locale'          => 'es',
                    'title'           => 'Inicio',
                    'slug'            => 'inicio',
                    'metaTitle'       => 'Meta',
                    'metaDescription' => null,
                    'ogTitle'         => 'OG',
                    'ogDescription'   => 'Description',
                    'ogImage'         => '/img.png',
                    'canonicalUrl'    => 'https://example.test/home',
                    'robots'          => 'index,follow',
                ],
            ],
        ], publish: true);

        $translation = $imported->getTranslation('es');

        self::assertSame($page, $imported);
        self::assertSame(PageStatus::Published, $imported->getStatus());
        self::assertInstanceOf(BuilderPageTranslation::class, $translation);
        self::assertSame('Inicio', $translation->getTitle());
        self::assertSame('inicio', $translation->getSlug());
        self::assertSame('Meta', $translation->getMetaTitle());
        self::assertNull($translation->getMetaDescription());
        self::assertSame('https://example.test/home', $translation->getCanonicalUrl());
    }

    /**
     * @param array<string, BuilderPage> $store
     */
    private function documentServiceWithStore(array &$store): DocumentService
    {
        $repo = new class($store) implements BuilderPageRepositoryInterface {
            /** @param array<string, BuilderPage> $store */
            public function __construct(private array &$store)
            {
            }

            public function findOneByPageKey(string $pageKey): ?BuilderPage
            {
                return $this->store[$pageKey] ?? null;
            }

            public function findAllOrdered(): array
            {
                return array_values($this->store);
            }

            public function put(BuilderPage $page): void
            {
                $this->store[$page->getPageKey()] = $page;
            }
        };

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use ($repo): void {
            if ($entity instanceof BuilderPage) {
                $repo->put($entity);
            }
        });

        return new DocumentService(
            $repo,
            $em,
            new BuilderLocales('es', ['es', 'en']),
            new DocumentNormalizer(),
            WidgetTypesFixture::registry(),
            new PageBuilderProtection(new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null)),
            new WidgetPropsMerger(),
        );
    }
}
