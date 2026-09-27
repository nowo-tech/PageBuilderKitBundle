<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\DataCollector;

use Nowo\PageBuilderKitBundle\DataCollector\PageBuilderKitDataCollector;
use Nowo\PageBuilderKitBundle\Debug\PageBuilderKitTrace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(PageBuilderKitDataCollector::class)]
final class PageBuilderKitDataCollectorTest extends TestCase
{
    #[Test]
    public function collectCopiesTraceAndResets(): void
    {
        $trace = new PageBuilderKitTrace();
        $trace->addRender([
            'pageKey' => 'home',
            'locale'  => 'es',
            'status'  => 'published',
            'engine'  => 'grapesjs',
        ]);
        $trace->addPublicOutcome('home', 'published', 'es');
        $trace->addAdminAction('save', 'home');

        $collector = new PageBuilderKitDataCollector($trace, '/cms', true);
        $collector->collect(new Request(), new Response());

        self::assertSame('nowo_page_builder_kit', $collector->getName());
        self::assertSame(1, $collector->getRenderCount());
        self::assertSame('/cms', $collector->getPathPrefix());
        self::assertTrue($collector->isRevisionsEnabled());
        self::assertSame('home', $collector->getRenders()[0]['pageKey']);
        self::assertSame('published', $collector->getPublicOutcomes()[0]['outcome']);
        self::assertSame('save', $collector->getAdminActions()[0]['action']);

        $collector->reset();
        self::assertSame(0, $collector->getRenderCount());
        self::assertSame([], $trace->getRenders());
    }
}
