<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use Nowo\PageBuilderKitBundle\Grapes\GrapesBlockPackInterface;
use Nowo\PageBuilderKitBundle\Grapes\GrapesBlockPackRegistry;
use Nowo\PageBuilderKitBundle\Service\GrapesJsFrontendConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GrapesJsFrontendConfig::class)]
final class GrapesJsFrontendConfigTest extends TestCase
{
    #[Test]
    public function toArrayUsesDefaultsAndCatalogTwigVariables(): void
    {
        $config = new GrapesJsFrontendConfig(
            allowCustomCode: false,
            cssFramework: 'bootstrap5',
            assetsUploadEnabled: true,
        );

        $array = $config->toArray();

        self::assertTrue($array['enabled']);
        self::assertSame('bootstrap5', $array['cssFramework']);
        self::assertFalse($array['assetEmbedAsBase64']);
        self::assertTrue($array['assetsUploadEnabled']);
        self::assertNotEmpty($array['canvasStyles']);
        self::assertFalse($array['plugins']['custom_code']);
        self::assertNotEmpty($array['assets']);
        self::assertNotEmpty($array['twig']['variables']);
    }

    #[Test]
    public function resolveCanvasStylesForEachFramework(): void
    {
        foreach (['bootstrap', 'bootstrap5', 'tabler', 'bootstrap4', 'foundation', 'none'] as $framework) {
            $styles = (new GrapesJsFrontendConfig(cssFramework: $framework))->toArray()['canvasStyles'];
            if ($framework === 'none') {
                self::assertSame([], $styles);
            } else {
                self::assertNotEmpty($styles);
            }
        }
    }

    #[Test]
    public function customCanvasStylesPluginsAssetsAndTwigVariables(): void
    {
        $config = new GrapesJsFrontendConfig(
            enabled: false,
            canvasStyles: ['https://cdn.example/app.css'],
            plugins: ['forms' => false, 'export' => true],
            assets: [['type' => 'image', 'src' => 'https://cdn.example/a.png']],
            twigEnabled: false,
            twigCanvasHelpers: false,
            twigVariables: [['name' => 'title', 'sample' => 'Hello', 'label' => 'Title']],
        );

        $array = $config->toArray();

        self::assertFalse($array['enabled']);
        self::assertSame(['https://cdn.example/app.css'], $array['canvasStyles']);
        self::assertFalse($array['plugins']['forms']);
        self::assertTrue($array['plugins']['export']);
        self::assertSame('https://cdn.example/a.png', $array['assets'][0]['src']);
        self::assertFalse($array['twig']['enabled']);
        self::assertFalse($array['twig']['canvasHelpers']);
        self::assertSame('Title', $array['twig']['variables'][0]['label']);
        self::assertSame([], $array['blockPacks']);
    }

    #[Test]
    public function includesHostBlockPacksInFrontendConfig(): void
    {
        $pack = new class implements GrapesBlockPackInterface {
            public function getName(): string
            {
                return 'test/pack';
            }

            public function getVersion(): string
            {
                return '1.0.0';
            }

            public function getCapabilities(): array
            {
                return [];
            }

            public function getBlocks(): array
            {
                return [[
                    'id'       => 'test-block',
                    'label'    => 'Test',
                    'category' => 'Test',
                    'content'  => '<div>x</div>',
                ]];
            }
        };

        $array = (new GrapesJsFrontendConfig(
            blockPackRegistry: new GrapesBlockPackRegistry([$pack]),
        ))->toArray();

        self::assertSame('test/pack', $array['blockPacks'][0]['name']);
        self::assertSame('test-block', $array['blockPacks'][0]['blocks'][0]['id']);
    }
}
