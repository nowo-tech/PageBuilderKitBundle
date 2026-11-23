<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\Persistence;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use LogicException;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTemplate;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTranslation;
use Nowo\PageBuilderKitBundle\Event\PageCreatedEvent;
use Nowo\PageBuilderKitBundle\Persistence\PageBuilderTransaction;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepositoryInterface;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageTemplateRepositoryInterface;
use Nowo\PageBuilderKitBundle\Service\ContentFieldsService;
use Nowo\PageBuilderKitBundle\Service\PageTemplateService;
use Nowo\PageBuilderKitBundle\Tests\Integration\Http\AbstractHttpTestCase;
use Nowo\TransactionContract\TransactionRunnerInterface;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function assert;
use function html_entity_decode;
use function json_decode;
use function json_encode;
use function parse_str;
use function preg_match;
use function preg_match_all;
use function rawurlencode;
use function str_contains;

use const ENT_HTML5;
use const ENT_QUOTES;
use const JSON_THROW_ON_ERROR;
use const PREG_SET_ORDER;

/**
 * 1.17 (REQ-RUNTIME-003 rules 1, 2 and 4): the write units of PageBuilderTransaction on a real (SQLite) manager.
 */
final class PageBuilderWriteUnitTest extends AbstractHttpTestCase
{
    public function testAUnitCommitsOnceAndDispatchesItsEventsAfterTheCommit(): void
    {
        $container  = $this->boot();
        $dispatched = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PageCreatedEvent::class, function (PageCreatedEvent $event) use (&$dispatched): void {
            $dispatched[] = [$event->pageKey, $this->em->getConnection()->isTransactionActive()];
        });
        $registry = $container->get('doctrine');
        assert($registry instanceof ManagerRegistry);

        foreach ([false, true] as $owns) {
            $transaction = new PageBuilderTransaction($this->em, $dispatcher, registry: $registry, ownsEntityManager: $owns);
            $key         = $owns ? 'own' : 'host';
            $result      = $transaction->run(function () use ($transaction, $key): string {
                // A unit started inside the unit joins it: one transaction, nothing committed before the end.
                $transaction->run(function () use ($transaction, $key): void {
                    $this->em->persist(new BuilderPage()->setPageKey($key)->setUuid('uuid-' . $key));
                    $transaction->commit(new PageCreatedEvent($key, 'uuid-' . $key));
                });
                self::assertTrue($this->em->getConnection()->isTransactionActive());

                return 'done';
            });

            self::assertSame('done', $result);
            self::assertFalse($this->em->getConnection()->isTransactionActive());
        }

        self::assertSame([['host', false], ['own', false]], $dispatched, 'dispatched after the commit, once per unit');
        self::assertSame(2, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM pb_page'));
    }

    public function testAFailedUnitStoresNothingAndTheHostManagerStaysOpen(): void
    {
        $this->boot();
        $transaction = new PageBuilderTransaction($this->em);

        $failure = null;

        try {
            $transaction->run(function () use ($transaction): never {
                $this->em->persist(new BuilderPage()->setPageKey('rolled')->setUuid('uuid-rolled'));
                $transaction->commit();

                throw new RuntimeException('domain refusal');
            });
        } catch (RuntimeException $e) {
            $failure = $e;
        }

        self::assertSame('domain refusal', $failure->getMessage());

        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM pb_page'));
        self::assertTrue($this->em->isOpen());
        self::assertFalse($this->em->getConnection()->isTransactionActive());
    }

    public function testTheHostRunnerRefusesUnflushedChangesOfTheHost(): void
    {
        $this->boot();
        $this->em->persist(new BuilderPage()->setPageKey('pending')->setUuid('uuid-pending'));

        $this->expectException(LogicException::class);
        new PageBuilderTransaction($this->em)->run(static fn (): null => null);
    }

