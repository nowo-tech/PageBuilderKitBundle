<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\DependencyInjection;

use Nowo\PageBuilderKitBundle\DependencyInjection\Configuration;
use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageBuilderKitBundle\Ui\PageBuilderKitPages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
    public function doctrineEntityManagerAndTransactionMode(): void
    {
        $config = new Processor()->processConfiguration(new Configuration(), [['doctrine' => ['entity_manager' => 'page_builder', 'transaction' => 'host', 'mapping_type' => 'xml']]]);
        self::assertSame('page_builder', $config['doctrine']['entity_manager']);
        self::assertSame('host', $config['doctrine']['transaction']);
        self::assertSame('xml', $config['doctrine']['mapping_type']);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('non-empty entity manager name');
        new Processor()->processConfiguration(new Configuration(), [['doctrine' => ['entity_manager' => ' ']]]);
    }

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
        self::assertArrayNotHasKey('css_framework', $config['web_ui'], 'removed in 2.0');
        self::assertSame([], $config['web_ui']['templates']);
        self::assertSame('/admin/page-builder', $config['web_ui']['path_prefix']);
        self::assertSame('', $config['doctrine']['table_prefix']);
        self::assertArrayNotHasKey('connection', $config['doctrine'], 'removed in 2.0');
        self::assertNull($config['doctrine']['private_entity_manager']);
        self::assertNull($config['doctrine']['entity_manager']);
        self::assertSame('bundle', $config['doctrine']['transaction']);
        self::assertSame('attribute', $config['doctrine']['mapping_type']);
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

    /**
     * 2.0: the keys deprecated in 1.x fail the build with a message naming what replaces them (never Symfony's generic
     * "Unrecognized option", never a silent fallback).
     *
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function removedKeys(): iterable
    {
        yield 'doctrine.connection (deprecated since 1.8)' => [['doctrine' => ['connection' => 'default']], 'The "nowo_page_builder_kit.doctrine.connection" option was removed in 2.0: deprecated and ignored since 1.8'];
        yield 'web_ui.css_framework (deprecated since 1.12)' => [['web_ui' => ['css_framework' => 'bootstrap5']], 'The "nowo_page_builder_kit.web_ui.css_framework" option was removed in 2.0: deprecated since 1.12'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[Test]
    #[DataProvider('removedKeys')]
    public function aRemovedKeyFailsTheBuild(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        new Processor()->processConfiguration(new Configuration(), [$config]);
    }

    /** Q4: `doctrine.private_entity_manager` is a deprecated no-op in 2.x, removed in 3.0; `false` is refused. */
    #[Test]
    public function privateEntityManagerIsDeprecatedAndFalseRefused(): void
    {
        $root = new Configuration()->getConfigTreeBuilder()->buildTree();
        self::assertInstanceOf(ArrayNode::class, $root);
        $doctrine = $root->getChildren()['doctrine'];
        self::assertInstanceOf(ArrayNode::class, $doctrine);
        $node = $doctrine->getChildren()['private_entity_manager'];
        self::assertInstanceOf(BaseNode::class, $node);

        self::assertTrue($node->isDeprecated());
        $deprecation = $node->getDeprecation('private_entity_manager', 'nowo_page_builder_kit.doctrine');
        self::assertSame('nowo-tech/page-builder-kit-bundle', $deprecation['package']);
        self::assertSame('2.0', $deprecation['version']);
        self::assertStringContainsString('removed in 3.0', $deprecation['message']);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('private_entity_manager: false is no longer supported');
        new Processor()->processConfiguration(new Configuration(), [['doctrine' => ['private_entity_manager' => false]]]);
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
    public function grapesModuleBaseUrlAcceptsHttpsAndRootRelativeOnly(): void
    {
        $config = new Processor()->processConfiguration(new Configuration(), [[]]);
        self::assertSame('https://esm.sh', $config['grapesjs']['module_base_url']);

        foreach (['https://cdn.example.com/esm', '/vendor/esm'] as $ok) {
            $config = new Processor()->processConfiguration(new Configuration(), [['grapesjs' => ['module_base_url' => $ok]]]);
            self::assertSame($ok, $config['grapesjs']['module_base_url']);
        }

        foreach (['http://cdn.example.com', '//evil.example', '/\\evil.example', 'javascript:alert(1)', 'https://a b', "https://cdn.example.com/esm\n", "/vendor/esm\n"] as $bad) {
            try {
                new Processor()->processConfiguration(new Configuration(), [['grapesjs' => ['module_base_url' => $bad]]]);
                self::fail('Accepted ' . $bad);
            } catch (InvalidConfigurationException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
