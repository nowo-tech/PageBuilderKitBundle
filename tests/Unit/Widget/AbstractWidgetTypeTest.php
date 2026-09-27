<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Widget;

use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtectionConfig;
use Nowo\PageBuilderKitBundle\Widget\Type\HeadingWidgetType;
use Nowo\PageBuilderKitBundle\Widget\Type\HtmlWidgetType;
use Nowo\PageBuilderKitBundle\Widget\Type\ImageWidgetType;
use Nowo\PageBuilderKitBundle\Widget\Type\SpacerWidgetType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AbstractWidgetTypeTest extends TestCase
{
    #[Test]
    public function exposesPublicTemplateAndDefaultAllowsChildren(): void
    {
        $heading = new HeadingWidgetType();
        self::assertFalse($heading->allowsChildren());
        self::assertStringContainsString('heading.html.twig', $heading->getPublicTemplate());
    }

    #[Test]
    public function widgetTypesCoverDefaultPropsAndSanitizePaths(): void
    {
        $protection = new PageBuilderProtection(
            new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null),
        );

        self::assertSame(['html' => ''], (new HtmlWidgetType())->defaultProps());
        self::assertSame(['height' => '1rem'], (new SpacerWidgetType())->defaultProps());
        self::assertSame('', (new ImageWidgetType())->sanitizeProps(['alt' => 123], $protection)['alt']);
        self::assertSame('1rem', (new SpacerWidgetType())->sanitizeProps(['height' => ''], $protection)['height']);
        self::assertStringContainsString('html.html.twig', (new HtmlWidgetType())->getPublicTemplate());
    }
}
