<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use function array_key_exists;

/**
 * Builds the GrapesJS frontend options consumed by the admin canvas.
 */
final class GrapesJsFrontendConfig
{
    /**
     * @param list<string> $canvasStyles
     * @param array<string, bool> $plugins
     * @param list<array<string, mixed>> $assets
     * @param list<array{name: string, sample: string, label: string}> $twigVariables
     */
    public function __construct(
        private readonly bool $enabled = true,
        private readonly string $cdnVersion = '0.22.9',
        private readonly string $height = 'calc(100vh - 220px)',
        private readonly bool $allowScripts = false,
        private readonly bool $allowCustomCode = true,
        private readonly bool $compoundExamples = true,
        private readonly bool $a11yHelpers = true,
        private readonly bool $showDevices = true,
        private readonly bool $noticeOnUnload = false,
        private readonly string $cssFramework = 'bootstrap5',
        private readonly array $canvasStyles = [],
        private readonly array $plugins = [],
        private readonly array $assets = [],
        private readonly bool $assetEmbedAsBase64 = true,
        private readonly bool $assetsUploadEnabled = false,
        private readonly bool $twigEnabled = true,
        private readonly bool $twigCanvasHelpers = true,
        private readonly array $twigVariables = [],
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
