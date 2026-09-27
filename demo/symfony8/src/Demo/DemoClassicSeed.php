<?php

declare(strict_types=1);

namespace App\Demo;

/**
 * Classic schema v1 seed (section / column / widget + locale props).
 */
final class DemoClassicSeed
{
    /**
     * @return array{structure: array<string, mixed>, widgetPropsByLocale: array<string, array<string, mixed>>}
     */
    public static function document(): array
    {
        $seed = 'demo-seed-v' . DemoUseCases::SEED_VERSION;

        $structure = [
            'version'  => 1,
            'sections' => [
                [
                    'id'       => 'sec-' . $seed,
                    'settings' => [
                        'cssClasses' => 'pbk-classic-demo',
                        'style'      => ['paddingTop' => '2rem', 'paddingBottom' => '2rem'],
                    ],
                    'columns' => [
                        [
                            'id'       => 'col-main',
                            'settings' => ['width' => 8],
                            'widgets'  => [
                                ['id' => 'w-heading', 'type' => 'heading', 'settings' => ['cssClasses' => 'mb-3'], 'children' => []],
                                ['id' => 'w-text', 'type' => 'text', 'settings' => [], 'children' => []],
                                ['id' => 'w-html', 'type' => 'html', 'settings' => [], 'children' => []],
                                [
                                    'id'       => 'w-container',
                                    'type'     => 'container',
                                    'settings' => ['cssClasses' => 'border rounded p-3 my-3'],
                                    'children' => [
                                        ['id' => 'w-nested-heading', 'type' => 'heading', 'settings' => [], 'children' => []],
                                        ['id' => 'w-nested-text', 'type' => 'text', 'settings' => [], 'children' => []],
                                    ],
                                ],
                                ['id' => 'w-button', 'type' => 'button', 'settings' => [], 'children' => []],
                                ['id' => 'w-spacer', 'type' => 'spacer', 'settings' => [], 'children' => []],
                                ['id' => 'w-image', 'type' => 'image', 'settings' => [], 'children' => []],
                            ],
                        ],
                        [
                            'id'       => 'col-side',
                            'settings' => ['width' => 4],
                            'widgets'  => [
                                ['id' => 'w-side-heading', 'type' => 'heading', 'settings' => [], 'children' => []],
                                ['id' => 'w-side-text', 'type' => 'text', 'settings' => [], 'children' => []],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $widgetPropsByLocale = [
            'en' => [
                'w-heading'        => ['text' => 'Classic schema v1', 'tag' => 'h1'],
                'w-text'           => ['html' => 'This page uses the legacy section → column → widget tree. Nested container below.'],
                'w-html'           => ['html' => '<p class="text-muted">Rich <strong>HTML</strong> widget with allowlist sanitize in the demo.</p>'],
                'w-nested-heading' => ['text' => 'Nested inside container', 'tag' => 'h3'],
                'w-nested-text'    => ['html' => 'ContainerWidgetType allows children (Elementor-like nesting).'],
                'w-button'         => ['label' => 'Open GrapesJS home', 'url' => '/admin/page-builder/pages/home/canvas', 'target' => '_self'],
                'w-spacer'         => ['height' => '24px'],
                'w-image'          => ['src' => 'https://picsum.photos/seed/pbk-classic/800/320', 'alt' => 'Classic demo', 'link' => ''],
                'w-side-heading'   => ['text' => 'Sidebar', 'tag' => 'h2'],
                'w-side-text'      => ['html' => 'Second column (width 4). Locale props fall back to default locale.'],
            ],
            'es' => [
                'w-heading'        => ['text' => 'Schema clásico v1', 'tag' => 'h1'],
                'w-text'           => ['html' => 'Esta página usa el árbol legacy sección → columna → widget. Contenedor anidado abajo.'],
                'w-html'           => ['html' => '<p class="text-muted">Widget <strong>HTML</strong> con sanitize allowlist en la demo.</p>'],
                'w-nested-heading' => ['text' => 'Anidado en container', 'tag' => 'h3'],
                'w-nested-text'    => ['html' => 'ContainerWidgetType permite hijos (anidación tipo Elementor).'],
                'w-button'         => ['label' => 'Abrir canvas GrapesJS', 'url' => '/admin/page-builder/pages/home/canvas', 'target' => '_self'],
                'w-spacer'         => ['height' => '24px'],
                'w-image'          => ['src' => 'https://picsum.photos/seed/pbk-classic-es/800/320', 'alt' => 'Demo clásico', 'link' => ''],
                'w-side-heading'   => ['text' => 'Barra lateral', 'tag' => 'h2'],
                'w-side-text'      => ['html' => 'Segunda columna (width 4). Props con fallback al locale por defecto.'],
            ],
        ];

        return [
            'structure'           => $structure,
            'widgetPropsByLocale' => $widgetPropsByLocale,
        ];
    }
}