    public function testAnInjectedRunnerIsUsedAndAHostControlledWriteRunsAsIs(): void
    {
        $this->boot();
        $runner = $this->createMock(TransactionRunnerInterface::class);
        $runner->expects(self::once())->method('run')->willReturnCallback(static fn (callable $operation): mixed => $operation());
        self::assertSame(1, new PageBuilderTransaction($this->em, runner: $runner)->run(static fn (): int => 1));

        $hostControlled = $this->createMock(TransactionRunnerInterface::class);
        $hostControlled->expects(self::never())->method('run');
        $transaction = new PageBuilderTransaction($this->em, hostControlsTransaction: true, runner: $hostControlled);
        self::assertSame(2, $transaction->run(static fn (): int => 2), 'the host use case owns the transaction');
    }

    public function testResetForgetsAnInterruptedRunDepth(): void
    {
        $this->boot();
        $runner = $this->createMock(TransactionRunnerInterface::class);
        $runner->expects(self::exactly(2))->method('run')->willReturnCallback(static fn (callable $operation): mixed => $operation());
        $transaction = new PageBuilderTransaction($this->em, runner: $runner);

        $transaction->run(static function () use ($transaction): void {
            $transaction->reset();
        });
        $transaction->run(static fn (): null => null);
    }

    public function testLockPageReadsTheDocumentAgainInsideATransactionOnly(): void
    {
        $this->boot();
        $page = $this->documents->createPage('lock', 'Lock', 'en');
        $this->documents->saveDocument($page, self::grapes('<p>stored</p>'), []);
        // 2.0: the bundle writes through its private manager; this unit runs on the test's manager, which loads the page.
        $page = $this->em->find(BuilderPage::class, $page->getId());
        self::assertInstanceOf(BuilderPage::class, $page);
        $document = $page->getDocument();
        self::assertInstanceOf(BuilderDocument::class, $document);
        $transaction = new PageBuilderTransaction($this->em);
        // Another editor committed a new structure (behind this manager's back).
        $this->em->getConnection()->executeStatement('UPDATE pb_document SET structure = ? WHERE id = ?', ['{"html":"committed elsewhere"}', $document->getId()]);

        $transaction->lockPage($page);
        self::assertStringContainsString('stored', (string) ($document->getStructure()['html'] ?? ''), 'outside a transaction nothing is read again');

        $transaction->run(static function () use ($transaction, $page, $document): void {
            $transaction->lockPage($page);
            self::assertSame(['html' => 'committed elsewhere'], $document->getStructure(), 'read again under the lock');
            self::assertCount(2, $document->getLocales());

            $transaction->lockPage(new BuilderPage());
            $detached = new BuilderPage()->setPageKey('detached');
            $id       = new ReflectionProperty(BuilderPage::class, 'id');
            $id->setValue($detached, 999);
            $transaction->lockPage($detached);
        });
    }

    public function testLockPageWithoutADocumentLocksThePageRow(): void
    {
        $this->boot();
        $page = new BuilderPage()->setPageKey('bare')->setUuid('uuid-bare');
        $this->em->persist($page);
        $this->em->flush();
        $transaction = new PageBuilderTransaction($this->em);

        $transaction->run(function () use ($transaction, $page): void {
            $transaction->lockPage($page);
            self::assertTrue($this->em->contains($page));
        });
    }

    public function testTwoEditorsSavingTheSameNewTemplateKeyGetADomainError(): void
    {
        $container = $this->boot();
        $templates = $container->get(PageTemplateService::class);
        assert($templates instanceof PageTemplateService);
        $this->em->persist(new BuilderPageTemplate()->setTemplateKey('landing')->setLabel('Other')->setStructure(self::grapes()));
        $this->em->flush();
        $this->em->clear();
        // Another editor stored the key after this one's lookup: the unique key decides.
        $templates = $this->replacing($templates, 'templateRepository', $this->createStub(BuilderPageTemplateRepositoryInterface::class));

        try {
            $templates->import(['formatVersion' => 1, 'kind' => 'page_builder_template', 'templateKey' => 'landing', 'label' => 'Mine', 'structure' => self::grapes(), 'widgetPropsByLocale' => []], false);
            self::fail('The second save must be refused.');
        } catch (InvalidArgumentException $e) {
            self::assertSame('Template "landing" was created by another editor at the same time. Reload and try again.', $e->getMessage());
        }

        self::assertTrue($this->em->isOpen(), 'the closed manager was replaced through the registry');
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM pb_page_template'));
    }

