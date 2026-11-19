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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use function assert;
use function file_exists;
use function getenv;
use function is_array;
use function is_resource;
use function is_string;
use function json_decode;
use function json_encode;
use function microtime;
use function preg_replace_callback;
use function proc_close;
use function proc_open;
use function stream_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use function usleep;

use const JSON_THROW_ON_ERROR;
use const PHP_BINARY;

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
     * @return iterable<string, array{0: string}>
     */
    public static function databases(): iterable
    {
        yield 'MySQL 8.4' => ['PBK_MYSQL_URL'];
        yield 'MariaDB 11.4' => ['PBK_MARIADB_URL'];
        yield 'PostgreSQL 16' => ['PBK_POSTGRES_URL'];
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
            $this->sql('INSERT INTO {page} ({page.uuid}, {page.pageKey}, {page.status}, {page.createdAt}, {page.updatedAt}) VALUES (?, ?, ?, ?, ?)'),
            ['00000000-0000-4000-8000-0000000000aa', 'race', 'draft', '2026-11-19 10:00:00', '2026-11-19 10:00:00'],
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
     * @param array<string, mixed> $bundle
     */
    private function boot(string $env, array $bundle = [], int $lockWait = 1): string
    {
        $url = getenv($env);
        if (!is_string($url) || $url === '') {
            self::markTestSkipped($env . ' is not set (make test-with-db).');
        }

        $this->url    = $url;
        $this->kernel = new AdminHttpKernel($bundle, self::class . '|' . $env, $url);
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        assert($container instanceof ContainerInterface);
        $this->container = $container;
        $em              = $container->get('doctrine.orm.entity_manager');
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

        return $url;
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
        $classes = ['page' => BuilderPage::class, 'document' => BuilderDocument::class];

        return (string) preg_replace_callback('/\{(page|document)(?:\.(\w+))?\}/', function (array $m) use ($classes): string {
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
            if (microtime(true) > $deadline) {
                self::fail('The second session did not take its lock: ' . stream_get_contents($pipes[2]));
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
        $response = $this->kernel->handle($request);
        $this->kernel->terminate($request, $response);

        return $response;
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
