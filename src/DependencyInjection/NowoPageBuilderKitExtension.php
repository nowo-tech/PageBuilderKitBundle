<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\DependencyInjection;

use Doctrine\ORM\Events;
use LogicException;
use Nowo\PageBuilderKitBundle\DataCollector\PageBuilderKitDataCollector;
use Nowo\PageBuilderKitBundle\Debug\NullPageBuilderKitTrace;
use Nowo\PageBuilderKitBundle\Debug\PageBuilderKitTrace;
use Nowo\PageBuilderKitBundle\Debug\PageBuilderKitTraceInterface;
use Nowo\PageBuilderKitBundle\DependencyInjection\Configuration as BundleConfiguration;
use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageBuilderKitBundle\Grapes\GrapesBlockPackInterface;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Media\AssetUploadHandler;
use Nowo\PageBuilderKitBundle\Media\AwsS3AssetStorage;
use Nowo\PageBuilderKitBundle\Media\LocalFilesystemAssetStorage;
use Nowo\PageBuilderKitBundle\Media\PageBuilderAssetStorageInterface;
use Nowo\PageBuilderKitBundle\Security\AllowAllPageBuilderKitAccessChecker;
use Nowo\PageBuilderKitBundle\Security\ConfigurablePageBuilderKitAccessChecker;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessCheckerInterface;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtectionConfig;
use Nowo\PageBuilderKitBundle\Service\DocumentService;
use Nowo\PageBuilderKitBundle\Service\GrapesDocumentSanitizer;
use Nowo\PageBuilderKitBundle\Service\GrapesJsFrontendConfig;
use Nowo\PageBuilderKitBundle\Service\GrapesTwigContextProviderInterface;
use Nowo\PageBuilderKitBundle\Service\GrapesTwigRenderer;
use Nowo\PageBuilderKitBundle\Service\PageRenderProvider;
use Nowo\PageBuilderKitBundle\Service\PageRevisionStore;
use Nowo\PageBuilderKitBundle\Service\PageSeoBuilder;
use Nowo\PageBuilderKitBundle\Widget\WidgetPackInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

use function array_key_exists;
use function is_array;
use function is_string;

