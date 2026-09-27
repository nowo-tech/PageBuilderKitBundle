<?php

declare(strict_types=1);

namespace App\Demo;

/**
 * Classic schema v1: multi-section landing with divergent EN/ES widget props.
 *
 * Layout (sections/columns) is shared; content is edited per locale.
 */
final class DemoSectionsSeed
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
                    'id'       => 'sec-hero-' . $seed,
                    'settings' => [
                        'cssId'      => 'section-hero',
                        'cssClasses' => 'pbk-classic-section pbk-classic-section--hero',
                        'attributes' => [
                            ['name' => 'aria-label', 'value' => 'Hero'],
                        ],
                        'style' => [
                            'paddingTop'      => '3rem',
                            'paddingBottom'   => '3rem',
                            'backgroundColor' => '#0f172a',
                            'color'           => '#f8fafc',
                        ],
                    ],
                    'columns' => [
                        [
                            'id'       => 'col-hero',
                            'settings' => ['width' => 12],
                            'widgets'  => [
                                ['id' => 'sec1-eyebrow', 'type' => 'heading', 'settings' => [], 'children' => []],
                                ['id' => 'sec1-title', 'type' => 'heading', 'settings' => [], 'children' => []],
                                ['id' => 'sec1-lead', 'type' => 'text', 'settings' => [], 'children' => []],
                                ['id' => 'sec1-cta', 'type' => 'button', 'settings' => [], 'children' => []],
                            ],
                        ],
                    ],
                ],
                [
                    'id'       => 'sec-features-' . $seed,
                    'settings' => [
                        'cssId'      => 'section-features',
                        'cssClasses' => 'pbk-classic-section pbk-classic-section--features',
                        'attributes' => [
                            ['name' => 'aria-label', 'value' => 'Features'],
                        ],
                        'style' => ['paddingTop' => '2.5rem', 'paddingBottom' => '2.5rem'],
                    ],
                    'columns' => [
                        [
                            'id'       => 'col-feat-1',
                            'settings' => ['width' => 4],
                            'widgets'  => [
                                ['id' => 'sec2-f1-title', 'type' => 'heading', 'settings' => [], 'children' => []],
                                ['id' => 'sec2-f1-body', 'type' => 'text', 'settings' => [], 'children' => []],
                            ],
                        ],
                        [
                            'id'       => 'col-feat-2',
                            'settings' => ['width' => 4],
                            'widgets'  => [
                                ['id' => 'sec2-f2-title', 'type' => 'heading', 'settings' => [], 'children' => []],
                                ['id' => 'sec2-f2-body', 'type' => 'text', 'settings' => [], 'children' => []],
                            ],
                        ],
                        [
                            'id'       => 'col-feat-3',
                            'settings' => ['width' => 4],
                            'widgets'  => [
                                ['id' => 'sec2-f3-title', 'type' => 'heading', 'settings' => [], 'children' => []],
                                ['id' => 'sec2-f3-body', 'type' => 'text', 'settings' => [], 'children' => []],
                            ],
                        ],
                    ],
                ],
                [
                    'id'       => 'sec-story-' . $seed,
                    'settings' => [
                        'cssId'      => 'section-story',
                        'cssClasses' => 'pbk-classic-section pbk-classic-section--story',
                        'style'      => [
                            'paddingTop'      => '2.5rem',
                            'paddingBottom'   => '2.5rem',
                            'backgroundColor' => '#f8fafc',
                        ],
                    ],
                    'columns' => [
                        [
                            'id'       => 'col-story-media',
                            'settings' => ['width' => 5],
                            'widgets'  => [
                                ['id' => 'sec3-image', 'type' => 'image', 'settings' => [], 'children' => []],
                            ],
                        ],
                        [
                            'id'       => 'col-story-copy',
                            'settings' => ['width' => 7],
                            'widgets'  => [
                                ['id' => 'sec3-title', 'type' => 'heading', 'settings' => [], 'children' => []],
                                ['id' => 'sec3-body', 'type' => 'text', 'settings' => [], 'children' => []],
                                ['id' => 'sec3-note', 'type' => 'html', 'settings' => [], 'children' => []],
                            ],
                        ],
                    ],
                ],
                [
                    'id'       => 'sec-cta-' . $seed,
                    'settings' => [
                        'cssId'      => 'section-cta',
                        'cssClasses' => 'pbk-classic-section pbk-classic-section--cta',
                        'style'      => [
                            'paddingTop'      => '2.5rem',
                            'paddingBottom'   => '2.5rem',
                            'backgroundColor' => '#1d4ed8',
                            'color'           => '#fff',
                        ],
                    ],
                    'columns' => [
                        [
                            'id'       => 'col-cta',
                            'settings' => ['width' => 12],
                            'widgets'  => [
                                ['id' => 'sec4-title', 'type' => 'heading', 'settings' => [], 'children' => []],
                                ['id' => 'sec4-body', 'type' => 'text', 'settings' => [], 'children' => []],
                                ['id' => 'sec4-button', 'type' => 'button', 'settings' => [], 'children' => []],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $widgetPropsByLocale = [
            'en' => [
                'sec1-eyebrow' => ['text' => 'Sections · classic v1', 'tag' => 'h6'],
                'sec1-title'   => ['text' => 'One layout, many locales', 'tag' => 'h1'],
                'sec1-lead'    => ['html' => '<p>Shared section → column → widget tree. Open the <strong>Sections</strong> editor and switch EN/ES tabs to change only the props of each section.</p>'],
                'sec1-cta'     => ['label' => 'Edit sections (EN)', 'url' => '/admin/page-builder/pages/sections/sections', 'target' => '_self'],

                'sec2-f1-title' => ['text' => 'Shared structure', 'tag' => 'h3'],
                'sec2-f1-body'  => ['html' => '<p>Sections and columns stay identical across locales.</p>'],
                'sec2-f2-title' => ['text' => 'Locale props', 'tag' => 'h3'],
                'sec2-f2-body'  => ['html' => '<p>Widget content lives in <code>widgetPropsByLocale</code> per language.</p>'],
                'sec2-f3-title' => ['text' => 'Fallback', 'tag' => 'h3'],
                'sec2-f3-body'  => ['html' => '<p>Missing ES props fall back to the default locale.</p>'],

                'sec3-image' => ['src' => 'https://picsum.photos/seed/pbk-sections-en/640/420', 'alt' => 'English story image'],
                'sec3-title' => ['text' => 'Story section (EN)', 'tag' => 'h2'],
                'sec3-body'  => ['html' => '<p>This copy is English-only messaging: launch in US/UK markets first, then localize.</p>'],
                'sec3-note'  => ['html' => '<p class="text-muted small mb-0">EN note: pricing in USD · support 9–5 EST</p>'],

                'sec4-title'  => ['text' => 'Ready to localize?', 'tag' => 'h2'],
                'sec4-body'   => ['html' => '<p>Switch to <code>?_locale=es</code> — same sections, different props.</p>'],
                'sec4-button' => ['label' => 'View Spanish variant', 'url' => '/sections?_locale=es', 'target' => '_self'],
            ],
            'es' => [
                'sec1-eyebrow' => ['text' => 'Secciones · clásico v1', 'tag' => 'h6'],
                'sec1-title'   => ['text' => 'Un layout, varios locales', 'tag' => 'h1'],
                'sec1-lead'    => ['html' => '<p>Árbol compartido sección → columna → widget. Abre el editor de <strong>Secciones</strong> y cambia EN/ES para editar solo las props de cada sección.</p>'],
                'sec1-cta'     => ['label' => 'Editar secciones (ES)', 'url' => '/admin/page-builder/pages/sections/sections?locale=es', 'target' => '_self'],

                'sec2-f1-title' => ['text' => 'Estructura compartida', 'tag' => 'h3'],
                'sec2-f1-body'  => ['html' => '<p>Las secciones y columnas son las mismas en todos los idiomas.</p>'],
                'sec2-f2-title' => ['text' => 'Props por locale', 'tag' => 'h3'],
                'sec2-f2-body'  => ['html' => '<p>El contenido vive en <code>widgetPropsByLocale</code> por idioma.</p>'],
                'sec2-f3-title' => ['text' => 'Mercado ES', 'tag' => 'h3'],
                'sec2-f3-body'  => ['html' => '<p>Esta tercera tarjeta enfatiza el mercado hispanohablante (mensaje distinto al EN).</p>'],

                'sec3-image' => ['src' => 'https://picsum.photos/seed/pbk-sections-es/640/420', 'alt' => 'Imagen historia en español'],
                'sec3-title' => ['text' => 'Sección historia (ES)', 'tag' => 'h2'],
                'sec3-body'  => ['html' => '<p>Mensaje solo para ES: priorizamos España y LATAM, con soporte en español y facturación en EUR.</p>'],
                'sec3-note'  => ['html' => '<p class="text-muted small mb-0">Nota ES: precios en EUR · soporte 9–18 CET</p>'],

                'sec4-title'  => ['text' => '¿Listo para traducir?', 'tag' => 'h2'],
                'sec4-body'   => ['html' => '<p>Vuelve a <code>?_locale=en</code> — mismas secciones, props distintas.</p>'],
                'sec4-button' => ['label' => 'Ver variante inglesa', 'url' => '/sections?_locale=en', 'target' => '_self'],
            ],
        ];

        return [
            'structure'           => $structure,
            'widgetPropsByLocale' => $widgetPropsByLocale,
        ];
    }
}
