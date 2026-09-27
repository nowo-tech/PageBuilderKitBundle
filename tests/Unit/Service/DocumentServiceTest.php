<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageBuilderKitBundle\Enum\PageStatus;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepositoryInterface;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtectionConfig;
use Nowo\PageBuilderKitBundle\Service\DocumentNormalizer;
use Nowo\PageBuilderKitBundle\Service\DocumentService;
use Nowo\PageBuilderKitBundle\Service\WidgetPropsMerger;
use Nowo\PageBuilderKitBundle\Tests\Support\WidgetTypesFixture;
use Nowo\PageBuilderKitBundle\Widget\Type\ContainerWidgetType;
use Nowo\PageBuilderKitBundle\Widget\Type\HeadingWidgetType;
use Nowo\PageBuilderKitBundle\Widget\WidgetTypeRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentService::class)]
final class DocumentServiceTest extends TestCase
{
    #[Test]
    #[DataProvider('invalidPageKeysProvider')]
    public function createPageRejectsInvalidPageKey(string $pageKey): void
    {
        $service = $this->createService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid page key.');

        $service->createPage($pageKey, 'Title', 'es');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPageKeysProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => ['my page'];
        yield 'special chars' => ['page@home'];
    }

    #[Test]
    public function createPagePersistsNewPageWhenKeyIsFree(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(BuilderPage::class));
        $em->expects(self::once())->method('flush');

        $page = $this->createService(null, $em)->createPage('My-Page', 'Title', 'es');

