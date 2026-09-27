<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\DependencyInjection;

use Nowo\PageBuilderKitBundle\DependencyInjection\Configuration;
use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    #[Test]
    public function processConfigurationAppliesDefaults(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), [[]]);

        self::assertSame('es', $config['default_locale']);
        self::assertSame(['es', 'en'], $config['locales']);
        self::assertSame(['ROLE_EDITOR'], $config['security']['access_roles']);
        self::assertNull($config['security']['access_checker']);
        self::assertFalse($config['security']['allow_unauthenticated']);
        self::assertSame(
            '@NowoPageBuilderKitBundle/admin/layout.html.twig',
            $config['web_ui']['layout_template'],
        );
        self::assertSame('tailwind', $config['web_ui']['css_framework']);
        self::assertSame('', $config['doctrine']['table_prefix']);
        self::assertSame('default', $config['doctrine']['connection']);
        self::assertSame(
            HtmlSanitizeStrategy::None->value,
            $config['html']['sanitize']['strategy'],
        );
        self::assertNull($config['html']['sanitize']['service']);
    }
}