    /**
     * The SEO save is one flush outside a unit: a failed flush (here a duplicate translation) closes the manager,
     * which is replaced so the next write of a kept-alive worker works (audit UW-2).
     */
    public function testAFailedSeoFlushLeavesAnOpenManager(): void
    {
        $this->boot();
        $page = $this->documents->createPage('seo', 'Seo', 'en');
        $page->addTranslation(new BuilderPageTranslation()->setLocale('en')->setTitle('Twice')->setSlug('twice'));

        try {
            $this->documents->flushPage($page);
            self::fail('The duplicate translation must be refused.');
        } catch (UniqueConstraintViolationException) {
        }

        self::assertTrue($this->em->isOpen(), 'the closed manager was replaced');
        $this->documents->createPage('after-seo', 'After', 'en');
        self::assertSame(2, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM pb_page'));
    }

    public function testAnOpenOrUnregisteredManagerIsNotReplaced(): void
    {
        $this->boot();
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('resetManager');
        new PageBuilderTransaction($this->em, registry: $registry)->replaceClosedManager();

        $this->em->close();
        new PageBuilderTransaction($this->em)->replaceClosedManager();
        self::assertFalse($this->em->isOpen(), 'without a registry the host (or its kernel reset) replaces it');
    }

    public function testTheContentFormOfAPageWithoutADocumentIsRefusedWithAFlash(): void
    {
        $this->boot();
        $this->em->persist(new BuilderPage()->setPageKey('bare')->setUuid('uuid-bare'));
        $this->em->flush();
        $this->em->clear();

        $content = self::ADMIN . '/pages/bare/content';
        self::assertSame(302, $this->request('POST', $content . '?locale=en', ['action' => 'save_values', '_csrf_token' => 'x', 'fieldValues' => ['en' => ['title' => 'x']]])->getStatusCode());
        self::assertSame(302, $this->request('POST', $content . '/schema', ['_csrf_token' => 'x', 'schema_action' => 'remove', 'key' => 'title'])->getStatusCode());
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM pb_document'), 'nothing written');
    }

    /**
     * Audit UW2-3: inside a unit, or while a transaction is open, the runner (or its owner) recovers; a registry that
     * fails is logged and never hides the original failure.
     */
    public function testAClosedManagerIsReplacedOnlyOutsideAUnitAndNeverThrows(): void
    {
        $this->boot();
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::once())->method('resetManager')->willThrowException(new RuntimeException('registry down'));
        $runner = $this->createStub(TransactionRunnerInterface::class);
        $runner->method('run')->willReturnCallback(static fn (callable $operation): mixed => $operation());
        $transaction = new PageBuilderTransaction($this->em, registry: $registry, runner: $runner);

        $this->em->getConnection()->beginTransaction();
        $this->em->close();
        $transaction->replaceClosedManager();
        $this->em->getConnection()->rollBack();

        $transaction->run(static function () use ($transaction): void {
            $transaction->replaceClosedManager();
        });
        $transaction->replaceClosedManager();
        self::assertFalse($this->em->isOpen());
    }

