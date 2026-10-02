<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRevisionRepositoryInterface;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtectionConfig;
use Nowo\PageBuilderKitBundle\Service\ContentFieldsNormalizer;
use Nowo\PageBuilderKitBundle\Service\ContentFieldsService;
use Nowo\PageBuilderKitBundle\Service\DocumentNormalizer;
use Nowo\PageBuilderKitBundle\Service\PageRevisionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversClass(ContentFieldsService::class)]
final class ContentFieldsServiceTest extends TestCase
{
    #[Test]
    public function saveSchemaPersistsNormalizedFields(): void
    {
        $page = $this->pageWithDocument(['fields' => [], 'fieldValues' => []]);
        $em   = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with($page);
        $em->expects(self::once())->method('flush');

        $this->service($em)->saveSchema($page, [
            ['key' => 'hero_title', 'type' => 'string', 'label' => 'Hero'],
            ['key' => 'body', 'type' => 'html', 'label' => 'Body'],
        ]);

        $fields = $page->getDocument()?->getStructure()['fields'] ?? [];
        self::assertCount(2, $fields);
        self::assertSame('hero_title', $fields[0]['key']);
        self::assertSame('html', $fields[1]['type']);
    }

    #[Test]
    public function saveValuesRejectsEmptySchema(): void
    {
        $page = $this->pageWithDocument(['fields' => [], 'fieldValues' => []]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No content fields defined for this page.');

        $this->service()->saveValues($page, ['es' => ['hero_title' => 'Hola']]);
    }

    #[Test]
    public function saveValuesSanitizesHtmlAndRepeaterGroupRows(): void
    {
        $schema = [
            ['key' => 'body', 'type' => 'html', 'label' => 'Body'],
            [
                'key'    => 'faqs',
                'type'   => 'repeater',
                'label'  => 'FAQs',
                'fields' => [
                    ['key' => 'question', 'type' => 'string'],
                    ['key' => 'answer', 'type' => 'html'],
                ],
            ],
            [
                'key'    => 'hero',
                'type'   => 'group',
                'label'  => 'Hero',
                'fields' => [
                    ['key' => 'title', 'type' => 'string'],
                    ['key' => 'blurb', 'type' => 'html'],
                ],
            ],
        ];
        $page = $this->pageWithDocument(['fields' => $schema, 'fieldValues' => []]);
        $em   = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $this->service($em, HtmlSanitizeStrategy::Strip)->saveValues($page, [
            'es' => [
                'body' => '<p onclick="x">Hi</p><script>evil()</script>',
                'faqs' => [
                    ['question' => 'Q?', 'answer' => '<b onclick="y">A</b>'],
                    'skip-me',
                ],
                'hero' => [
                    'title' => 'T',
                    'blurb' => '<em onclick="z">E</em>',
                ],
            ],
        ]);

        $values = $page->getDocument()?->getStructure()['fieldValues']['es'] ?? [];
        // Strip strategy uses strip_tags() — script body text remains.
        self::assertSame('Hievil()', $values['body']);
        self::assertSame('A', $values['faqs'][0]['answer']);
        self::assertSame('Q?', $values['faqs'][0]['question']);
        self::assertSame('E', $values['hero']['blurb']);
        self::assertSame('T', $values['hero']['title']);
    }

    #[Test]
    public function saveFieldValueCreatesDefinitionAndUpdatesExisting(): void
    {
        $page = $this->pageWithDocument(['fields' => [], 'fieldValues' => []]);
        $svc  = $this->service();

        $svc->saveFieldValue($page, 'cta', 'es', 'Click', [
            'type'  => 'string',
            'label' => 'CTA',
        ]);

        $structure = $page->getDocument()?->getStructure() ?? [];
        self::assertSame('cta', $structure['fields'][0]['key']);
        self::assertSame('Click', $structure['fieldValues']['es']['cta']);

        $svc->saveFieldValue($page, 'cta', 'es', 'Go', [
            'labels' => ['es' => 'Llamada'],
        ]);

        $structure = $page->getDocument()?->getStructure() ?? [];
        self::assertSame('Go', $structure['fieldValues']['es']['cta']);
        self::assertSame('Llamada', $structure['fields'][0]['labels']['es'] ?? null);
    }

    #[Test]
    public function saveFieldValueRejectsUnknownKeyWithoutDefinition(): void
    {
        $page = $this->pageWithDocument(['fields' => [], 'fieldValues' => []]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown content field "missing".');

        $this->service()->saveFieldValue($page, 'missing', 'es', 'x');
    }

    #[Test]
    public function requireDocumentWhenPageHasNone(): void
    {
        $page = (new BuilderPage())->setPageKey('orphan');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Page "orphan" has no document.');

        $this->service()->saveSchema($page, []);
    }

    #[Test]
    public function snapshotsWhenRevisionStoreIsOnSave(): void
    {
        $page = $this->pageWithDocument(['fields' => [], 'fieldValues' => []]);
        $em   = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::atLeastOnce())->method('flush');

        $revisionRepo = $this->createStub(BuilderPageRevisionRepositoryInterface::class);
        $revisionRepo->method('findLatestForPage')->willReturn(null);
        $revisionRepo->method('findByPageNewestFirst')->willReturn([]);

        $store = new PageRevisionStore($em, $revisionRepo, enabled: true, onSave: true, onPublish: true);

        $this->service($em, HtmlSanitizeStrategy::None, $store)->saveSchema($page, [
            ['key' => 'title', 'type' => 'string'],
        ]);

        self::assertSame('title', $page->getDocument()?->getStructure()['fields'][0]['key'] ?? null);
    }

    #[Test]
    public function saveValuesAcceptsSchemaOverrideAndSnapshots(): void
    {
        $page = $this->pageWithDocument(['fields' => [], 'fieldValues' => []]);
        $em   = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::atLeastOnce())->method('flush');

        $revisionRepo = $this->createStub(BuilderPageRevisionRepositoryInterface::class);
        $revisionRepo->method('findLatestForPage')->willReturn(null);
        $revisionRepo->method('findByPageNewestFirst')->willReturn([]);
        $store = new PageRevisionStore($em, $revisionRepo, enabled: true, onSave: true, onPublish: true);

        $this->service($em, HtmlSanitizeStrategy::None, $store)->saveValues($page, [
            'es' => ['title' => 'Hola'],
        ], [
            ['key' => 'title', 'type' => 'string', 'label' => 'Title'],
            ['key' => 'other', 'type' => 'string', 'label' => 'Other'],
        ]);

        $structure = $page->getDocument()?->getStructure() ?? [];
        self::assertSame('Hola', $structure['fieldValues']['es']['title']);
        self::assertCount(2, $structure['fields']);
    }

