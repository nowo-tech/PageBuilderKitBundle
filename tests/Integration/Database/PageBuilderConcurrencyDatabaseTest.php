<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\Database;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Controller\Admin\PageDocumentApiController;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTranslation;
use Nowo\PageBuilderKitBundle\EventListener\PageBuilderConflictListener;
use Nowo\PageBuilderKitBundle\Service\ContentFieldsService;
use Nowo\PageBuilderKitBundle\Service\DocumentService;
use Nowo\PageBuilderKitBundle\Tests\Integration\Http\AdminHttpKernel;
use Nowo\TransactionContract\TransientStorageFailure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use function array_column;
use function assert;
use function file_exists;
use function getenv;
use function html_entity_decode;
use function in_array;
use function is_array;
use function is_resource;
use function is_string;
use function json_decode;
use function json_encode;
use function microtime;
use function parse_str;
use function preg_match_all;
use function preg_replace_callback;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function rawurlencode;
use function str_contains;
use function str_ends_with;
use function stream_get_contents;
use function strlen;
use function strtolower;
use function substr;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use function usleep;

use const ENT_HTML5;
use const ENT_QUOTES;
use const JSON_THROW_ON_ERROR;
use const PHP_BINARY;
use const PREG_SET_ORDER;

/**
 * REQ-RUNTIME-003: the bundle's write units against real MySQL 8.4, MariaDB 11.4 and PostgreSQL 16, with a second
 * database session (a plain connection, or a child process holding a transaction open). Every session bounds its
 * lock waits (innodb_lock_wait_timeout / lock_timeout), so a hang fails fast.
 *
 * Run by `make test-with-db` and the CI "Real databases" job (PBK_MYSQL_URL, PBK_MARIADB_URL, PBK_POSTGRES_URL).
 */
#[Group('database')]
final class PageBuilderConcurrencyDatabaseTest extends TestCase
{
    private const string ADMIN = '/admin/page-builder';

    private ?AdminHttpKernel $kernel = null;

    private ?Connection $other = null;

    private string $url = '';

    private EntityManagerInterface $em;

    private DocumentService $documents;

    private ContainerInterface $container;

    /**
     * Suffix of a data set run with `doctrine.transaction: host` (DDD hosts): the bundle's admin writes then run on your
     * default manager through transaction-doctrine's `forHostEntityManager()` (REQ-RUNTIME-003 rules 1, 2 and 6).
     */
    private const string HOST = '|host';

    /**
     * With `transaction: host` the bundle flushes its own writes only while one of its admin controllers serves the main
     * request (the bundle UI's use case). The test's direct service calls (seeds, the write under test) run inside such
     * a main request, pushed on the request stack; it is taken off around each real HTTP request of the test.
     */
    private ?Request $adminUseCase = null;

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function databases(): iterable
    {
        yield 'MySQL 8.4' => ['PBK_MYSQL_URL'];
        yield 'MariaDB 11.4' => ['PBK_MARIADB_URL'];
        yield 'PostgreSQL 16' => ['PBK_POSTGRES_URL'];
        // 2.0: the zero-config sets above run on the private manager; the same rule 1 / 2 / 4 / 6 suite with
        // transaction: host runs the admin's writes on your default manager (forHostEntityManager()).
        yield 'MySQL 8.4, transaction: host' => ['PBK_MYSQL_URL' . self::HOST];
        yield 'MariaDB 11.4, transaction: host' => ['PBK_MARIADB_URL' . self::HOST];
        yield 'PostgreSQL 16, transaction: host' => ['PBK_POSTGRES_URL' . self::HOST];
    }

