<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Widget\Type;

use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtectionConfig;
use Nowo\PageBuilderKitBundle\Widget\Type\HeadingWidgetType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HeadingWidgetType::class)]
final class HeadingWidgetTypeTest extends TestCase
{
    private HeadingWidgetType $widgetType;

    protected function setUp(): void
    {
        $this->widgetType = new HeadingWidgetType();
    }

    #[Test]
    public function defaultProps(): void
    {
        self::assertSame(
            [
                'text' => '',
                'tag'  => 'h2',
            ],
            $this->widgetType->defaultProps(),
        );
        self::assertSame('heading', $this->widgetType->getType());
        self::assertSame('widget.heading.label', $this->widgetType->getLabelKey());
        self::assertSame(
            '@NowoPageBuilderKitBundle/widgets/heading.html.twig',
            $this->widgetType->getPublicTemplate(),
        );
    }

    #[Test]
    public function sanitizePropsNormalizesTagAndTextWithNullProtectionStrategy(): void
    {
        $protection = new PageBuilderProtection(
            new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null),
        );

        $result = $this->widgetType->sanitizeProps(
            [
                'text' => '  Hello  ',
                'tag'  => 'H3',
            ],
            $protection,
        );

        self::assertSame('Hello', $result['text']);
        self::assertSame('h3', $result['tag']);
    }

    #[Test]
    public function sanitizePropsRejectsInvalidTagAndNonStringText(): void
    {
        $protection = new PageBuilderProtection(
            new PageBuilderProtectionConfig(HtmlSanitizeStrategy::Strip, null),
        );

        $result = $this->widgetType->sanitizeProps(
            [
                'text' => 123,
                'tag'  => 'div',
            ],
            $protection,
        );

        self::assertSame('', $result['text']);
        self::assertSame('h2', $result['tag']);
    }
}
