<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\DependencyInjection;

use LogicException;
use Nowo\PageBuilderKitBundle\DependencyInjection\NowoPageBuilderKitExtension;
use Nowo\PageBuilderKitBundle\DependencyInjection\TablePrefixListener;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Security\AllowAllPageBuilderKitAccessChecker;
use Nowo\PageBuilderKitBundle\Security\Html\NullPageBuilderHtmlSanitizer;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessCheckerInterface;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtectionConfig;
use Nowo\PageBuilderKitBundle\Service\DocumentService;
use Nowo\PageBuilderKitBundle\Service\PageRenderProvider;
use Nowo\PageBuilderKitBundle\Service\WidgetPropsMerger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

#[CoversClass(NowoPageBuilderKitExtension::class)]
final class NowoPageBuilderKitExtensionTest extends TestCase
{
    #[Test]
    public function loadSetsParametersAndProtectionServices(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', [
            'SecurityBundle' => SecurityBundle::class,
        ]);

        $extension = new NowoPageBuilderKitExtension();
        $extension->load([
            [
                'default_locale' => 'en',
                'locales'        => ['en', 'de'],
                'security'       => [
                    'allow_unauthenticated' => true,
                ],
                'web_ui' => [
                    'css_framework' => 'bootstrap5',
                ],
                'doctrine' => [
                    'table_prefix' => 'pb_',
                ],
                'html' => [
                    'sanitize' => [
                        'strategy' => 'strip',
                    ],
                ],
            ],
        ], $container);

        self::assertSame('en', $container->getParameter('nowo_page_builder_kit.default_locale'));
        self::assertSame(['en', 'de'], $container->getParameter('nowo_page_builder_kit.locales'));
        self::assertSame('pb_', $container->getParameter('nowo_page_builder_kit.doctrine.table_prefix'));
        self::assertSame('bootstrap5', $container->getParameter('nowo_page_builder_kit.web_ui.css_framework'));
        self::assertSame('/admin/page-builder', $container->getParameter('nowo_page_builder_kit.web_ui.path_prefix'));

        $localesDef = $container->getDefinition(BuilderLocales::class);
        self::assertSame('en', $localesDef->getArgument('$defaultLocale'));
        self::assertSame(['en', 'de'], $localesDef->getArgument('$locales'));

        self::assertTrue($container->hasDefinition(PageBuilderProtection::class));
        self::assertTrue($container->hasAlias(PageBuilderKitAccessCheckerInterface::class));
        self::assertTrue($container->hasDefinition(TablePrefixListener::class));
        self::assertTrue($container->hasDefinition(DocumentService::class));
        self::assertTrue($container->hasDefinition(PageRenderProvider::class));
        self::assertTrue($container->hasDefinition(WidgetPropsMerger::class));
        self::assertSame(
            'nowo_page_builder_kit.access_checker.allow_all',
            (string) $container->getAlias(PageBuilderKitAccessCheckerInterface::class),
        );
        self::assertSame(
            AllowAllPageBuilderKitAccessChecker::class,
            $container->findDefinition('nowo_page_builder_kit.access_checker.allow_all')->getClass(),
        );
    }

    #[Test]
    public function loadThrowsWhenSecurityRequiredButMissing(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);

        $extension = new NowoPageBuilderKitExtension();

        $this->expectException(LogicException::class);

        $extension->load([
            [
                'security' => [
                    'allow_unauthenticated' => false,
                ],
            ],
        ], $container);
    }

    #[Test]
    public function prependRegistersFrameworkAssetsAndFormKitProfile(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'nowo_form_kit';
            }
        });

        $extension = new NowoPageBuilderKitExtension();
        $extension->prepend($container);

        $frameworkConfigs = $container->getExtensionConfig('framework');
        self::assertNotEmpty($frameworkConfigs);
        self::assertSame(
            '/bundles/nowopagebuilderkit',
            $frameworkConfigs[0]['assets']['packages']['nowo_page_builder_kit']['base_path'],
        );

        $formKitConfigs = $container->getExtensionConfig('nowo_form_kit');
        self::assertArrayHasKey('page_builder_kit', $formKitConfigs[0]['profiles']);
    }

    #[Test]
    public function loadRegistersCustomHtmlSanitizerForServiceStrategy(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);
        $container->register('app.sanitizer', NullPageBuilderHtmlSanitizer::class);

        $extension = new NowoPageBuilderKitExtension();
        $extension->load([
            [
                'security' => ['allow_unauthenticated' => true],
                'html'     => [
                    'sanitize' => [
                        'strategy' => 'service',
                        'service'  => 'app.sanitizer',
                    ],
                ],
            ],
        ], $container);

        $protectionDef = $container->getDefinition(PageBuilderProtection::class);
        self::assertCount(2, $protectionDef->getArguments());
        self::assertTrue($container->hasDefinition(PageBuilderProtectionConfig::class));
        self::assertTrue($container->hasDefinition(PageBuilderProtection::class));
    }
}