final class NowoPageBuilderKitExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('framework', [
            'assets' => [
                'packages' => [
                    'nowo_page_builder_kit' => [
                        'base_path' => '/bundles/nowopagebuilderkit',
                    ],
                ],
            ],
        ]);

        if (!$container->hasExtension('nowo_form_kit')) {
            return;
        }

        $hostHasProfile = false;
        foreach ($container->getExtensionConfig('nowo_form_kit') as $cfg) {
            /** @var array<string, mixed> $cfg */
            $profiles = $cfg['profiles'] ?? null;
            if (is_array($profiles) && array_key_exists('page_builder_kit', $profiles)) {
                $hostHasProfile = true;
            }
        }

        if ($hostHasProfile) {
            return;
        }

        $container->prependExtensionConfig('nowo_form_kit', [
            'profiles' => [
                'page_builder_kit' => [
                    'alias'              => 'page_builder_kit',
                    'translation_domain' => 'NowoPageBuilderKitBundle',
                    'defaults'           => [
                        'attr'     => ['class' => 'nowo-ui-input form-control'],
                        'row_attr' => ['class' => 'mb-2'],
                    ],
                ],
            ],
        ]);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new BundleConfiguration();
        $config        = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $container->setParameter('nowo_page_builder_kit.default_locale', $config['default_locale']);
        $container->setParameter('nowo_page_builder_kit.locales', $config['locales']);
        $container->setParameter('nowo_page_builder_kit.doctrine.table_prefix', $config['doctrine']['table_prefix']);
        $container->setParameter('nowo_page_builder_kit.doctrine.connection', $config['doctrine']['connection']);
        $container->setParameter('nowo_page_builder_kit.security.access_roles', $config['security']['access_roles']);
        $container->setParameter('nowo_page_builder_kit.security.access_checker', $config['security']['access_checker']);
        $container->setParameter('nowo_page_builder_kit.security.allow_unauthenticated', $config['security']['allow_unauthenticated']);
        $container->setParameter('nowo_page_builder_kit.web_ui.path_prefix', $config['web_ui']['path_prefix']);
        $container->setParameter('nowo_page_builder_kit.web_ui.layout_template', $config['web_ui']['layout_template']);
        $container->setParameter('nowo_page_builder_kit.web_ui.css_framework', $config['web_ui']['css_framework']);
        $container->setParameter('nowo_page_builder_kit.seo.site_name', $config['seo']['site_name']);
        $container->setParameter('nowo_page_builder_kit.seo.default_og_image', $config['seo']['default_og_image']);
        $container->setParameter('nowo_page_builder_kit.seo.canonical_base_url', $config['seo']['canonical_base_url']);
        $container->setParameter('nowo_page_builder_kit.seo.default_robots', $config['seo']['default_robots']);
        $container->setParameter('nowo_page_builder_kit.grapesjs.enabled', $config['grapesjs']['enabled']);
        $container->setParameter('nowo_page_builder_kit.grapesjs.allow_scripts', $config['grapesjs']['allow_scripts']);
        $container->setParameter('nowo_page_builder_kit.grapesjs.allow_custom_code', $config['grapesjs']['allow_custom_code']);
        $container->setParameter('nowo_page_builder_kit.grapesjs.compound_examples', $config['grapesjs']['compound_examples']);
        $container->setParameter('nowo_page_builder_kit.grapesjs.a11y_helpers', $config['grapesjs']['a11y_helpers']);
        $container->setParameter('nowo_page_builder_kit.grapesjs.config', $config['grapesjs']);
        $container->setParameter('nowo_page_builder_kit.revisions.enabled', $config['revisions']['enabled']);
        $container->setParameter('nowo_page_builder_kit.revisions.max_per_page', $config['revisions']['max_per_page']);
        $container->setParameter('nowo_page_builder_kit.revisions.on_save', $config['revisions']['on_save']);
        $container->setParameter('nowo_page_builder_kit.revisions.on_publish', $config['revisions']['on_publish']);

        $kernelDebug      = $container->hasParameter('kernel.debug') && (bool) $container->getParameter('kernel.debug');
        $collectorEnabled = $kernelDebug && (bool) $config['debug']['collector'];
        $container->setParameter('nowo_page_builder_kit.debug.collector', $collectorEnabled);
        $this->registerDebugCollector($container, $collectorEnabled, $config);

        $container->getDefinition(BuilderLocales::class)
            ->setArgument('$defaultLocale', $config['default_locale'])
            ->setArgument('$locales', $config['locales']);

        $container->getDefinition(PageRevisionStore::class)
            ->setArgument('$enabled', (bool) $config['revisions']['enabled'])
            ->setArgument('$maxPerPage', (int) $config['revisions']['max_per_page'])
            ->setArgument('$onSave', (bool) $config['revisions']['on_save'])
            ->setArgument('$onPublish', (bool) $config['revisions']['on_publish']);

        if ($container->hasDefinition(DocumentService::class)) {
            $container->getDefinition(DocumentService::class)
                ->setArgument('$pageRevisionStore', new Reference(PageRevisionStore::class));
        }

        $container->getDefinition(PageSeoBuilder::class)
            ->setArgument('$siteName', (string) $config['seo']['site_name'])
            ->setArgument('$defaultOgImage', (string) $config['seo']['default_og_image'])
            ->setArgument('$canonicalBaseUrl', (string) $config['seo']['canonical_base_url'])
            ->setArgument('$defaultRobots', (string) $config['seo']['default_robots']);

        $container->getDefinition(GrapesDocumentSanitizer::class)
            ->setArgument('$allowScripts', (bool) $config['grapesjs']['allow_scripts']);

        $grapesConfig = $config['grapesjs'];
        $container->getDefinition(GrapesJsFrontendConfig::class)
            ->setArgument('$enabled', (bool) $grapesConfig['enabled'])
            ->setArgument('$cdnVersion', (string) $grapesConfig['cdn_version'])
            ->setArgument('$height', (string) $grapesConfig['height'])
            ->setArgument('$allowScripts', (bool) $grapesConfig['allow_scripts'])
            ->setArgument('$allowCustomCode', (bool) $grapesConfig['allow_custom_code'])
            ->setArgument('$compoundExamples', (bool) $grapesConfig['compound_examples'])
            ->setArgument('$a11yHelpers', (bool) $grapesConfig['a11y_helpers'])
            ->setArgument('$showDevices', (bool) $grapesConfig['show_devices'])
            ->setArgument('$noticeOnUnload', (bool) $grapesConfig['notice_on_unload'])
            ->setArgument('$cssFramework', (string) $config['web_ui']['css_framework'])
            ->setArgument('$canvasStyles', $grapesConfig['canvas_styles'])
            ->setArgument('$plugins', $grapesConfig['plugins'])
            ->setArgument('$assets', $grapesConfig['assets'])
            ->setArgument('$assetEmbedAsBase64', (bool) $grapesConfig['asset_embed_as_base64'])
            ->setArgument('$assetsUploadEnabled', (bool) $grapesConfig['assets_upload']['enabled'])
            ->setArgument('$twigEnabled', (bool) $grapesConfig['twig']['enabled'])
            ->setArgument('$twigCanvasHelpers', (bool) $grapesConfig['twig']['canvas_helpers']);

        $this->registerAssetUpload($container, $grapesConfig['assets_upload']);

        $container->getDefinition(GrapesTwigRenderer::class)
            ->setArgument('$enabled', (bool) $grapesConfig['twig']['enabled'])
            ->setArgument('$strictVariables', (bool) $grapesConfig['twig']['strict_variables']);

        if ($container->hasDefinition(PageRenderProvider::class)) {
            $container->getDefinition(PageRenderProvider::class)
                ->setArgument('$twigContextProviders', new TaggedIteratorArgument('nowo_page_builder_kit.grapes_twig_context'))
                ->setArgument('$trace', new Reference(PageBuilderKitTraceInterface::class));
        }

        $container->registerForAutoconfiguration(GrapesTwigContextProviderInterface::class)
            ->addTag('nowo_page_builder_kit.grapes_twig_context');

        $container->registerForAutoconfiguration(WidgetPackInterface::class)
            ->addTag('nowo_page_builder_kit.widget_pack');

        $container->registerForAutoconfiguration(GrapesBlockPackInterface::class)
            ->addTag('nowo_page_builder_kit.grapes_block_pack');

        if (
            !$config['security']['allow_unauthenticated']
            && !$this->isSecurityBundleAvailable($container)
        ) {
            throw new LogicException('NowoPageBuilderKitBundle admin UI requires symfony/security-bundle when security.allow_unauthenticated is false.');
        }

        $this->registerAccessChecker($container, $config['security']);
        $this->registerPageBuilderProtection($container, $config);

        $tablePrefix = (string) $config['doctrine']['table_prefix'];
        if ($tablePrefix !== '') {
            $definition = new Definition(TablePrefixListener::class, [$tablePrefix]);
            $definition->addTag('doctrine.event_listener', ['event' => Events::loadClassMetadata]);
            $container->setDefinition(TablePrefixListener::class, $definition);
        }
    }

    /**
     * @param array{
     *     enabled: bool,
     *     storage: string,
     *     service: ?string,
     *     max_bytes: int,
     *     allowed_mime: list<string>,
     *     local: array{directory: string, public_prefix: string},
     *     s3: array{helper_service: string, folder: string, private: bool, public_base_url: string}
     * } $upload
     */
    private function registerAssetUpload(ContainerBuilder $container, array $upload): void
    {
        $enabled = (bool) $upload['enabled'];
        $container->setParameter('nowo_page_builder_kit.grapesjs.assets_upload.enabled', $enabled);
        $container->setParameter('nowo_page_builder_kit.grapesjs.assets_upload.storage', $upload['storage']);

        $storageId = 'nowo_page_builder_kit.asset_storage';

        if (!$enabled) {
            // Keep a local storage instance so the interface alias resolves; handler stays disabled.
            $container->register($storageId, LocalFilesystemAssetStorage::class)
                ->setAutowired(false)
                ->setArgument('$directory', (string) $upload['local']['directory'])
                ->setArgument('$publicPrefix', (string) $upload['local']['public_prefix'])
                ->setArgument('$maxBytes', (int) $upload['max_bytes'])
                ->setArgument('$allowedMimeTypes', $upload['allowed_mime']);
        } else {
            match ($upload['storage']) {
                'service' => $this->registerCustomAssetStorage($container, $storageId, $upload['service'] ?? null),
                's3'      => $this->registerS3AssetStorage($container, $storageId, $upload),
                default   => $container->register($storageId, LocalFilesystemAssetStorage::class)
                    ->setAutowired(false)
                    ->setArgument('$directory', (string) $upload['local']['directory'])
                    ->setArgument('$publicPrefix', (string) $upload['local']['public_prefix'])
                    ->setArgument('$maxBytes', (int) $upload['max_bytes'])
                    ->setArgument('$allowedMimeTypes', $upload['allowed_mime']),
            };
        }

        $container->setAlias(PageBuilderAssetStorageInterface::class, $storageId)->setPublic(false);

        $container->getDefinition(AssetUploadHandler::class)
            ->setArgument('$storage', new Reference(PageBuilderAssetStorageInterface::class))
            ->setArgument('$enabled', $enabled);
    }

    private function registerCustomAssetStorage(ContainerBuilder $container, string $storageId, ?string $serviceId): void
    {
        $serviceId = $this->optionalServiceId($serviceId);
        if ($serviceId === null) {
            throw new LogicException('nowo_page_builder_kit.grapesjs.assets_upload.service is required when storage=service.');
        }

        $container->setAlias($storageId, $serviceId);
    }

    /**
     * @param array{
     *     max_bytes: int,
     *     allowed_mime: list<string>,
     *     s3: array{helper_service: string, folder: string, private: bool, public_base_url: string}
     * } $upload
     */
    private function registerS3AssetStorage(ContainerBuilder $container, string $storageId, array $upload): void
    {
        $helperId = (string) $upload['s3']['helper_service'];
        if ($helperId === '') {
            throw new LogicException('nowo_page_builder_kit.grapesjs.assets_upload.s3.helper_service cannot be empty.');
        }

        $publicBase = (string) $upload['s3']['public_base_url'];
        $container->register($storageId, AwsS3AssetStorage::class)
            ->setAutowired(false)
            ->setArgument('$s3Helper', new Reference($helperId))
            ->setArgument('$folder', (string) $upload['s3']['folder'])
            ->setArgument('$private', (bool) $upload['s3']['private'])
            ->setArgument('$maxBytes', (int) $upload['max_bytes'])
            ->setArgument('$allowedMimeTypes', $upload['allowed_mime'])
            ->setArgument('$publicBaseUrl', $publicBase !== '' ? $publicBase : null);
    }

    public function getAlias(): string
    {
        return BundleConfiguration::ALIAS;
    }

    /**
     * @param array{access_checker: ?string, access_roles: list<string>, allow_unauthenticated: bool} $security
     */
    private function registerAccessChecker(ContainerBuilder $container, array $security): void
    {
        if ($security['allow_unauthenticated']) {
            $id = 'nowo_page_builder_kit.access_checker.allow_all';
            $container->setDefinition($id, new Definition(AllowAllPageBuilderKitAccessChecker::class));
            $container->setAlias(PageBuilderKitAccessCheckerInterface::class, $id);

            return;
        }

        $custom = $security['access_checker'] ?? null;
        if (is_string($custom) && $custom !== '') {
            $container->setAlias(PageBuilderKitAccessCheckerInterface::class, $custom);

            return;
        }

        $id         = 'nowo_page_builder_kit.access_checker.default';
        $definition = new Definition(ConfigurablePageBuilderKitAccessChecker::class);
        $definition->setArgument('$accessRoles', $security['access_roles']);
        $definition->setArgument('$authorizationChecker', new Reference('security.authorization_checker'));
        $container->setDefinition($id, $definition);
        $container->setAlias(PageBuilderKitAccessCheckerInterface::class, $id);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function registerPageBuilderProtection(ContainerBuilder $container, array $config): void
    {
        /** @var array<string, mixed> $html */
        $html = $config['html']['sanitize'];

        $container->register(PageBuilderProtectionConfig::class)
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setArguments([
                HtmlSanitizeStrategy::from((string) $html['strategy']),
                $this->optionalServiceId($html['service'] ?? null),
            ]);

        $customSanitizer = $this->optionalServiceId($html['service'] ?? null);

        $container->register(PageBuilderProtection::class)
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setArguments([
                new Reference(PageBuilderProtectionConfig::class),
                $customSanitizer !== null ? new Reference($customSanitizer) : null,
            ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function registerDebugCollector(ContainerBuilder $container, bool $enabled, array $config): void
    {
        if (!$enabled) {
            $container->setAlias(PageBuilderKitTraceInterface::class, NullPageBuilderKitTrace::class);

            return;
        }

        $container->register(PageBuilderKitTrace::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->addTag('kernel.reset', ['method' => 'reset']);

        $container->setAlias(PageBuilderKitTraceInterface::class, PageBuilderKitTrace::class);

        $container->register(PageBuilderKitDataCollector::class)
            ->setAutowired(false)
            ->setArguments([
                new Reference(PageBuilderKitTraceInterface::class),
                (string) $config['web_ui']['path_prefix'],
                (bool) $config['revisions']['enabled'],
            ])
            ->addTag('data_collector', [
                'template' => '@NowoPageBuilderKitBundle/Collector/page_builder.html.twig',
                'id'       => PageBuilderKitDataCollector::NAME,
                'priority' => 250,
            ]);
    }

    private function optionalServiceId(mixed $serviceId): ?string
    {
        if (!is_string($serviceId) || $serviceId === '') {
            return null;
        }

        return $serviceId;
    }

    private function isSecurityBundleAvailable(ContainerBuilder $container): bool
    {
        if ($container->hasExtension('security')) {
            return true;
        }

        if (!$container->hasParameter('kernel.bundles')) {
            return false;
        }

        /** @var array<string, class-string> $bundles */
        $bundles = $container->getParameter('kernel.bundles');

        return isset($bundles['SecurityBundle']);
    }
}
