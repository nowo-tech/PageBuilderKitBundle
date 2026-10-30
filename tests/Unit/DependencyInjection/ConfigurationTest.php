<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\DependencyInjection;

use Nowo\PageBuilderKitBundle\DependencyInjection\Configuration;
use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageBuilderKitBundle\Ui\PageBuilderKitPages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
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
        self::assertNull($config['web_ui']['layout_template']);
        self::assertSame('tailwind', $config['web_ui']['css_framework']);
        self::assertSame([], $config['web_ui']['templates']);
        self::assertSame('/admin/page-builder', $config['web_ui']['path_prefix']);
        self::assertSame('', $config['doctrine']['table_prefix']);
        self::assertSame('default', $config['doctrine']['connection']);
        self::assertSame(
            HtmlSanitizeStrategy::Allowlist->value,
            $config['html']['sanitize']['strategy'],
        );
        self::assertNull($config['html']['sanitize']['service']);
        self::assertFalse($config['revisions']['enabled']);
        self::assertSame(50, $config['revisions']['max_per_page']);
        self::assertTrue($config['revisions']['on_save']);
        self::assertTrue($config['revisions']['on_publish']);
        self::assertTrue($config['debug']['collector']);
    }

    #[Test]
    public function doctrineConnectionIsDeprecated(): void
    {
        $root = new Configuration()->getConfigTreeBuilder()->buildTree();
        self::assertInstanceOf(ArrayNode::class, $root);
        $doctrine = $root->getChildren()['doctrine'];
        self::assertInstanceOf(ArrayNode::class, $doctrine);
        $connection = $doctrine->getChildren()['connection'];
        self::assertInstanceOf(BaseNode::class, $connection);

        self::assertTrue($connection->isDeprecated());
        $deprecation = $connection->getDeprecation('connection', 'nowo_page_builder_kit.doctrine');
        self::assertSame('nowo-tech/page-builder-kit-bundle', $deprecation['package']);
        self::assertSame('1.8', $deprecation['version']);
        self::assertStringContainsString('doctrine.orm.entity_managers', $deprecation['message']);
    }

    #[Test]
    public function pathPrefixRejectsTrailingSlash(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        new Processor()->processConfiguration(new Configuration(), [[
            'web_ui' => ['path_prefix' => '/cms/'],
        ]]);
    }

    #[Test]
    public function pathPrefixAcceptsCustomAdminRoot(): void
    {
        $config = new Processor()->processConfiguration(new Configuration(), [[
            'web_ui' => ['path_prefix' => '/cms/pages'],
        ]]);

        self::assertSame('/cms/pages', $config['web_ui']['path_prefix']);
    }

    #[Test]
    public function legacyDefaultLayoutMeansTheUiKitLayout(): void
    {
        $config = new Processor()->processConfiguration(new Configuration(), [[
            'web_ui' => ['layout_template' => PageBuilderKitPages::LEGACY_DEFAULT_LAYOUT],
        ]]);

        self::assertNull($config['web_ui']['layout_template']);

        $config = new Processor()->processConfiguration(new Configuration(), [[
            'web_ui' => ['layout_template' => 'admin/layout.html.twig'],
        ]]);

        self::assertSame('admin/layout.html.twig', $config['web_ui']['layout_template']);
    }

    #[Test]
    public function templatesAcceptsKnownPageIds(): void
    {
        $config = new Processor()->processConfiguration(new Configuration(), [[
            'web_ui' => ['templates' => ['index' => 'admin/pbk/index.html.twig', 'canvas' => 'admin/pbk/canvas.html.twig']],
        ]]);

        self::assertSame(['index' => 'admin/pbk/index.html.twig', 'canvas' => 'admin/pbk/canvas.html.twig'], $config['web_ui']['templates']);
    }

    #[Test]
    public function templatesRejectsUnknownPageIds(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unknown nowo_page_builder_kit.web_ui.templates page id(s) "dashboard"');

        new Processor()->processConfiguration(new Configuration(), [[
            'web_ui' => ['templates' => ['dashboard' => 'x.html.twig']],
        ]]);
    }

    #[Test]
    public function cssFrameworkIsDeprecated(): void
    {
        $root = new Configuration()->getConfigTreeBuilder()->buildTree();
        self::assertInstanceOf(ArrayNode::class, $root);
        $webUi = $root->getChildren()['web_ui'];
        self::assertInstanceOf(ArrayNode::class, $webUi);
        $node = $webUi->getChildren()['css_framework'];
        self::assertInstanceOf(BaseNode::class, $node);

        self::assertTrue($node->isDeprecated());
        $deprecation = $node->getDeprecation('css_framework', 'nowo_page_builder_kit.web_ui');
        self::assertSame('1.12', $deprecation['version']);
        self::assertStringContainsString('grapesjs.canvas_styles', $deprecation['message']);
    }

    #[Test]
    public function grapesModuleBaseUrlAcceptsHttpsAndRootRelativeOnly(): void
    {
        $config = new Processor()->processConfiguration(new Configuration(), [[]]);
        self::assertSame('https://esm.sh', $config['grapesjs']['module_base_url']);

        foreach (['https://cdn.example.com/esm', '/vendor/esm'] as $ok) {
            $config = new Processor()->processConfiguration(new Configuration(), [['grapesjs' => ['module_base_url' => $ok]]]);
            self::assertSame($ok, $config['grapesjs']['module_base_url']);
        }

        foreach (['http://cdn.example.com', '//evil.example', '/\\evil.example', 'javascript:alert(1)', 'https://a b'] as $bad) {
            try {
                new Processor()->processConfiguration(new Configuration(), [['grapesjs' => ['module_base_url' => $bad]]]);
                self::fail('Accepted ' . $bad);
            } catch (InvalidConfigurationException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
