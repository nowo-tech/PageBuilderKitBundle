<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\DependencyInjection;

use LogicException;
use Nowo\PageBuilderKitBundle\DependencyInjection\Configuration;
use Nowo\PageBuilderKitBundle\DependencyInjection\NowoPageBuilderKitExtension;
use Nowo\PageBuilderKitBundle\DependencyInjection\TablePrefixListener;
use Nowo\PageBuilderKitBundle\Media\AssetUploadHandler;
use Nowo\PageBuilderKitBundle\Media\AwsS3AssetStorage;
use Nowo\PageBuilderKitBundle\Media\LocalFilesystemAssetStorage;
use Nowo\PageBuilderKitBundle\Security\ConfigurablePageBuilderKitAccessChecker;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessCheckerInterface;
use Nowo\PageBuilderKitBundle\Service\GrapesJsFrontendConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

#[CoversClass(NowoPageBuilderKitExtension::class)]
final class NowoPageBuilderKitExtensionCoverageTest extends TestCase
{
    #[Test]
    public function getAliasMatchesConfiguration(): void
    {
        self::assertSame(Configuration::ALIAS, (new NowoPageBuilderKitExtension())->getAlias());
    }

    #[Test]
    public function prependSkipsFormKitProfileWhenExtensionMissing(): void
    {
        $container = new ContainerBuilder();
        (new NowoPageBuilderKitExtension())->prepend($container);

        self::assertNotEmpty($container->getExtensionConfig('framework'));
        self::assertSame([], $container->getExtensionConfig('nowo_form_kit'));
    }

    #[Test]
    public function prependSkipsWhenHostAlreadyDefinesProfile(): void
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
        $container->prependExtensionConfig('nowo_form_kit', [
            'profiles' => ['page_builder_kit' => ['alias' => 'page_builder_kit']],
        ]);

        (new NowoPageBuilderKitExtension())->prepend($container);

        self::assertCount(1, $container->getExtensionConfig('nowo_form_kit'));
    }

    #[Test]
    public function loadRegistersConfigurableAccessCheckerAndAssetUploadVariants(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        $container->register('security.authorization_checker', stdClass::class);
        $container->register('core_aws_s3.service.helper', stdClass::class);
        $container->register('app.storage', stdClass::class);

        $config = [
            'security' => [
                'allow_unauthenticated' => false,
                'access_roles'          => ['ROLE_ADMIN'],
                'access_checker'        => null,
            ],
            'doctrine' => ['table_prefix' => ''],
            'grapesjs' => [
                'assets_upload' => [
                    'enabled'      => true,
                    'storage'      => 's3',
                    'service'      => null,
                    'max_bytes'    => 1000,
                    'allowed_mime' => ['image/png'],
                    'local'        => ['directory' => '/tmp', 'public_prefix' => '/u'],
                    's3'           => [
                        'helper_service'  => 'core_aws_s3.service.helper',
                        'folder'          => 'pb',
                        'private'         => true,
                        'public_base_url' => 'https://cdn.example',
                    ],
                ],
            ],
        ];

        (new NowoPageBuilderKitExtension())->load([$config], $container);

        self::assertSame(
            ConfigurablePageBuilderKitAccessChecker::class,
            $container->findDefinition('nowo_page_builder_kit.access_checker.default')->getClass(),
        );
        self::assertFalse($container->hasDefinition(TablePrefixListener::class));
        self::assertTrue($container->hasDefinition(AwsS3AssetStorage::class));
        self::assertTrue($container->hasDefinition(AssetUploadHandler::class));
        self::assertTrue($container->hasDefinition(GrapesJsFrontendConfig::class));
    }

    #[Test]
    public function loadRegistersLocalUploadAndCustomStorageService(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);
        $container->register('app.storage', stdClass::class);

        (new NowoPageBuilderKitExtension())->load([[
            'security' => ['allow_unauthenticated' => true],
            'grapesjs' => [
                'assets_upload' => [
                    'enabled' => true,
                    'storage' => 'service',
                    'service' => 'app.storage',
                ],
            ],
        ]], $container);

        self::assertSame(
            'app.storage',
            (string) $container->getAlias('nowo_page_builder_kit.asset_storage'),
        );
    }

    #[Test]
    public function loadUsesLocalStorageWhenUploadDisabled(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);

        (new NowoPageBuilderKitExtension())->load([[
            'security' => ['allow_unauthenticated' => true],
            'grapesjs' => ['assets_upload' => ['enabled' => false]],
        ]], $container);

        self::assertTrue($container->hasDefinition(LocalFilesystemAssetStorage::class));
        self::assertFalse($container->getDefinition(AssetUploadHandler::class)->getArgument('$enabled'));
    }

    #[Test]
    public function loadThrowsForMissingCustomUploadService(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);

        $this->expectException(LogicException::class);

        (new NowoPageBuilderKitExtension())->load([[
            'security' => ['allow_unauthenticated' => true],
            'grapesjs' => [
                'assets_upload' => ['enabled' => true, 'storage' => 'service', 'service' => null],
            ],
        ]], $container);
    }

    #[Test]
    public function loadThrowsForEmptyS3HelperService(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);

        $this->expectException(LogicException::class);

        (new NowoPageBuilderKitExtension())->load([[
            'security' => ['allow_unauthenticated' => true],
            'grapesjs' => [
                'assets_upload' => [
                    'enabled' => true,
                    'storage' => 's3',
                    's3'      => ['helper_service' => ''],
                ],
            ],
        ]], $container);
    }

    #[Test]
    public function loadDetectsSecurityBundleViaExtension(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'security';
            }
        });
        $container->register('security.authorization_checker', stdClass::class);

        (new NowoPageBuilderKitExtension())->load([[
            'security' => ['allow_unauthenticated' => false, 'access_roles' => ['ROLE_X']],
        ]], $container);

        self::assertTrue($container->hasDefinition('nowo_page_builder_kit.access_checker.default'));
    }

    #[Test]
    public function loadRegistersLocalFilesystemWhenUploadEnabled(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);

        (new NowoPageBuilderKitExtension())->load([[
            'security' => ['allow_unauthenticated' => true],
            'grapesjs' => [
                'assets_upload' => [
                    'enabled' => true,
                    'storage' => 'local',
                ],
            ],
        ]], $container);

        self::assertTrue($container->hasDefinition('nowo_page_builder_kit.asset_storage'));
        self::assertTrue($container->getDefinition(AssetUploadHandler::class)->getArgument('$enabled'));
    }

    #[Test]
    public function loadHonorsCustomAccessCheckerAlias(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => 'x']);
        $container->register('app.checker', stdClass::class);

        (new NowoPageBuilderKitExtension())->load([[
            'security' => [
                'allow_unauthenticated' => false,
                'access_checker'        => 'app.checker',
            ],
        ]], $container);

        self::assertSame('app.checker', (string) $container->getAlias(PageBuilderKitAccessCheckerInterface::class));
    }

    #[Test]
    public function loadWithoutKernelBundlesUsesSecurityExtension(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'security';
            }
        });
        $container->register('security.authorization_checker', stdClass::class);

        (new NowoPageBuilderKitExtension())->load([[
            'security' => ['allow_unauthenticated' => false, 'access_roles' => ['ROLE_X']],
        ]], $container);

        self::assertTrue($container->hasDefinition('nowo_page_builder_kit.access_checker.default'));
    }
}