    protected function tearDown(): void
    {
        if ($this->other instanceof Connection) {
            if ($this->other->isTransactionActive()) {
                $this->other->rollBack();
            }
            $this->other->close();
            $this->other = null;
        }
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    /**
     * A save that meets a row lock held by another session answers 409 + Retry-After + CONFLICT_001 (JSON shape kept,
     * code added); nothing is stored, and the same connection saves on the retry.
     */
    #[DataProvider('databases')]
    public function testSaveBlockedByALockAnswersConflict001AndTheRetrySaves(string $env): void
    {
        $this->boot($env);
        $this->documents->saveDocument($this->documents->createPage('home', 'Home', 'en'), $this->grapes('<p>v1</p>'), []);
        $this->holdDocumentLock('home');

        $response = $this->saveThroughApi('home', '<p>v2</p>');

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('CONFLICT_001', $response->headers->get('X-Conflict-Code'));
        self::assertSame('1', $response->headers->get('Retry-After'));
        self::assertSame(['error' => 'conflict', 'code' => 'CONFLICT_001', 'message' => PageBuilderConflictListener::RETRY_SAFE_MESSAGE], $this->decode($response));
        self::assertStringContainsString('<p>v1</p>', $this->storedHtml('home'), 'nothing of the failed save is stored');

        $this->releaseOther();
        $retry = $this->saveThroughApi('home', '<p>v2</p>');
        self::assertSame(200, $retry->getStatusCode(), 'the connection is usable after the conflict');
        self::assertStringContainsString('<p>v2</p>', $this->storedHtml('home'));
    }

    /**
     * An HTML admin action (publish, a form post) that meets a lock answers through the host's error page: the
     * configured status (503 here) with the conflict code; the page stays a draft.
     */
    #[DataProvider('databases')]
    public function testPublishBlockedByALockAnswersTheConfiguredStatusWithTheCode(string $env): void
    {
        $this->boot($env, ['conflict_response' => ['status' => 503, 'retry_after' => 4]]);
        $this->documents->saveDocument($this->documents->createPage('about', 'About', 'en'), $this->grapes(), []);
        $this->holdDocumentLock('about');

        $response = $this->handle(Request::create(self::ADMIN . '/pages/about/publish', 'POST', ['_csrf_token' => 't']));

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('CONFLICT_001', $response->headers->get('X-Conflict-Code'));
        self::assertSame('4', $response->headers->get('Retry-After'));
        $this->releaseOther();
        self::assertSame('draft', $this->other()->fetchOne($this->sql('SELECT {page.status} FROM {page} WHERE {page.pageKey} = ?'), ['about']));
    }

    /**
     * Two editors saving different content fields of one page at the same time (two processes): the second save
     * waits for the first one's lock, reads the structure again under it and keeps both values (no lost update).
     */
    #[DataProvider('databases')]
    public function testTwoEditorsSavingDifferentFieldsAtOnceKeepBothValues(string $env): void
    {
        $url  = $this->boot($env, lockWait: 8);
        $page = $this->documents->createPage('fields', 'Fields', 'en');
        $this->documents->saveDocument($page, $this->grapes('<p>f</p>', [['key' => 'title', 'type' => 'string', 'label' => 'Title'], ['key' => 'lead', 'type' => 'string', 'label' => 'Lead']]), []);
        $documentId                              = $this->documentId('fields');
        $structure                               = $this->storedStructure('fields');
        $structure['fieldValues']['en']['title'] = 'First editor';
        $this->em->clear();

        $child = $this->startHolder($url, [
            [$this->sql('SELECT id FROM {document} WHERE id = ? FOR UPDATE'), [$documentId]],
            [$this->sql('UPDATE {document} SET {document.structure} = ? WHERE id = ?'), [json_encode($structure, JSON_THROW_ON_ERROR), $documentId]],
        ], 800);

        $started  = microtime(true);
        $response = $this->handle($this->jsonRequest(self::ADMIN . '/pages/fields/fields/lead', ['locale' => 'en', 'value' => 'Second editor', 'type' => 'string', 'label' => 'Lead']));
        $this->finish($child);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertGreaterThan(0.3, microtime(true) - $started, 'the save waited for the other editor');
        $values = $this->storedStructure('fields')['fieldValues']['en'] ?? [];
        self::assertSame('First editor', $values['title'] ?? null, 'the first editor\'s value survives');
        self::assertSame('Second editor', $values['lead'] ?? null);
    }

    /**
     * The content form of one locale (editor A, en, read before) posted while editor B's save of another locale (es)
     * holds the lock (two processes): only A's changes are merged onto what B committed (audit UW-1).
     */
    #[DataProvider('databases')]
    public function testTheContentFormOfOneLocaleKeepsAnotherEditorsValueOfAnotherLocale(string $env): void
    {
        $this->boot($env, lockWait: 8);
        $this->fieldsPage('form', [['key' => 'headline', 'type' => 'string', 'label' => 'Headline'], ['key' => 'flag', 'type' => 'bool', 'label' => 'Flag']]);

        $stored = $this->postStaleForm('form', ['es' => ['headline' => 'Titular de B']], [
            'fieldValues[en][headline]' => 'Headline of A',
            'fieldLabels[en][headline]' => 'Main headline',
        ]);

        self::assertSame('Titular de B', $stored['fieldValues']['es']['headline'] ?? null, 'the other editor\'s value survives');
        self::assertSame('Headline of A', $stored['fieldValues']['en']['headline'] ?? null);
        self::assertFalse((bool) ($stored['fieldValues']['en']['flag'] ?? false));
        self::assertSame('Main headline', $stored['fields'][0]['labels']['en'] ?? null, 'the changed label is applied');
        self::assertSame('Flag', $stored['fields'][1]['label'] ?? null, 'an unchanged label is not written');
    }

    /**
     * Audit UW2-1: editor B turns a bool of the same locale on while editor A posts the form read before (the box
     * shown unchecked, left alone): the bool stays true.
     */
    #[DataProvider('databases')]
    public function testAStaleFormKeepsABoolAnotherEditorTurnedOn(string $env): void
    {
        $this->boot($env, lockWait: 8);
        $this->fieldsPage('bools', [['key' => 'headline', 'type' => 'string', 'label' => 'Headline'], ['key' => 'featured', 'type' => 'bool', 'label' => 'Featured']]);

        $stored = $this->postStaleForm('bools', ['en' => ['featured' => true]], ['fieldValues[en][headline]' => 'Changed by A']);

        self::assertTrue($stored['fieldValues']['en']['featured'] ?? null, 'the other editor\'s bool survives');
        self::assertSame('Changed by A', $stored['fieldValues']['en']['headline'] ?? null);
    }

    /**
     * Audit UW2-2: editor B saves a field of the same locale inline while editor A posts the form read before without
     * touching that field: B's value is kept (only A's changes are merged).
     */
    #[DataProvider('databases')]
    public function testAStaleFormKeepsAFieldOfTheSameLocaleItDidNotChange(string $env): void
    {
        $this->boot($env, lockWait: 8);
        $this->fieldsPage('lead', [['key' => 'headline', 'type' => 'string', 'label' => 'Headline'], ['key' => 'lead', 'type' => 'string', 'label' => 'Lead']]);

        $stored = $this->postStaleForm('lead', ['en' => ['lead' => 'Inline lead of B']], ['fieldValues[en][headline]' => 'Headline of A']);

        self::assertSame('Inline lead of B', $stored['fieldValues']['en']['lead'] ?? null);
        self::assertSame('Headline of A', $stored['fieldValues']['en']['headline'] ?? null);
    }

    /**
     * A schema change (add a field) posted while another editor's save adds a field inline (two processes): both
     * fields are in the schema (audit UW-1).
     */
    #[DataProvider('databases')]
    public function testASchemaAddKeepsAFieldAnotherEditorAddedAtTheSameTime(string $env): void
    {
        $url  = $this->boot($env, lockWait: 8);
        $page = $this->documents->createPage('schema', 'Schema', 'en');
        $this->documents->saveDocument($page, $this->grapes('<p>s</p>', [['key' => 'headline', 'type' => 'string', 'label' => 'Headline']]), []);
        $documentId            = $this->documentId('schema');
        $structure             = $this->storedStructure('schema');
        $structure['fields'][] = ['key' => 'inline', 'type' => 'string', 'label' => 'Inline'];
        $this->em->clear();

        $child = $this->startHolder($url, [
            [$this->sql('SELECT id FROM {document} WHERE id = ? FOR UPDATE'), [$documentId]],
            [$this->sql('UPDATE {document} SET {document.structure} = ? WHERE id = ?'), [json_encode($structure, JSON_THROW_ON_ERROR), $documentId]],
        ], 800);
        $response = $this->handle(Request::create(self::ADMIN . '/pages/schema/content/schema', 'POST', ['_csrf_token' => 't', 'schema_action' => 'add', 'key' => 'added', 'type' => 'string', 'label' => 'Added']));
        $this->finish($child);
        self::assertSame(302, $response->getStatusCode());

        $keys = array_column($this->storedStructure('schema')['fields'] ?? [], 'key');
        self::assertSame(['headline', 'inline', 'added'], $keys);

        $this->handle(Request::create(self::ADMIN . '/pages/schema/content/schema', 'POST', ['_csrf_token' => 't', 'schema_action' => 'remove', 'key' => 'headline']));
        self::assertSame(['inline', 'added'], array_column($this->storedStructure('schema')['fields'] ?? [], 'key'));
    }

    /**
     * A field save from an editor whose page was read before another editor's committed change (a stale identity map)
     * starts from the committed structure: the other value is kept.
     */
    #[DataProvider('databases')]
    public function testFieldSaveFromAStaleReadKeepsTheCommittedValues(string $env): void
    {
        $this->boot($env);
        $page = $this->documents->createPage('stale', 'Stale', 'en');
        $this->documents->saveDocument($page, $this->grapes('<p>s</p>', [['key' => 'title', 'type' => 'string', 'label' => 'Title'], ['key' => 'lead', 'type' => 'string', 'label' => 'Lead']]), []);
        $structure                               = $this->storedStructure('stale');
        $structure['fieldValues']['en']['title'] = 'Committed elsewhere';
        $this->other()->executeStatement($this->sql('UPDATE {document} SET {document.structure} = ? WHERE id = ?'), [json_encode($structure, JSON_THROW_ON_ERROR), $this->documentId('stale')]);

        $fields = $this->container->get(ContentFieldsService::class);
        assert($fields instanceof ContentFieldsService);
        $fields->saveFieldValue($page, 'lead', 'en', 'Mine');

        $values = $this->storedStructure('stale')['fieldValues']['en'] ?? [];
        self::assertSame('Committed elsewhere', $values['title'] ?? null);
        self::assertSame('Mine', $values['lead'] ?? null);
    }

    /**
     * Two editors creating the same page key at once (two processes): the unique key refuses the second, which gets
     * the domain answer ("already exists"), never a 500 or a conflict code; one page is stored.
     */
    #[DataProvider('databases')]
    public function testTwoCreatesOfOneKeyAtOnceGiveOneDomainError(string $env): void
    {
        $url   = $this->boot($env, lockWait: 8);
        $child = $this->startHolder($url, [[
            // An explicit id: DBAL 3 maps the PostgreSQL id to a sequence with no column default.
            $this->sql('INSERT INTO {page} (id, {page.uuid}, {page.pageKey}, {page.status}, {page.createdAt}, {page.updatedAt}) VALUES (?, ?, ?, ?, ?, ?)'),
            [9000, '00000000-0000-4000-8000-0000000000aa', 'race', 'draft', '2026-11-19 10:00:00', '2026-11-19 10:00:00'],
        ]], 800);

        try {
            $this->documents->createPage('race', 'Race', 'en');
            self::fail('The second create must be refused.');
        } catch (InvalidArgumentException $e) {
            self::assertSame('Page key "race" already exists.', $e->getMessage());
        } finally {
            $this->finish($child);
        }

        self::assertSame(1, (int) $this->other()->fetchOne($this->sql('SELECT COUNT(*) FROM {page} WHERE {page.pageKey} = ?'), ['race']));
        self::assertTrue($this->em->isOpen(), 'the closed manager was replaced through the registry');
        $this->documents->createPage('after-race', 'After', 'en');
        self::assertSame(1, (int) $this->other()->fetchOne($this->sql('SELECT COUNT(*) FROM {page} WHERE {page.pageKey} = ?'), ['after-race']));
    }

    /**
     * A lock conflict after a unit of the same request committed answers CONFLICT_002 without Retry-After (commit
     * counted by the real commit-tracking middleware of the bundle's connection).
     */
    #[DataProvider('databases')]
    public function testConflictAfterACommittedUnitAnswersConflict002(string $env): void
    {
        $this->boot($env);
        $this->documents->saveDocument($this->documents->createPage('first', 'First', 'en'), $this->grapes(), []);
        $listener = $this->container->get(PageBuilderConflictListener::class);
        assert($listener instanceof PageBuilderConflictListener);
        assert($this->kernel instanceof HttpKernelInterface);

        $request = Request::create(self::ADMIN . '/pages/first/document', 'POST', server: ['HTTP_ACCEPT' => 'application/json']);
        $request->attributes->set('_controller', PageDocumentApiController::class . '::saveDocument');
        $listener->onRequest(new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $this->documents->createPage('second', 'Second', 'en');
        $this->holdDocumentLock('first');
        $first = $this->em->getRepository(BuilderPage::class)->findOneBy(['pageKey' => 'first']);
        self::assertInstanceOf(BuilderPage::class, $first);

        try {
            $this->documents->saveDocument($first, $this->grapes('<p>late</p>'), []);
            self::fail('The save must meet the lock.');
        } catch (TransientStorageFailure $failure) {
            assert($this->kernel instanceof HttpKernelInterface);
            $event = new ExceptionEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $failure);
            $listener->onException($event);
        }

        $response = $event->getResponse();
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(409, $response->getStatusCode());
        self::assertSame('CONFLICT_002', $response->headers->get('X-Conflict-Code'));
        self::assertFalse($response->headers->has('Retry-After'), 'part was committed: no retry invitation');
        $this->releaseOther();
        self::assertSame(1, (int) $this->other()->fetchOne($this->sql('SELECT COUNT(*) FROM {page} WHERE {page.pageKey} = ?'), ['second']), 'the committed unit stays');
    }

    /**
     * REQ-RUNTIME-003 rule 4: the canvas sends the token of the document it loaded. Another editor's save committed
     * while this one waited for the lock (two processes): the save is refused with 409 PAGE_BUILDER_STALE_EDIT (no
     * `X-Conflict-Code`, the current token in the body), nothing is overwritten, and the retry with that token saves.
     */
    #[DataProvider('databases')]
    public function testAStaleCanvasSaveIsRefusedAndTheRetryWithTheCurrentTokenSaves(string $env): void
    {
        $this->boot($env, lockWait: 8);
        $this->documents->saveDocument($this->documents->createPage('home', 'Home', 'en'), $this->grapes('<p>v1</p>'), []);
        $this->em->clear();
        $loaded = $this->decode($this->handle(Request::create(self::ADMIN . '/pages/home/document')));
        self::assertIsString($loaded['token'] ?? null);

        $documentId        = $this->documentId('home');
        $structure         = $this->storedStructure('home');
        $structure['html'] = '<p>Other editor</p>';
        $child             = $this->startHolder($this->url, [
            [$this->sql('SELECT id FROM {document} WHERE id = ? FOR UPDATE'), [$documentId]],
            [$this->sql('UPDATE {document} SET {document.structure} = ? WHERE id = ?'), [json_encode($structure, JSON_THROW_ON_ERROR), $documentId]],
        ], 800);
        $stale = $this->handle($this->jsonRequest(self::ADMIN . '/pages/home/document', ['structure' => $this->grapes('<p>Mine</p>'), 'widgetPropsByLocale' => [], 'baseToken' => $loaded['token']]));
        $this->finish($child);

        self::assertSame(409, $stale->getStatusCode());
        self::assertNull($stale->headers->get('X-Conflict-Code'), 'a domain conflict is not a CONFLICT_* code');
        self::assertNull($stale->headers->get('Retry-After'));
        $body = $this->decode($stale);
        self::assertSame('PAGE_BUILDER_STALE_EDIT', $body['code'] ?? null);
        self::assertSame('stale_edit', $body['error'] ?? null);
        self::assertIsString($body['token'] ?? null);
        self::assertNotSame($loaded['token'], $body['token']);
        self::assertStringContainsString('<p>Other editor</p>', $this->storedHtml('home'), 'the committed save is not overwritten');

        $retry = $this->handle($this->jsonRequest(self::ADMIN . '/pages/home/document', ['structure' => $this->grapes('<p>Mine</p>'), 'widgetPropsByLocale' => [], 'baseToken' => $body['token']]));
        self::assertSame(200, $retry->getStatusCode());
        self::assertIsString($this->decode($retry)['token'] ?? null);
        self::assertStringContainsString('<p>Mine</p>', $this->storedHtml('home'));
    }

    /**
     * The token of a save answer is the one the next save finds under the lock, whatever the database does to the stored
     * JSON (MySQL re-orders object keys): save, then save again with the answer token gives 200 twice.
     */
    #[DataProvider('databases')]
    public function testTheTokenOfASaveAnswerIsTheOneTheNextSaveFinds(string $env): void
    {
        $this->boot($env, lockWait: 8);
        $this->documents->saveDocument($this->documents->createPage('tok', 'Tok', 'en'), $this->grapes('<p>v1</p>'), []);
        $this->em->clear();
        $structure = $this->grapes('<p>v2</p>');
        // Keys in an order MySQL's JSON type does not keep, nested, and locales in reverse order.
        $structure['grapes']        = ['zeta' => 1, 'pages' => [['name' => 'p', 'frames' => [['z' => 1, 'a' => 2]]]], 'assets' => [], 'alpha' => ['y' => 1, 'b' => 2]];
        $structure['localeContent'] = ['es' => ['html' => 's', 'css' => '', 'grapes' => ['k' => 1, 'b' => 2]], 'en' => ['html' => 'e', 'css' => '', 'grapes' => []]];

        $token = $this->decode($this->handle(Request::create(self::ADMIN . '/pages/tok/document')))['token'];
        for ($i = 1; $i <= 3; ++$i) {
            $structure['html'] = '<p>save ' . $i . '</p>';
            $response          = $this->handle($this->jsonRequest(self::ADMIN . '/pages/tok/document', ['structure' => $structure, 'widgetPropsByLocale' => [], 'baseToken' => $token]));
            self::assertSame(200, $response->getStatusCode(), 'save ' . $i . ': ' . $response->getContent());
            $token = $this->decode($response)['token'];
            $this->em->clear();
        }

        $fresh = $this->decode($this->handle(Request::create(self::ADMIN . '/pages/tok/document')))['token'];
        self::assertSame($fresh, $token, 'the answer token is the stored document\'s token');
    }

    /**
     * Rule 4, same field: two editors change the same content field to different values. The one who saves second is
     * refused (flash and redirect, nothing written); the first one's value stays.
     */
    #[DataProvider('databases')]
    public function testTheSameContentFieldChangedByTwoEditorsKeepsTheFirstValue(string $env): void
    {
        $this->boot($env, lockWait: 8);
        $this->fieldsPage('same', [['key' => 'headline', 'type' => 'string', 'label' => 'Headline'], ['key' => 'lead', 'type' => 'string', 'label' => 'Lead']]);

        $stored = $this->postStaleForm('same', ['en' => ['headline' => 'Headline of B']], ['fieldValues[en][headline]' => 'Headline of A', 'fieldValues[en][lead]' => 'Lead of A']);

        self::assertSame('Headline of B', $stored['fieldValues']['en']['headline'] ?? null, 'the other editor\'s value of the same field is not replaced');
        self::assertArrayNotHasKey('lead', $stored['fieldValues']['en'] ?? [], 'the refused save writes nothing, not even its other fields');
    }

    /**
     * Rule 4, SEO form: editor A changes the title while editor B (two processes) changed the meta title: both are
     * kept (the form's unchanged fields never write the old values back).
     */
    #[DataProvider('databases')]
    public function testTheSeoFormKeepsAnotherEditorsChangeToAnotherField(string $env): void
    {
        $this->boot($env, lockWait: 8);
        $this->fieldsPage('seo', []);

        $stored = $this->postSeoForm('seo', 'Meta of B', ['builder_page_seo[title]' => 'Title of A']);

        self::assertSame('Title of A', $stored['title']);
        self::assertSame('Meta of B', $stored['meta_title'], 'the other editor\'s field is kept');
    }

    /**
     * Rule 4, SEO form, same field: both editors changed the meta title to different values; the second save is refused
     * as a whole and the first value stays.
     */
    #[DataProvider('databases')]
    public function testTheSeoFormRefusesTheSameFieldChangedByTwoEditors(string $env): void
    {
        $this->boot($env, lockWait: 8);
        $this->fieldsPage('seo2', []);

        $stored = $this->postSeoForm('seo2', 'Meta of B', ['builder_page_seo[metaTitle]' => 'Meta of A', 'builder_page_seo[title]' => 'Title of A']);

        self::assertSame('Meta of B', $stored['meta_title']);
        self::assertSame('Page seo2', $stored['title'], 'nothing of the refused save is written');
    }

    /**
     * @param array<string, mixed> $bundle
     */
    private function boot(string $env, array $bundle = [], int $lockWait = 1): string
    {
        $host = str_ends_with($env, self::HOST);
        $env  = $host ? substr($env, 0, -strlen(self::HOST)) : $env;
        if ($host) {
            $bundle['doctrine'] = ['transaction' => 'host'];
        }

        $url = getenv($env);
        if (!is_string($url) || $url === '') {
            self::markTestSkipped($env . ' is not set (make test-with-db).');
        }

        $this->url          = $url;
        $this->adminUseCase = null;
        $this->kernel       = new AdminHttpKernel($bundle, self::class . '|' . $env . ($host ? self::HOST : ''), $url);
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        assert($container instanceof ContainerInterface);
        $this->container = $container;
        // The bundle's manager: the default one, or the private one (same connection and tables).
        $em = $container->get('nowo_page_builder_kit.entity_manager');
        assert($em instanceof EntityManagerInterface);
        $this->em = $em;
        $schema   = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        DatabaseUrl::limitLockWait($em->getConnection(), $lockWait);
        $documents = $container->get(DocumentService::class);
        assert($documents instanceof DocumentService);
        $this->documents = $documents;
        if ($host) {
            $this->adminUseCase = Request::create(self::ADMIN . '/pages');
            $this->adminUseCase->attributes->set('_controller', PageDocumentApiController::class . '::saveDocument');
            $this->requestStack()->push($this->adminUseCase);
        }

        return $url;
    }

    /**
     * @param list<array<string, mixed>> $fields
     */
    private function fieldsPage(string $pageKey, array $fields): void
    {
        $page = $this->documents->createPage($pageKey, 'Page ' . $pageKey, 'en');
        $this->documents->saveDocument($page, $this->grapes('<p>' . $pageKey . '</p>', $fields), []);
        $this->em->clear();
    }

    /**
     * Editor A reads the en content form; editor B (a child process) then commits $otherValues under the lock and
     * holds it while A posts the form it read, with $changes applied (as a browser would post it).
     *
     * @param array<string, array<string, mixed>> $otherValues locale => key => value committed by B
     * @param array<string, string> $changes field name => new value
     *
     * @return array<string, mixed> the stored structure
     */
    private function postStaleForm(string $pageKey, array $otherValues, array $changes): array
    {
        $form = $this->handle(Request::create(self::ADMIN . '/pages/' . $pageKey . '/content?locale=en'));
        self::assertSame(200, $form->getStatusCode());
        $fields = $this->formFields((string) $form->getContent());
        foreach ($changes as $name => $value) {
            $fields[] = [$name, $value];
        }

        $documentId = $this->documentId($pageKey);
        $structure  = $this->storedStructure($pageKey);
        foreach ($otherValues as $locale => $bag) {
            foreach ($bag as $key => $value) {
                $structure['fieldValues'][$locale][$key] = $value;
            }
        }

        $child = $this->startHolder($this->url, [
            [$this->sql('SELECT id FROM {document} WHERE id = ? FOR UPDATE'), [$documentId]],
            [$this->sql('UPDATE {document} SET {document.structure} = ? WHERE id = ?'), [json_encode($structure, JSON_THROW_ON_ERROR), $documentId]],
        ], 800);
        $query = '';
        foreach ($fields as [$name, $value]) {
            $query .= '&' . rawurlencode($name) . '=' . rawurlencode($value);
        }
        parse_str($query, $parameters);
        $response = $this->handle(Request::create(self::ADMIN . '/pages/' . $pageKey . '/content?locale=en', 'POST', $parameters));
        $this->finish($child);
        self::assertSame(302, $response->getStatusCode());

        return $this->storedStructure($pageKey);
    }

    /**
     * Editor A reads the en SEO form; editor B (a child process) then commits a new meta title under the lock and holds
     * it while A posts the form it read, with $changes applied.
     *
     * @param array<string, string> $changes field name => new value
     *
     * @return array<string, string|null> the stored translation (title, meta_title)
     */
    private function postSeoForm(string $pageKey, string $otherMetaTitle, array $changes): array
    {
        $form = $this->handle(Request::create(self::ADMIN . '/pages/' . $pageKey . '/seo?locale=en'));
        self::assertSame(200, $form->getStatusCode());
        $fields = $this->formFields((string) $form->getContent());
        foreach ($changes as $name => $value) {
            $fields[] = [$name, $value];
        }

        $documentId = $this->documentId($pageKey);
        $pageId     = (int) $this->other()->fetchOne($this->sql('SELECT id FROM {page} WHERE {page.pageKey} = ?'), [$pageKey]);
        $child      = $this->startHolder($this->url, [
            [$this->sql('SELECT id FROM {document} WHERE id = ? FOR UPDATE'), [$documentId]],
            [$this->sql('UPDATE {translation} SET {translation.metaTitle} = ? WHERE {translation.page} = ?'), [$otherMetaTitle, $pageId]],
        ], 800);
        $query = '';
        foreach ($fields as [$name, $value]) {
            $query .= '&' . rawurlencode($name) . '=' . rawurlencode($value);
        }
        parse_str($query, $parameters);
        $response = $this->handle(Request::create(self::ADMIN . '/pages/' . $pageKey . '/seo?locale=en', 'POST', $parameters));
        $this->finish($child);
        self::assertSame(302, $response->getStatusCode());

        $row = $this->other()->fetchAssociative($this->sql('SELECT {translation.title} AS title, {translation.metaTitle} AS meta_title FROM {translation} WHERE {translation.page} = ?'), [$pageId]);
        assert(is_array($row));

        /* @var array<string, string|null> $row */
        return $row;
    }

    /**
     * The inputs a browser posts from the page: hidden and text inputs, checked checkboxes; a later entry for the
     * same name wins (parse_str).
     *
     * @return list<array{0: string, 1: string}>
     */
    private function formFields(string $html): array
    {
        $fields = [];
        preg_match_all('/<input\b[^>]*>/i', $html, $inputs);
        foreach ($inputs[0] as $input) {
            preg_match_all('/([\w-]+)="([^"]*)"/', $input, $attributes, PREG_SET_ORDER);
            $attr = [];
            foreach ($attributes as $attribute) {
                $attr[strtolower($attribute[1])] = html_entity_decode($attribute[2], ENT_QUOTES | ENT_HTML5);
            }
            $type = $attr['type'] ?? 'text';
            if (!isset($attr['name']) || ($type === 'checkbox' && !str_contains($input, 'checked')) || in_array($type, ['submit', 'button', 'file'], true)) {
                continue;
            }
            $fields[] = [$attr['name'], $type === 'checkbox' ? ($attr['value'] ?? '1') : ($attr['value'] ?? '')];
        }

        return $fields;
    }

    private function other(): Connection
    {
        if (!$this->other instanceof Connection) {
            $this->other = DatabaseUrl::connect($this->url);
            DatabaseUrl::limitLockWait($this->other, 5);
        }

        return $this->other;
    }

    /** Another session takes the page's document row lock and keeps its transaction open. */
    private function holdDocumentLock(string $pageKey): void
    {
        $id = $this->documentId($pageKey);
        $this->other()->beginTransaction();
        $this->other()->executeQuery($this->sql('SELECT id FROM {document} WHERE id = ? FOR UPDATE'), [$id])->fetchAllAssociative();
    }

    private function releaseOther(): void
    {
        if ($this->other()->isTransactionActive()) {
            $this->other()->rollBack();
        }
    }

    private function documentId(string $pageKey): int
    {
        return (int) $this->other()->fetchOne($this->sql('SELECT d.id FROM {document} d JOIN {page} p ON p.id = d.{document.page} WHERE p.{page.pageKey} = ?'), [$pageKey]);
    }

    /**
     * Table and column names from the mapping ({table}, {table.field}), whatever the naming strategy.
     */
    private function sql(string $sql): string
    {
        $classes = ['page' => BuilderPage::class, 'document' => BuilderDocument::class, 'translation' => BuilderPageTranslation::class];

        return (string) preg_replace_callback('/\{(page|document|translation)(?:\.(\w+))?\}/', function (array $m) use ($classes): string {
            $metadata = $this->em->getClassMetadata($classes[$m[1]]);
            if (!isset($m[2])) {
                return $metadata->getTableName();
            }

            return $metadata->hasAssociation($m[2]) ? $metadata->getSingleAssociationJoinColumnName($m[2]) : $metadata->getColumnName($m[2]);
        }, $sql);
    }

    /**
     * @return array<string, mixed>
     */
    private function storedStructure(string $pageKey): array
    {
        $json = $this->other()->fetchOne($this->sql('SELECT d.{document.structure} FROM {document} d JOIN {page} p ON p.id = d.{document.page} WHERE p.{page.pageKey} = ?'), [$pageKey]);
        assert(is_string($json));
        $structure = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($structure));

        /* @var array<string, mixed> $structure */
        return $structure;
    }

