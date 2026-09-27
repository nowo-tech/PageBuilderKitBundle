<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use Nowo\PageBuilderKitBundle\Grapes\GrapesBlockPackRegistry;

use function array_key_exists;

/**
 * Builds the GrapesJS frontend options consumed by the admin canvas.
 */
final readonly class GrapesJsFrontendConfig
{
    /**
     * @param list<string> $canvasStyles
     * @param array<string, bool> $plugins
     * @param list<array<string, mixed>> $assets
     * @param list<array{name: string, sample: string, label: string}> $twigVariables
     */
    public function __construct(
        private bool $enabled = true,
        private string $cdnVersion = '0.22.9',
        private string $height = 'calc(100vh - 220px)',
        private bool $allowScripts = false,
        private bool $allowCustomCode = true,
        private bool $compoundExamples = true,
        private bool $a11yHelpers = true,
        private bool $showDevices = true,
        private bool $noticeOnUnload = false,
        private string $cssFramework = 'bootstrap5',
        private array $canvasStyles = [],
        private array $plugins = [],
        private array $assets = [],
        private bool $assetEmbedAsBase64 = true,
        private bool $assetsUploadEnabled = false,
        private bool $twigEnabled = true,
        private bool $twigCanvasHelpers = true,
        private array $twigVariables = [],
        private GrapesBlockPackRegistry $blockPackRegistry = new GrapesBlockPackRegistry(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $embedAsBase64 = $this->assetEmbedAsBase64;
        if ($this->assetsUploadEnabled) {
            // Prefer remote/local URLs over bloating the document with base64.
            $embedAsBase64 = false;
        }

        return [
            'enabled'             => $this->enabled,
            'cdnVersion'          => $this->cdnVersion,
            'height'              => $this->height,
            'allowScripts'        => $this->allowScripts,
            'allowCustomCode'     => $this->allowCustomCode,
            'compoundExamples'    => $this->compoundExamples,
            'a11yHelpers'         => $this->a11yHelpers,
            'showDevices'         => $this->showDevices,
            'noticeOnUnload'      => $this->noticeOnUnload,
            'cssFramework'        => $this->cssFramework,
            'canvasStyles'        => $this->resolveCanvasStyles(),
            'plugins'             => $this->normalizePlugins(),
            'assets'              => $this->assets !== [] ? $this->assets : $this->defaultAssets(),
            'assetEmbedAsBase64'  => $embedAsBase64,
            'assetsUploadEnabled' => $this->assetsUploadEnabled,
            'pluginCdnBase'       => 'https://esm.sh',
            'blockPacks'          => $this->blockPackRegistry->toFrontend(),
            'twig'                => [
                'enabled'       => $this->twigEnabled,
                'canvasHelpers' => $this->twigCanvasHelpers,
                'variables'     => $this->twigVariables !== [] ? $this->twigVariables : (new GrapesTwigRenderer())->catalogVariables(),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveCanvasStyles(): array
    {
        $styles = $this->canvasStyles;
        if ($styles !== []) {
            return array_values($styles);
        }

        return match ($this->cssFramework) {
            'bootstrap', 'bootstrap5', 'tabler' => [
                'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
            ],
            'bootstrap4' => [
                'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css',
            ],
            'foundation' => [
                'https://cdn.jsdelivr.net/npm/foundation-sites@6.8.1/dist/css/foundation.min.css',
            ],
            default => [],
        };
    }

    /**
     * @return array<string, bool>
     */
    private function normalizePlugins(): array
    {
        $defaults = [
            'blocks_basic'     => true,
            'forms'            => true,
            'countdown'        => true,
            'export'           => true,
            'tabs'             => true,
            'custom_code'      => true,
            'touch'            => true,
            'parser_postcss'   => true,
            'tooltip'          => true,
            'style_bg'         => true,
            'typed'            => true,
            'tui_image_editor' => true,
            'navbar'           => true,
            'preset_webpage'   => true,
        ];

        foreach ($defaults as $key => $default) {
            if (array_key_exists($key, $this->plugins)) {
                $defaults[$key] = (bool) $this->plugins[$key];
            }
        }

        if (!$this->allowCustomCode) {
            $defaults['custom_code'] = false;
        }

        return $defaults;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function defaultAssets(): array
    {
        return [
            ['type' => 'image', 'src' => 'https://picsum.photos/seed/pbk1/800/600', 'height' => 600, 'width' => 800, 'name' => 'Photo 1'],
            ['type' => 'image', 'src' => 'https://picsum.photos/seed/pbk2/800/600', 'height' => 600, 'width' => 800, 'name' => 'Photo 2'],
            ['type' => 'image', 'src' => 'https://picsum.photos/seed/pbk3/1200/400', 'height' => 400, 'width' => 1200, 'name' => 'Banner'],
            ['type' => 'image', 'src' => 'https://picsum.photos/seed/pbk4/640/480', 'height' => 480, 'width' => 640, 'name' => 'Card'],
            ['type' => 'image', 'src' => 'https://i.pravatar.cc/150?u=pbk-a', 'height' => 150, 'width' => 150, 'name' => 'Avatar A'],
            ['type' => 'image', 'src' => 'https://i.pravatar.cc/150?u=pbk-b', 'height' => 150, 'width' => 150, 'name' => 'Avatar B'],
            ['type' => 'image', 'src' => 'https://via.placeholder.com/600x400/0f172a/38bdf8?text=PBK', 'height' => 400, 'width' => 600, 'name' => 'Placeholder'],
        ];
    }
}
