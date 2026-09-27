<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Widget\Type;

use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtectionConfig;
use Nowo\PageBuilderKitBundle\Widget\Type\HtmlWidgetType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HtmlWidgetType::class)]
final class HtmlWidgetTypeTest extends TestCase
{
    #[Test]
    public function defaultPropsAndMetadata(): void
    {
        $type = new HtmlWidgetType();

        self::assertSame('html', $type->getType());
        self::assertSame(['html' => ''], $type->defaultProps());
        self::assertStringContainsString('html.html.twig', $type->getPublicTemplate());
    }

    #[Test]
    public function sanitizePropsUsesAllowlistStrategy(): void
    {
        $protection = new PageBuilderProtection(
            new PageBuilderProtectionConfig(HtmlSanitizeStrategy::Allowlist, null),
        );

        $result = (new HtmlWidgetType())->sanitizeProps(
            ['html' => '<p>ok</p><script>x</script>'],
            $protection,
        );

        self::assertStringContainsString('ok', $result['html']);
        self::assertStringNotContainsString('script', $result['html']);
    }
}