    private function storedHtml(string $pageKey): string
    {
        $html = $this->storedStructure($pageKey)['html'] ?? '';

        return is_string($html) ? $html : '';
    }

    /**
     * A child process opens a transaction, runs the statements, signals, holds the transaction for $holdMs, commits.
     *
     * @param list<array{0: string, 1: list<mixed>}> $statements
     *
     * @return array{0: resource, 1: array<int, resource>}
     */
    private function startHolder(string $url, array $statements, int $holdMs): array
    {
        $signal = tempnam(sys_get_temp_dir(), 'pbk-race');
        assert(is_string($signal));
        unlink($signal);
        $config = json_encode(['url' => $url, 'statements' => $statements, 'signal' => $signal, 'hold_ms' => $holdMs], JSON_THROW_ON_ERROR);
        // Bounded: the parent waits at most 15 s for the signal, and the child's own lock waits are 10 s.
        // @phpstan-ignore frankenphp.classic.noUnlimitedIoTimeout
        $process = proc_open([PHP_BINARY, __DIR__ . '/../Database/Fixtures/hold-and-commit.php', $config], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        assert(is_resource($process));

        $deadline = microtime(true) + 15;
        while (!file_exists($signal)) {
            $status = proc_get_status($process);
            if (!$status['running'] || microtime(true) > $deadline) {
                self::fail('The second session did not take its lock: ' . stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]));
            }
            usleep(20_000);
        }
        unlink($signal);