    public function testAFieldAnotherEditorAddedMeanwhileIsRefused(): void
    {
        $container = $this->boot();
        $fields    = $container->get(ContentFieldsService::class);
        assert($fields instanceof ContentFieldsService);
        $page = $this->documents->createPage('fields', 'Fields', 'en');
        $this->documents->saveDocument($page, self::grapes(), []);
        $fields->saveSchemaWithField($page, ['key' => 'headline', 'type' => 'string', 'label' => 'Headline']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "headline" already exists.');
        $fields->saveSchemaWithField($page, ['key' => 'headline', 'type' => 'string', 'label' => 'Again']);
    }

    /**
     * Audit UW2-1/2: the content form posts what it showed; only what the editor changed is saved (an unchecked
     * rendered bool counts as a change to false, an untouched field is not written back).
     */
    public function testTheContentFormSavesOnlyWhatTheEditorChanged(): void
    {
        $container = $this->boot();
        $fields    = $container->get(ContentFieldsService::class);
        assert($fields instanceof ContentFieldsService);
        $page = $this->documents->createPage('intent', 'Intent', 'en');
        $this->documents->saveDocument($page, self::grapes(), []);
        $fields->saveSchema($page, [['key' => 'headline', 'type' => 'string', 'label' => 'Headline'], ['key' => 'flag', 'type' => 'bool', 'label' => 'Flag'], ['key' => 'lead', 'type' => 'string', 'label' => 'Lead']]);
        $fields->saveValues($page, ['en' => ['headline' => 'Old', 'flag' => true, 'lead' => 'Shown lead']]);

        $html = (string) $this->request('GET', self::ADMIN . '/pages/intent/content?locale=en')->getContent();
        preg_match_all('/<input\b[^>]*name="([^"]+)"[^>]*>/i', $html, $inputs, PREG_SET_ORDER);
        $query = '';
        foreach ($inputs as [$input, $name]) {
            $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5);
            if (str_contains($input, 'type="checkbox"')) {
                continue; // the editor unchecks "flag"
            }
            preg_match('/value="([^"]*)"/', $input, $value);
            $query .= '&' . rawurlencode($name) . '=' . rawurlencode(html_entity_decode($value[1] ?? '', ENT_QUOTES | ENT_HTML5));
        }
        parse_str($query . '&fieldValues%5Ben%5D%5Bheadline%5D=New&fieldOriginals%5Bxx%5D%5Blead%5D=ignored', $parameters);
        self::assertArrayHasKey('fieldOriginals', $parameters, 'the form posts what it showed');
        $post = [];
        foreach ($parameters as $name => $value) {
            $post[(string) $name] = $value;
        }

        // Another editor changes the lead after this editor's read: it is not written back.
        $structure                              = $page->getDocument()?->getStructure() ?? [];
        $structure['fieldValues']['en']['lead'] = 'Committed lead';
        $this->em->getConnection()->executeStatement('UPDATE pb_document SET structure = ? WHERE id = ?', [json_encode($structure, JSON_THROW_ON_ERROR), $page->getDocument()?->getId()]);

        self::assertSame(302, $this->request('POST', self::ADMIN . '/pages/intent/content?locale=en', $post)->getStatusCode());
        $stored = json_decode((string) $this->em->getConnection()->fetchOne('SELECT structure FROM pb_document WHERE id = ?', [$page->getDocument()?->getId()]), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($stored);
        self::assertSame(['headline' => 'New', 'flag' => false, 'lead' => 'Committed lead'], $stored['fieldValues']['en'] ?? null);
    }

    public function testKeyBasedWritesOfAnUnknownPageAreRefusedBeforeAnyUnit(): void
    {
        $this->boot();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown page "missing".');
        $this->documents->publishByKey('missing');
    }

    public function testTwoEditorsCreatingTheSamePageKeyGetTheDomainError(): void
    {
        $this->boot();
        $this->documents->createPage('race', 'Race', 'en');
        $this->em->clear();
        $documents = $this->replacing($this->documents, 'pageRepository', $this->createStub(BuilderPageRepositoryInterface::class));

        try {
            $documents->createPage('race', 'Race', 'en');
            self::fail('The second create must be refused.');
        } catch (InvalidArgumentException $e) {
            self::assertSame('Page key "race" already exists.', $e->getMessage());
        }

        self::assertTrue($this->em->isOpen());
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM pb_page'));
    }

    /**
     * A copy of a readonly service with one dependency replaced (here: a lookup that misses the other editor's row).
     *
     * @template T of object
     *
     * @param T $service
     *
     * @return T
     */
    private function replacing(object $service, string $property, object $replacement): object
    {
        $class = new ReflectionClass($service);
        $copy  = $class->newInstanceWithoutConstructor();
        foreach ($class->getProperties() as $reflection) {
            $reflection->setValue($copy, $reflection->getName() === $property ? $replacement : $reflection->getValue($service));
        }

        return $copy;
    }
}
