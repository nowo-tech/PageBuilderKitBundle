<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Nowo\PageBuilderKitBundle\DependencyInjection\NowoPageBuilderKitExtension;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageRevision;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTemplate;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTranslation;
use Nowo\PageBuilderKitBundle\Event\PagePublishedEvent;
use Nowo\PageBuilderKitBundle\Service\ContentFieldsService;
use Nowo\PageBuilderKitBundle\Service\DocumentService;
use Nowo\PageBuilderKitBundle\Service\PagePublisherInterface;
use Nowo\PageBuilderKitBundle\Service\PageRevisionService;
use Nowo\PageBuilderKitBundle\Service\PageTemplateService;
use Nowo\PageBuilderKitBundle\Tests\Fixtures\Host\HostNote;
use Nowo\PageBuilderKitBundle\Tests\Fixtures\HostRef\HostPageLink;
use Nowo\TransactionDoctrine\PrivateManager\PrivateEntityManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

use function base64_decode;
use function file_put_contents;
use function json_decode;
use function json_encode;
use function preg_replace_callback;
use function restore_error_handler;
use function set_error_handler;
use function str_contains;
use function str_replace;
use function strtoupper;
use function sys_get_temp_dir;
use function uniqid;

use const E_USER_DEPRECATED;
use const JSON_THROW_ON_ERROR;

/**
 * Since 2.0 (REQ-ARCH-001 rule 6), THE data-loss test of the flip, through a real kernel with ZERO Doctrine
 * configuration for the bundle (no `private_entity_manager`, no `entity_manager`: the 1.x default, where every page
 * builder write flushed your default manager) and real database writes, nothing mocked.
 *
 * Your application leaves changes pending on its own manager (a new entity scheduled for insertion, an edited managed
 * entity, an entity persisted by your listener of {@see PagePublishedEvent} — the "Beacon pattern" — and an entity of
 * yours pointing at a page through `$yourManager->getReference()`), then the bundle writes through every path: the
 * publisher port, a page save, a publish, the admin's document save, publish and ASSET UPLOAD requests, revisions (snapshot
 * and restore), templates (save and create a page from one), content fields, the SEO translation flush, writes handed a
 * page YOUR manager loaded (W1-2), a failed write, and request / Messenger boundaries. The test proves that:
 *
 * - every page builder write is committed, through the bundle's private manager (never through yours);
 * - your manager is never flushed by the bundle (no flush event of it at all), never cleared, never closed;
 * - each of your pending changes is still pending afterwards, with its change set intact, and none reached the
 *   database: in 1.x every one of them would have been written by the first page builder write;
 * - your own flush then writes all of them, and does not undo the bundle's writes (no page row inserted again, no
 *   status written back by a page your manager still holds);
 * - no deprecation is triggered (the 1.19 notices and host-flush probe are gone).
 *
 * Runs on SQLite ({@see HostPendingChangesKernelTest}) and on the real servers of the database group
 * (`tests/Integration/Database/HostPendingChangesDatabaseTest`: MySQL 8.4, MariaDB 11.4, PostgreSQL 16).
 */
abstract class HostPendingChangesTestCase extends TestCase
{
    private const string ADMIN = '/admin/page-builder';

    private ?HostPendingChangesKernel $kernel = null;

    private string $directory = '';

    private ?string $url = null;

    /** @var list<string> */
    private array $deprecations = [];

    /** Null: SQLite in the test's directory. */
    abstract protected function databaseUrl(): ?string;

    protected function tearDown(): void
    {
        if ($this->kernel instanceof HostPendingChangesKernel && $this->url !== null) {
            // A real server keeps its tables: leave none of this test's behind.
            $manager = $this->manager($this->container());
            new SchemaTool($manager)->dropSchema($manager->getMetadataFactory()->getAllMetadata());
        }

        $this->kernel?->shutdown();
        $this->kernel = null;
        if ($this->directory !== '') {
            new Filesystem()->remove($this->directory);
        }
    }