        return [$process, $pipes];
    }

    /**
     * @param array{0: resource, 1: array<int, resource>} $child
     */
    private function finish(array $child): void
    {
        [$process, $pipes] = $child;
        $errors            = (string) stream_get_contents($pipes[2]);
        self::assertSame(0, proc_close($process), 'second session: ' . $errors);
    }

    private function saveThroughApi(string $pageKey, string $html): Response
    {
        return $this->handle($this->jsonRequest(self::ADMIN . '/pages/' . $pageKey . '/document', ['structure' => $this->grapes($html), 'widgetPropsByLocale' => []]));
    }

    /**
     * @param array<string, mixed> $json
     */
    private function jsonRequest(string $uri, array $json): Request
    {
        return Request::create($uri, 'POST', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_CSRF_TOKEN' => 't'], content: json_encode($json, JSON_THROW_ON_ERROR));
    }

    private function handle(Request $request): Response
    {
        assert($this->kernel instanceof AdminHttpKernel);
        // A real request of its own: the admin use case of the direct service calls is taken off meanwhile.
        if ($this->adminUseCase instanceof Request) {
            $this->requestStack()->pop();
        }

        try {
            $response = $this->kernel->handle($request);
            $this->kernel->terminate($request, $response);
        } finally {
            if ($this->adminUseCase instanceof Request) {
                $this->requestStack()->push($this->adminUseCase);
            }
        }

        return $response;
    }

    private function requestStack(): RequestStack
    {
        $stack = $this->container->get('request_stack');
        assert($stack instanceof RequestStack);

        return $stack;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        self::assertInstanceOf(JsonResponse::class, $response);
        $data = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($data));

        /* @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @param list<array<string, mixed>> $fields
     *
     * @return array<string, mixed>
     */
    private function grapes(string $html = '<p>Hi</p>', array $fields = []): array
    {
        return ['version' => 2, 'engine' => 'grapesjs', 'html' => $html, 'css' => '', 'grapes' => [], 'localeContent' => [], 'fields' => $fields, 'fieldValues' => []];
    }
}