    #[Test]
    public function saveFieldValueSnapshotsAndSkipsNonMatchingSchemaKeys(): void
    {
        $page = $this->pageWithDocument([
            'fields' => [
                ['key' => 'other', 'type' => 'string'],
                ['key' => 'title', 'type' => 'string'],
            ],
            'fieldValues' => [],
        ]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::atLeastOnce())->method('flush');

        $revisionRepo = $this->createStub(BuilderPageRevisionRepositoryInterface::class);
        $revisionRepo->method('findLatestForPage')->willReturn(null);
        $revisionRepo->method('findByPageNewestFirst')->willReturn([]);
        $store = new PageRevisionStore($em, $revisionRepo, enabled: true, onSave: true, onPublish: true);

        $this->service($em, HtmlSanitizeStrategy::None, $store)->saveFieldValue($page, 'title', 'es', 'Hi');

        self::assertSame('Hi', $page->getDocument()?->getStructure()['fieldValues']['es']['title'] ?? null);
    }

    #[Test]
    public function sanitizeSkipsMalformedBagsRowsAndUnknownTypes(): void
    {
        $schema = [
            ['key' => 'body', 'type' => 'html'],
            ['key' => 'weird', 'type' => 'not-real'],
            [
                'key'    => 'faqs',
                'type'   => 'repeater',
                'fields' => [
                    'bad-sub',
                    ['type' => 'html'],
                    ['key' => 'answer', 'type' => 'html'],
                ],
            ],
            [
                'key'    => 'hero',
                'type'   => 'group',
                'fields' => [
                    'bad',
                    ['type' => 'html'],
                    ['key' => 'blurb', 'type' => 'html'],
                ],
            ],
        ];
        $page = $this->pageWithDocument(['fields' => $schema, 'fieldValues' => []]);

        // Force a non-array locale bag through sanitizeHtmlTypedValues by mocking normalizer?
        // Instead exercise repeater/group skip branches via saveValues.
        $this->service(null, HtmlSanitizeStrategy::Strip)->saveValues($page, [
            'es' => [
                'body'  => '<b>ok</b>',
                'weird' => '<script>x</script>',
                'faqs'  => [
                    'skip-row',
                    ['answer' => '<i>A</i>', 'ignored' => 'x'],
                ],
                'hero' => [
                    'blurb' => '<em>E</em>',
                    'x'     => 'y',
                ],
            ],
        ]);

        $values = $page->getDocument()?->getStructure()['fieldValues']['es'] ?? [];
        self::assertSame('ok', $values['body']);
        self::assertSame('<script>x</script>', $values['weird']);
        self::assertSame('A', $values['faqs'][0]['answer']);
        self::assertSame('E', $values['hero']['blurb']);
    }

