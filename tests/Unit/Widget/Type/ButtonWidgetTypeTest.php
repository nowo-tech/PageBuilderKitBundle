<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Widget\Type;

use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtectionConfig;
use Nowo\PageBuilderKitBundle\Widget\Type\ButtonWidgetType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ButtonWidgetType::class)]
final class ButtonWidgetTypeTest extends TestCase
{
    #[Test]
    public function defaultPropsAndMetadata(): void
    {
        $type = new ButtonWidgetType();

        self::assertSame('button', $type->getType());
        self::assertSame('widget.button.label', $type->getLabelKey());
        self::assertSame(
            ['label' => '', 'url' => '', 'target' => '_self'],
            $type->defaultProps(),
        );
    }

    #[Test]
    public function sanitizePropsNormalizesFields(): void
    {
        $protection = new PageBuilderProtection(
            new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null),
        );
        $type = new ButtonWidgetType();

        $result = $type->sanitizeProps(
            [
                'label'  => '  Go  ',
                'url'    => ' /path ',
                'target' => '_blank',
            ],
            $protection,
        );

        self::assertSame('Go', $result['label']);
        self::assertSame('/path', $result['url']);
        self::assertSame('_blank', $result['target']);
    }

    #[Test]
    public function sanitizePropsRejectsInvalidTarget(): void
    {
        $protection = new PageBuilderProtection(
            new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null),
        );

        $result = (new ButtonWidgetType())->sanitizeProps(['target' => '_top'], $protection);

        self::assertSame('_self', $result['target']);
    }
}
