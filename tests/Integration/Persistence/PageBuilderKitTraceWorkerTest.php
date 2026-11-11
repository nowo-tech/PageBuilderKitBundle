<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\Persistence;

use Nowo\PageBuilderKitBundle\Debug\PageBuilderKitTrace;
use Nowo\PageBuilderKitBundle\Debug\PageBuilderKitTraceBoundarySubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\Service\ResetInterface;

use function dirname;
use function gc_collect_cycles;
use function memory_get_usage;
use function sys_get_temp_dir;

/**
 * REQ-RUNTIME-002 (1.14.1): the debug trace in ONE kept-alive kernel with the services resetter never called
 * (`http_kernel` driven directly, Messenger `--no-reset`, long-running console). Two users alternate over 1,000
 * requests and 1,000 messages; each unit of work sees only its own trace entries and memory stays flat.
 */
#[CoversClass(PageBuilderKitTraceBoundarySubscriber::class)]
final class PageBuilderKitTraceWorkerTest extends TestCase
{
    private const int ITERATIONS = 1000;

    private const int MEMORY_MARGIN = 4 * 1024 * 1024;

    private const string RECEIVED = 'Symfony\\Component\\Messenger\\Event\\WorkerMessageReceivedEvent';

    private const string HANDLED = 'Symfony\\Component\\Messenger\\Event\\WorkerMessageHandledEvent';

    private const string FAILED = 'Symfony\\Component\\Messenger\\Event\\WorkerMessageFailedEvent';

    private ?PersistenceTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    #[Test]
    public function httpRequestsWithoutTheResetterSeeOnlyTheirOwnTrace(): void
    {
        $container = $this->boot();
        $kernel    = $container->get('http_kernel');
        self::assertInstanceOf(HttpKernel::class, $kernel);

        $baseline = 0;
        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $user     = $i % 2 === 0 ? 'user-a' : 'user-b';
            $request  = Request::create('/t/trace/' . $user);
            $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
            self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
            self::assertSame($user, $response->getContent(), 'request ' . $i . ' sees only its own trace');
            $kernel->terminate($request, $response);
            $this->resetSymfonyDebugServices($container);
            if ($i === 50) {
                $baseline = $this->collectedMemory();
            }
        }

        self::assertLessThan(self::MEMORY_MARGIN, $this->collectedMemory() - $baseline, 'memory stays flat over many requests');
    }

    #[Test]
    public function messengerAndConsoleBoundariesClearTheTrace(): void
    {
        $container  = $this->boot();
        $dispatcher = $container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $trace = $container->get(PageBuilderKitTrace::class);
        self::assertInstanceOf(PageBuilderKitTrace::class, $trace);

        $baseline = 0;
        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $user = $i % 2 === 0 ? 'user-a' : 'user-b';
            $dispatcher->dispatch(new stdClass(), self::RECEIVED);
            self::assertSame([], $trace->getPublicOutcomes(), 'message ' . $i . ' starts with an empty trace');
            $trace->addPublicOutcome($user, 'published', 'en');
            $trace->addAdminAction('save', $user);
            $dispatcher->dispatch(new stdClass(), $i % 3 === 0 ? self::FAILED : self::HANDLED);
            self::assertSame([], $trace->getPublicOutcomes(), 'message ' . $i . ' leaves nothing for the next one');
            self::assertSame([], $trace->getAdminActions());
            $this->resetSymfonyDebugServices($container);
            if ($i === 50) {
                $baseline = $this->collectedMemory();
            }
        }

        self::assertLessThan(self::MEMORY_MARGIN, $this->collectedMemory() - $baseline, 'memory stays flat over many messages');

        $trace->addRender(['pageKey' => 'cli', 'locale' => 'en', 'status' => 'published', 'engine' => 'grapesjs']);
        $dispatcher->dispatch(new ConsoleTerminateEvent(new Command('app:long-running'), new ArrayInput([]), new NullOutput(), 0), ConsoleEvents::TERMINATE);
        self::assertSame([], $trace->getRenders(), 'a long-running command clears the trace when it ends');
    }

    /**
     * The kernel runs in debug (the trace only exists there). Symfony's own debug services (traceable dispatcher call
     * stack, stopwatch) grow per request by design and are cleared only by the services resetter; they are cleared
     * here so the memory margin measures the bundle. The bundle trace itself is never reset by the test.
     */
    private function resetSymfonyDebugServices(ContainerInterface $container): void
    {
        foreach (['event_dispatcher', 'debug.stopwatch'] as $id) {
            $service = $container->has($id) ? $container->get($id) : null;
            if ($service instanceof ResetInterface) {
                $service->reset();
            }
        }
    }

    /**
     * Memory after a cycle collection: request / event objects form reference cycles that PHP frees only when the
     * collector runs, so the raw figure depends on GC timing, not on retained state.
     */
    private function collectedMemory(): int
    {
        gc_collect_cycles();

        return memory_get_usage();
    }

    private function boot(): ContainerInterface
    {
        $this->kernel = new PersistenceTestKernel(
            [
                'dbal' => ['url' => 'sqlite:///' . sys_get_temp_dir() . '/nowo-page-builder-kit-trace.sqlite'],
                'orm'  => ['mappings' => [
                    'Host' => [
                        'type'      => 'attribute',
                        'dir'       => dirname(__DIR__, 2) . '/Fixtures/Host',
                        'prefix'    => 'Nowo\\PageBuilderKitBundle\\Tests\\Fixtures\\Host',
                        'is_bundle' => false,
                    ],
                ]],
            ],
            debugCollector: true,
        );
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }
}