    #[Test]
    public function sanitizeHandlesMalformedBagsViaReflection(): void
    {
        $service = $this->service(null, HtmlSanitizeStrategy::Strip);
        $schema  = [
            ['key' => 'body', 'type' => 'not-a-type'],
            [
                'key'    => 'faqs',
                'type'   => 'repeater',
                'fields' => [
                    'bad',
                    ['type' => 'html'],
                    ['key' => 'answer', 'type' => 'html'],
                ],
            ],
            [
                'key'    => 'hero',
                'type'   => 'group',
                'fields' => [
                    'bad',
                    ['type' => 'html'],
                    ['key' => 'blurb', 'type' => 'html'],
                ],
            ],
        ];

        $method = new ReflectionMethod(ContentFieldsService::class, 'sanitizeHtmlTypedValues');
        $out    = $method->invoke($service, [
            'bad' => 'not-array',
            'es'  => [
                'body' => '<b>x</b>',
                'faqs' => [
                    'skip',
                    ['answer' => '<i>A</i>'],
                ],
                'hero' => [
                    'blurb' => '<em>E</em>',
                ],
            ],
        ], $schema);

        self::assertSame('<b>x</b>', $out['es']['body']);
        self::assertSame('A', $out['es']['faqs'][0]['answer']);
        self::assertSame('E', $out['es']['hero']['blurb']);
    }

    /**
     * @param array<string, mixed> $structure
     */
    private function pageWithDocument(array $structure): BuilderPage
    {
        $page     = (new BuilderPage())->setPageKey('home');
        $document = (new BuilderDocument())->setPage($page)->setStructure($structure);
        $page->setDocument($document);

        return $page;
    }

    private function service(
        ?EntityManagerInterface $em = null,
        HtmlSanitizeStrategy $strategy = HtmlSanitizeStrategy::None,
        ?PageRevisionStore $store = null,
    ): ContentFieldsService {
        return new ContentFieldsService(
            $em ?? $this->createStub(EntityManagerInterface::class),
            new DocumentNormalizer(),
            new ContentFieldsNormalizer(),
            new PageBuilderProtection(new PageBuilderProtectionConfig($strategy, null)),
            $store,
        );
    }
}