        self::assertSame('my-page', $page->getPageKey());
        self::assertSame(PageStatus::Draft, $page->getStatus());
        self::assertSame('Title', $page->getTranslation('es')?->getTitle());
        $document = $page->getDocument();
        self::assertInstanceOf(BuilderDocument::class, $document);
        self::assertCount(2, $document->getLocales());
    }

    #[Test]
    public function createPageUsesDefaultLocaleWhenUnknownLocaleProvided(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $page = $this->createService(null, $em)->createPage('about', 'About', 'de');

        self::assertSame('es', $page->getTranslation('es')?->getLocale());
        self::assertNull($page->getTranslation('de'));
    }

    #[Test]
    public function createPageRejectsDuplicateKey(): void
    {
        $existing = (new BuilderPage())->setPageKey('home');
        $service  = $this->createService($existing);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Page key "home" already exists.');

        $service->createPage('home', 'Home', 'es');
    }

    #[Test]
    public function loadPageByKeyReturnsRepositoryResult(): void
    {
        $page = (new BuilderPage())->setPageKey('home');

        self::assertSame($page, $this->createService($page)->loadPageByKey('home'));
    }

    #[Test]
    public function flushPageDelegatesToEntityManager(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $this->createService(null, $em)->flushPage(new BuilderPage());
    }

    #[Test]
    public function validateStructureRejectsNestedWidgetsWhenTypeDisallowsChildren(): void
    {
        $structure = [
            'version'  => DocumentNormalizer::SCHEMA_VERSION,
            'sections' => [[
                'columns' => [[
                    'widgets' => [[
                        'id'       => 'w1',
                        'type'     => 'heading',
                        'children' => [['id' => 'w2', 'type' => 'heading']],
                    ]],
                ]],
            ]],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->createService()->validateStructure($structure);
    }

    #[Test]
    public function publishAndUnpublishUpdateStatus(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(2))->method('flush');

        $service = $this->createService(null, $em);
        $page    = (new BuilderPage())->setPageKey('home')->setStatus(PageStatus::Draft);

        $service->publish($page);
        self::assertSame(PageStatus::Published, $page->getStatus());
        self::assertNotNull($page->getPublishedAt());

        $service->unpublish($page);
        self::assertSame(PageStatus::Draft, $page->getStatus());
        self::assertNull($page->getPublishedAt());
    }

    #[Test]
    public function validateStructureAcceptsKnownWidgets(): void
    {
        $structure = $this->validStructure();

        $this->createService()->validateStructure($structure);

        self::assertArrayHasKey('version', $structure);
        self::assertIsInt($structure['version']);
    }

    #[Test]
    public function validateStructureAcceptsGrapesDocument(): void
    {
        $this->expectNotToPerformAssertions();

        $structure = [
            'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'          => '<p>Hi</p>',
            'css'           => '',
            'grapes'        => [],
            'localeContent' => [],
            'sections'      => [],
        ];

        $this->createService()->validateStructure($structure);
    }

    #[Test]
    public function saveDocumentPersistsGrapesStructureAndClearsWidgetProps(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $page = (new BuilderPage())->setPageKey('home');
        $page->setDocument((new BuilderDocument())->setPage($page));

        $this->createService(null, $em)->saveDocument(
            $page,
            [
                'version'       => 2,
                'engine'        => 'grapesjs',
                'html'          => '<p onclick="x">Hi</p><script>bad()</script>',
                'css'           => '.ok{color:blue}',
                'grapes'        => ['pages' => []],
                'localeContent' => [
                    'es' => [
                        'html'   => '<p>Hola</p>',
                        'css'    => '',
                        'grapes' => [],
                    ],
                ],
            ],
            [
                'es' => ['w-head' => ['text' => 'ignored']],
            ],
        );

        $document = $page->getDocument();
        self::assertInstanceOf(BuilderDocument::class, $document);
        $structure = $document->getStructure();
        self::assertSame(DocumentNormalizer::ENGINE_GRAPESJS, $structure['engine']);
        self::assertStringContainsString('<p>Hi</p>', $structure['html']);
        self::assertStringNotContainsString('script', strtolower((string) $structure['html']));
        self::assertSame('.ok{color:blue}', $structure['css']);
        self::assertSame([], $document->getLocaleDocument('es')?->getWidgetProps());
    }

    /**
     * @param array<string, mixed> $structure
     */
    #[Test]
    #[DataProvider('invalidStructureProvider')]
    public function validateStructureRejectsInvalidPayload(array $structure, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->createService()->validateStructure($structure);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidStructureProvider(): iterable
    {
        yield 'bad version' => [
            ['version' => 99, 'sections' => []],
            'Unsupported document version.',
        ];
        yield 'sections not array' => [
            ['version' => DocumentNormalizer::SCHEMA_VERSION, 'sections' => null],
            'Document sections must be an array.',
        ];
        yield 'section not object' => [
            ['version' => DocumentNormalizer::SCHEMA_VERSION, 'sections' => ['x']],
            'Each section must be an object.',
        ];
        yield 'missing columns' => [
            ['version' => DocumentNormalizer::SCHEMA_VERSION, 'sections' => [[]]],
            'Each section must contain at least one column.',
        ];
        yield 'column not object' => [
            [
                'version'  => DocumentNormalizer::SCHEMA_VERSION,
                'sections' => [['columns' => ['x']]],
            ],
            'Each column must be an object.',
        ];
        yield 'widgets missing' => [
            [
                'version'  => DocumentNormalizer::SCHEMA_VERSION,
                'sections' => [['columns' => [[]]]],
            ],
            'Each column must contain a widgets array.',
        ];
        yield 'widget not object' => [
            [
                'version'  => DocumentNormalizer::SCHEMA_VERSION,
                'sections' => [['columns' => [['widgets' => ['x']]]]],
            ],
            'Each widget must be an object.',
        ];
        yield 'widget type missing' => [
            [
                'version'  => DocumentNormalizer::SCHEMA_VERSION,
                'sections' => [['columns' => [['widgets' => [['id' => 'w']]]]]],
            ],
            'Widget type is required.',
        ];
        yield 'widget children not array' => [
            [
                'version'  => DocumentNormalizer::SCHEMA_VERSION,
                'sections' => [['columns' => [['widgets' => [['id' => 'w', 'type' => 'heading', 'children' => 'x']]]]]],
            ],
            'Widget children must be an array.',
        ];
        yield 'grapes bad version' => [
            [
                'version'       => 1,
                'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
                'html'          => '',
                'css'           => '',
                'grapes'        => [],
                'localeContent' => [],
                'sections'      => [],
            ],
            'Unsupported document version.',
        ];
        yield 'grapes bad engine' => [
            [
                'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
                'engine'        => 'classic',
                'html'          => '',
                'css'           => '',
                'grapes'        => [],
                'localeContent' => [],
                'sections'      => [],
            ],
            'GrapesJS documents require engine=grapesjs.',
        ];
        yield 'grapes html not string' => [
            [
                'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
                'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
                'html'          => null,
                'css'           => '',
                'grapes'        => [],
                'localeContent' => [],
                'sections'      => [],
            ],
            'GrapesJS html must be a string.',
        ];
        yield 'grapes css not string' => [
            [
                'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
                'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
                'html'          => '',
                'css'           => null,
                'grapes'        => [],
                'localeContent' => [],
                'sections'      => [],
            ],
            'GrapesJS css must be a string.',
        ];
        yield 'grapes project not object' => [
            [
                'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
                'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
                'html'          => '',
                'css'           => '',
                'grapes'        => 'x',
                'localeContent' => [],
                'sections'      => [],
            ],
            'GrapesJS grapes project must be an object.',
        ];
        yield 'grapes localeContent not object' => [
            [
                'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
                'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
                'html'          => '',
                'css'           => '',
                'grapes'        => [],
                'localeContent' => 'x',
                'sections'      => [],
            ],
            'GrapesJS localeContent must be an object.',
        ];
        yield 'grapes localeContent invalid locale key' => [
            [
                'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
                'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
                'html'          => '',
                'css'           => '',
                'grapes'        => [],
                'localeContent' => [0 => ['html' => '', 'css' => '', 'grapes' => []]],
                'sections'      => [],
            ],
            'Invalid locale key in localeContent.',
        ];
        yield 'grapes localeContent entry not object' => [
            [
                'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
                'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
                'html'          => '',
                'css'           => '',
                'grapes'        => [],
                'localeContent' => ['es' => 'x'],
                'sections'      => [],
            ],
            'localeContent.es must be an object.',
        ];
        yield 'grapes localeContent missing fields' => [
            [
                'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
                'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
                'html'          => '',
                'css'           => '',
                'grapes'        => [],
                'localeContent' => ['es' => ['html' => '']],
                'sections'      => [],
            ],
            'localeContent.es must contain html, css and grapes.',
        ];
    }

    #[Test]
    public function validateStructureAcceptsNestedContainerChildren(): void
    {
        $registry  = new WidgetTypeRegistry([new ContainerWidgetType(), new HeadingWidgetType()]);
        $structure = [
            'version'  => DocumentNormalizer::SCHEMA_VERSION,
            'sections' => [[
                'columns' => [[
                    'widgets' => [[
                        'id'       => 'c1',
                        'type'     => 'container',
                        'children' => [['id' => 'h1', 'type' => 'heading']],
                    ]],
                ]],
            ]],
        ];

        $service = $this->createService(registry: $registry);
        $service->validateStructure($structure);
        self::assertSame(['c1', 'h1'], $service->collectWidgetIds($structure));
    }

    #[Test]
    public function sanitizeWidgetPropsForStructureSkipsUnknownWidgetIds(): void
    {
        $sanitized = $this->createService()->sanitizeWidgetPropsForStructure(
            $this->validStructure(),
            ['w-head' => ['text' => 'Hi', 'tag' => 'h2'], 'ghost' => ['text' => 'x']],
        );

        self::assertArrayHasKey('w-head', $sanitized);
        self::assertArrayNotHasKey('ghost', $sanitized);
    }

    #[Test]
    public function saveDocumentCreatesDocumentWhenMissing(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $page = (new BuilderPage())->setPageKey('home');

        $this->createService(null, $em)->saveDocument($page, $this->validStructure(), ['es' => []]);

        self::assertInstanceOf(BuilderDocument::class, $page->getDocument());
    }

    #[Test]
    public function collectWidgetIdsIgnoresMalformedStructure(): void
    {
        $ids = $this->createService()->collectWidgetIds([
            'sections' => 'bad',
        ]);

        self::assertSame([], $ids);
    }

    #[Test]
    public function validateStructureRejectsUnknownWidgetType(): void
    {
        $structure = (new DocumentNormalizer())->normalize([
            'sections' => [
                [
                    'columns' => [
                        [
                            'widgets' => [
                                ['id' => 'w1', 'type' => 'heading'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown widget type "heading".');

        $this->createService(registry: new WidgetTypeRegistry([]))
            ->validateStructure($structure);
    }

    #[Test]
    public function mergePropsWithFallbackLocaleDelegatesToMerger(): void
    {
        $service = $this->createService();

        $merged = $service->mergePropsWithFallbackLocale(
            ['w1' => ['text' => 'en']],
            ['w1' => ['text' => 'es', 'tag' => 'h2']],
            'en',
            'es',
        );

        self::assertSame('en', $merged['w1']['text']);
        self::assertSame('h2', $merged['w1']['tag']);
    }

    #[Test]
    public function collectWidgetIdsFlattensStructure(): void
    {
        $ids = $this->createService()->collectWidgetIds($this->validStructure());

        self::assertSame(['w-head'], $ids);
    }

    #[Test]
    public function sanitizeWidgetPropsForStructureUsesHeadingRegistry(): void
    {
        $structure = $this->validStructure();
        $sanitized = $this->createService()->sanitizeWidgetPropsForStructure(
            $structure,
            ['w-head' => ['text' => '  Hi ', 'tag' => 'H1']],
        );

        self::assertSame('Hi', $sanitized['w-head']['text']);
        self::assertSame('h1', $sanitized['w-head']['tag']);
    }

    #[Test]
    public function saveDocumentNormalizesPersistsAndSanitizesLocales(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $page = (new BuilderPage())->setPageKey('home');
        $page->setDocument((new BuilderDocument())->setPage($page));

        $this->createService(null, $em)->saveDocument(
            $page,
            $this->validStructure(),
            [
                'es' => ['w-head' => ['text' => 'Hola', 'tag' => 'h1']],
                'en' => ['w-head' => ['text' => 'Hello', 'tag' => 'h2']],
            ],
        );

        $document = $page->getDocument();
        self::assertInstanceOf(BuilderDocument::class, $document);
        self::assertSame('Hola', $document->getLocaleDocument('es')?->getWidgetProps()['w-head']['text']);
        self::assertSame('Hello', $document->getLocaleDocument('en')?->getWidgetProps()['w-head']['text']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validStructure(): array
    {
        return (new DocumentNormalizer())->normalize([
            'sections' => [
                [
                    'columns' => [
                        [
                            'widgets' => [
                                ['id' => 'w-head', 'type' => 'heading'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function createService(
        ?BuilderPage $existingPage = null,
        ?EntityManagerInterface $entityManager = null,
        ?WidgetTypeRegistry $registry = null,
    ): DocumentService {
        $em = $entityManager ?? $this->createStub(EntityManagerInterface::class);

        return new DocumentService(
            $this->createStubRepository($existingPage),
            $em,
            new BuilderLocales('es', ['es', 'en']),
            new DocumentNormalizer(),
            $registry ?? WidgetTypesFixture::registry(),
            new PageBuilderProtection(
                new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null),
            ),
            new WidgetPropsMerger(),
        );
    }

    private function createStubRepository(?BuilderPage $page): BuilderPageRepositoryInterface
    {
        return new class($page) implements BuilderPageRepositoryInterface {
            public function __construct(private readonly ?BuilderPage $page)
            {
            }

            public function findOneByPageKey(string $pageKey): ?BuilderPage
            {
                if (!$this->page instanceof BuilderPage) {
                    return null;
                }

                return $this->page->getPageKey() === $pageKey ? $this->page : null;
            }

            public function findAllOrdered(): array
            {
                return [];
            }
        };
    }
}