    #[Test]
    public function pageBuilderWritesNeverFlushYourPendingChangesAndYourFlushStillWritesThem(): void
    {
        $container  = $this->boot();
        $default    = $this->manager($container);
        $connection = $default->getConnection();

        // The bundle's entity manager is its private one: zero configuration, no notice.
        $private = $this->service($container, NowoPageBuilderKitExtension::ENTITY_MANAGER_SERVICE, EntityManagerInterface::class);
        self::assertInstanceOf(PrivateEntityManager::class, $private);
        self::assertNotSame($default, $private);

        $documents = $this->service($container, DocumentService::class, DocumentService::class);
        $publisher = $this->service($container, PagePublisherInterface::class, PagePublisherInterface::class);
        $revisions = $this->service($container, PageRevisionService::class, PageRevisionService::class);
        $templates = $this->service($container, PageTemplateService::class, PageTemplateService::class);
        $fields    = $this->service($container, ContentFieldsService::class, ContentFieldsService::class);

        // Seed: pages written by the bundle, a row of yours flushed by you.
        $homeId = (int) $documents->createPage('home', 'Home', 'en')->getId();
        $publisher->createPageByKey('about', 'About', 'en');
        $aboutId = (int) $documents->loadPageByKey('about')?->getId();
        $stored  = new HostNote('stored');
        $default->persist($stored);
        $default->flush();
        $storedId = (int) $stored->id;

        // Counts every flush of YOUR manager (the private one shares its event manager, so it is told apart).
        $yourFlushes = new class($default) {
            public int $count = 0;

            public function __construct(private readonly EntityManagerInterface $yours)
            {
            }

            public function onFlush(OnFlushEventArgs $args): void
            {
                if ($args->getObjectManager() === $this->yours) {
                    ++$this->count;
                }
            }
        };
        $default->getEventManager()->addEventListener([Events::onFlush], $yourFlushes);

        // Your pending changes: a new entity, an edited managed one, one your listener of a page builder event persists,
        // and an entity of yours pointing at a page through YOUR manager (W0-9 / W1-2).
        $note = new HostNote('pending host change');
        $default->persist($note);
        $stored->text = 'edited, not flushed';
        $fromListener = new HostNote('persisted by your listener');
        $dispatcher   = $container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $dispatcher->addListener(PagePublishedEvent::class, static function () use ($default, $fromListener): void {
            $default->persist($fromListener);
        });
        $link = new HostPageLink($default->getReference(BuilderPage::class, $homeId) ?? throw new LogicException('reference'));
        $default->persist($link);
        // A page YOUR manager loaded before the bundle publishes it: your flush must not write its old status back.
        $staleAbout = $default->find(BuilderPage::class, $aboutId);
        self::assertInstanceOf(BuilderPage::class, $staleAbout);
        self::assertSame('draft', $staleAbout->getStatus()->value);

        $this->collectDeprecations(function () use ($container, $default, $private, $documents, $publisher, $revisions, $templates, $fields, $homeId, $dispatcher): void {
            // The publisher port and a page save, publish of the service.
            $publisher->saveDocumentByKey('about', $this->grapes('<p>About v1</p>'));
            $publisher->publishByKey('about');
            $home = $documents->loadPageByKey('home');
            self::assertInstanceOf(BuilderPage::class, $home);
            $documents->saveDocument($home, $this->grapes('<p>Home v1</p>'), []);

            // The admin: document save, publish and an ASSET UPLOAD (kept-alive kernel: handle + terminate).
            $this->assertOk($this->request($container, 'POST', self::ADMIN . '/pages/home/document', [], ['X-CSRF-TOKEN' => 'x'], ['structure' => $this->grapes('<p>Home v2</p>'), 'widgetPropsByLocale' => []]));
            $this->assertOk($this->request($container, 'POST', self::ADMIN . '/pages/home/publish', ['_csrf_token' => 'x'], ['Accept' => 'application/json']));
            $png = $this->directory . '/upload.png';
            file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true));
            $upload = $this->request($container, 'POST', self::ADMIN . '/assets/upload', [], ['X-CSRF-TOKEN' => 'x'], null, ['files' => [new UploadedFile($png, 'hero.png', 'image/png', null, true)]]);
            $this->assertOk($upload);
            self::assertStringStartsWith('/uploads/', (string) (json_decode((string) $upload->getContent(), true, 512, JSON_THROW_ON_ERROR)['data'][0]['src'] ?? ''));

            // Revisions (a snapshot and a restore), templates, content fields and the SEO translation flush.
            $home = $documents->loadPageByKey('home');
            self::assertInstanceOf(BuilderPage::class, $home);
            $revision = $revisions->create($home, 'manual');
            self::assertInstanceOf(BuilderPageRevision::class, $revision);
            $revisions->restore($home, (int) $revision->getId());
            $templates->saveFromPage($home, 'landing', 'Landing');
            $templates->createPageFromTemplate('landing', 'from-template', 'From template', 'en');
            $fields->saveSchemaWithField($home, ['key' => 'cta', 'type' => 'string', 'label' => 'CTA']);
            $fields->saveFieldValue($home, 'cta', 'en', 'Go', ['type' => 'string', 'label' => 'CTA']);
            $home = $documents->loadPageByKey('home');
            self::assertInstanceOf(BuilderPage::class, $home);
            $translation = $home->getTranslation('en');
            self::assertInstanceOf(BuilderPageTranslation::class, $translation);
            $translation->setMetaTitle('Home meta');
            $documents->flushPage($home);

            // W1-2: a page YOUR manager loaded, handed to the bundle's writes: re-loaded by id, never inserted again.
            $yours = $default->find(BuilderPage::class, $homeId);
            self::assertInstanceOf(BuilderPage::class, $yours);
            self::assertFalse($private->contains($yours));
            $documents->unpublish($yours);
            $documents->publish($yours);
            $documents->saveDocument($yours, $this->grapes('<p>Home v3</p>'), []);
            $fields->saveFieldValue($yours, 'cta', 'es', 'Vamos', ['type' => 'string', 'label' => 'CTA']);
            // ... but a flush of the changes YOU made to it is refused before anything is written.
            try {
                $documents->flushPage($yours);
                self::fail('A page your manager holds must be refused.');
            } catch (LogicException $exception) {
                self::assertStringContainsString('The page "home" was not loaded through NowoPageBuilderKitBundle', $exception->getMessage());
            }

            // A failed page builder write: rolled back, only the private manager is dropped.
            try {
                $documents->unit(static function () use ($documents): never {
                    $documents->createPage('rolled-back', 'Rolled back', 'en');

                    throw new RuntimeException('the page builder write fails');
                });
                self::fail('The write must fail.'); // @phpstan-ignore deadCode.unreachable (the guard of the test)
            } catch (RuntimeException $exception) {
                self::assertSame('the page builder write fails', $exception->getMessage());
            }

            // A Messenger message (received, handled): the bundle drops its private manager there, never yours.
            $envelope = new Envelope(new stdClass());
            $dispatcher->dispatch(new WorkerMessageReceivedEvent($envelope, 'async'));
            $documents->publish($documents->loadPageByKey('from-template') ?? throw new LogicException('from-template'));
            $dispatcher->dispatch(new WorkerMessageHandledEvent($envelope, 'async'));
            self::assertFalse($private->isCreated(), 'the private manager is dropped at the end of the message');
        });

        self::assertSame([], $this->deprecations, 'no notice and no host-flush probe in 2.0');

        // Every page builder write is committed.
        $page = fn (string $key, string $field): mixed => $connection->fetchOne($this->sql($default, 'SELECT {' . $field . '} FROM pb_page WHERE {pageKey} = ?'), [$key]);
        self::assertSame(['about', 'from-template', 'home'], $connection->fetchFirstColumn($this->sql($default, 'SELECT {pageKey} FROM pb_page ORDER BY {pageKey}')), 'no page inserted twice, the rolled-back one is gone');
        self::assertSame('published', $page('home', 'status'));
        self::assertSame('published', $page('about', 'status'));
        self::assertSame('published', $page('from-template', 'status'));
        $html = (string) $connection->fetchOne($this->sql($default, 'SELECT d.{structure} FROM pb_document d', BuilderDocument::class) . ' JOIN pb_page p ON p.id = d.page_id WHERE p.' . $this->sql($default, '{pageKey}') . ' = ?', ['home']);
        self::assertStringContainsString('Home v3', $html);
        self::assertStringContainsString('cta', $html, 'the content field is stored');
        self::assertGreaterThan(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM pb_page_revision'));
        self::assertSame(1, (int) $connection->fetchOne($this->sql($default, 'SELECT COUNT(*) FROM pb_page_template WHERE {templateKey} = ?', BuilderPageTemplate::class), ['landing']));
        self::assertSame('Home meta', $connection->fetchOne($this->sql($default, 'SELECT {metaTitle} FROM pb_page_translation WHERE {locale} = ?', BuilderPageTranslation::class) . ' AND page_id = ?', ['en', $homeId]));

        // Your manager was never flushed, cleared or closed by the bundle, and none of your changes reached the database.
        self::assertSame(0, $yourFlushes->count, 'the bundle never flushed your manager');
        self::assertTrue($default->isOpen());
        self::assertSame(['stored'], $connection->fetchFirstColumn('SELECT text FROM host_note'), 'no pending entity of yours was written, nor your edit');
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM host_page_link'));

        // ...and each of them is still pending, intact.
        $unitOfWork = $default->getUnitOfWork();
        self::assertTrue($unitOfWork->isScheduledForInsert($note), 'your new entity is still scheduled');
        self::assertTrue($unitOfWork->isScheduledForInsert($fromListener), 'the entity your listener persisted is still scheduled');
        self::assertTrue($unitOfWork->isScheduledForInsert($link), 'your entity pointing at a page is still scheduled');
        self::assertTrue($default->contains($stored));
        $unitOfWork->computeChangeSets();
        self::assertSame(['stored', 'edited, not flushed'], $unitOfWork->getEntityChangeSet($stored)['text'] ?? null, 'your edit is still pending');
        self::assertFalse($unitOfWork->isScheduledForUpdate($staleAbout), 'a page your manager holds has no pending change');

        // Your own flush writes them all and undoes nothing of the bundle's writes.
        $default->flush();
        self::assertSame(1, $yourFlushes->count);
        self::assertSame(['edited, not flushed', 'pending host change', 'persisted by your listener'], $connection->fetchFirstColumn('SELECT text FROM host_note ORDER BY text'));
        self::assertSame($storedId, (int) $connection->fetchOne('SELECT id FROM host_note WHERE text = ?', ['edited, not flushed']));
        self::assertSame($homeId, (int) $connection->fetchOne('SELECT page_id FROM host_page_link'), 'your getReference() wrote the foreign key');
        self::assertSame(3, (int) $connection->fetchOne('SELECT COUNT(*) FROM pb_page'), 'your flush inserts no page again');
        self::assertSame('published', $page('about', 'status'), 'nor writes back the status of the page your manager held');
    }

    private function boot(): ContainerInterface
    {
        $this->directory = sys_get_temp_dir() . '/nowo-page-builder-kit-host-pending-' . uniqid('', true);
        new Filesystem()->mkdir($this->directory);
        $this->url    = $this->databaseUrl();
        $kernel       = new HostPendingChangesKernel($this->directory, $this->url);
        $this->kernel = $kernel;
        $this->collectDeprecations(static fn () => $kernel->boot());
        self::assertSame([], $this->deprecations, 'zero configuration: no notice');
        $container = $this->container();

        $manager  = $this->manager($container);
        $tool     = new SchemaTool($manager);
        $metadata = $manager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        return $container;
    }

    private function container(): ContainerInterface
    {
        self::assertInstanceOf(HostPendingChangesKernel::class, $this->kernel);
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    private function manager(ContainerInterface $container): EntityManagerInterface
    {
        $doctrine = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $doctrine);
        $manager = $doctrine->getManager('default');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(ContainerInterface $container, string $id, string $class): object
    {
        $service = $container->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }

    /**
     * Collects this bundle's deprecations (and any host-flush probe line) while `$run` runs.
     */
    private function collectDeprecations(callable $run): void
    {
        // @phpstan-ignore frankenphp.worker.noSetErrorExceptionHandler (test only: restored right after)
        set_error_handler(function (int $type, string $message): bool {
            if (str_contains($message, 'page-builder-kit') || str_contains($message, 'NowoPageBuilderKitBundle') || str_contains($message, 'flushed by')) {
                $this->deprecations[] = $message;
            }

            return true;
        }, E_USER_DEPRECATED);

        try {
            $run();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * One main request of a kept-alive kernel (handle + terminate).
     *
     * @param array<string, mixed> $parameters
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $json
     * @param array<string, mixed> $files
     */
    private function request(ContainerInterface $container, string $method, string $uri, array $parameters = [], array $headers = [], ?array $json = null, array $files = []): Response
    {
        $kernel = $container->get('http_kernel');
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);
        $server = [];
        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }
        $content = null;
        if ($json !== null) {
            $content                = json_encode($json, JSON_THROW_ON_ERROR);
            $server['CONTENT_TYPE'] = 'application/json';
        }
        $request  = Request::create($uri, $method, $parameters, [], $files, $server, $content);
        $response = $kernel->handle($request);
        if ($kernel instanceof TerminableInterface) {
            $kernel->terminate($request, $response);
        }

        return $response;
    }

    private function assertOk(Response $response): void
    {
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    }

    /**
     * Replaces `{field}` with the column of that field of $class (the host's naming strategy).
     *
     * @param class-string $class
     */
    private function sql(EntityManagerInterface $manager, string $sql, string $class = BuilderPage::class): string
    {
        $metadata = $manager->getClassMetadata($class);

        return (string) preg_replace_callback('/\{(\w+)\}/', static fn (array $m): string => $metadata->getColumnName($m[1]), $sql);
    }

    /**
     * @return array<string, mixed>
     */
    private function grapes(string $html): array
    {
        return ['version' => 2, 'engine' => 'grapesjs', 'html' => $html, 'css' => '', 'grapes' => [], 'localeContent' => [], 'fields' => [], 'fieldValues' => []];
    }
}
