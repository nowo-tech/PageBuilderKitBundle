<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Entity;

use Nowo\PageBuilderKitBundle\Entity\BuilderPageTemplate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BuilderPageTemplate::class)]
final class BuilderPageTemplateTest extends TestCase
{
    #[Test]
    public function accessorsRoundTrip(): void
    {
        $template = (new BuilderPageTemplate())
            ->setTemplateKey('hero')
            ->setLabel('Hero')
            ->setStructure(['version' => 2, 'engine' => 'grapesjs'])
            ->setWidgetPropsByLocale(['es' => []]);

        $template->touchUpdatedAt();

        self::assertNull($template->getId());
        self::assertSame('hero', $template->getTemplateKey());
        self::assertSame('Hero', $template->getLabel());
        self::assertSame(['version' => 2, 'engine' => 'grapesjs'], $template->getStructure());
        self::assertSame(['es' => []], $template->getWidgetPropsByLocale());
        self::assertGreaterThanOrEqual(
            $template->getCreatedAt()->getTimestamp(),
            $template->getUpdatedAt()->getTimestamp(),
        );
    }
}
