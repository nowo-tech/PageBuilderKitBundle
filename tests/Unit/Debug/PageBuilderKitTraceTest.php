<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Debug;

use Nowo\PageBuilderKitBundle\Debug\NullPageBuilderKitTrace;
use Nowo\PageBuilderKitBundle\Debug\PageBuilderKitTrace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageBuilderKitTrace::class)]
#[CoversClass(NullPageBuilderKitTrace::class)]
final class PageBuilderKitTraceTest extends TestCase
{
    #[Test]
    public function accumulatesAndResets(): void
    {
        $trace = new PageBuilderKitTrace();
        $trace->addRender(['pageKey' => 'home', 'locale' => 'es', 'status' => 'published', 'engine' => 'grapesjs']);
        $trace->addPublicOutcome('home', 'published', 'es');
        $trace->addAdminAction('save', 'home', 3);

        self::assertCount(1, $trace->getRenders());
        self::assertSame('published', $trace->getPublicOutcomes()[0]['outcome']);
        self::assertSame(3, $trace->getAdminActions()[0]['revisionId']);

        $trace->reset();
        self::assertSame([], $trace->getRenders());
        self::assertSame([], $trace->getPublicOutcomes());
        self::assertSame([], $trace->getAdminActions());
    }

    #[Test]
    public function nullTraceIsNoOp(): void
    {
        $trace = new NullPageBuilderKitTrace();
        $trace->addRender(['pageKey' => 'x']);
        $trace->addPublicOutcome('x', 'published');
        $trace->addAdminAction('save', 'x');
        $trace->reset();

        self::assertSame([], $trace->getRenders());
        self::assertSame([], $trace->getPublicOutcomes());
        self::assertSame([], $trace->getAdminActions());
    }
}
