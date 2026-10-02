<?php

declare(strict_types=1);

namespace App\Demo;

/**
 * Full use-case matrix for the FrankenPHP demo.
 *
 * @phpstan-type UseCase array{
 *     key: string,
 *     route: string,
 *     title_en: string,
 *     title_es: string,
 *     category: string,
 *     engine: 'grapesjs'|'classic',
 *     publish: bool,
 *     description_en: string,
 *     description_es: string,
 *     seed?: bool,
 *     embeds?: list<string>
 * }
 */
final class DemoUseCases
{
    public const int SEED_VERSION = 13;

    /** Page keys rendered together by the multi-render collector demo. */
    public const array MULTI_RENDER_EMBED_KEYS = ['pricing', 'about', 'faq'];

    /**
     * @return list<UseCase>
     */
    public static function all(): array
    {
        return [
            [
                'key'            => 'home',
                'route'          => 'home',
                'title_en'       => 'Home landing',
                'title_es'       => 'Landing inicio',
                'category'       => 'Marketing',
                'engine'         => 'grapesjs',
                'publish'        => true,
                'description_en' => 'Full marketing landing with all compound blocks.',
                'description_es' => 'Landing completa con todos los compounds.',
            ],
            [
                'key'            => 'compounds',
                'route'          => 'compounds',
                'title_en'       => 'Compound gallery',
                'title_es'       => 'Galería compounds',
                'category'       => 'Builder',
                'engine'         => 'grapesjs',
                'publish'        => true,
                'description_en' => 'Every built-in compound DomComponent on one page.',
                'description_es' => 'Todos los compounds built-in en una página.',
            ],
            [
                'key'            => 'pricing',
                'route'          => 'pricing',
                'title_en'       => 'Pricing',
                'title_es'       => 'Precios',
                'category'       => 'Marketing',
                'engine'         => 'grapesjs',
                'publish'        => true,
                'description_en' => 'Three pricing cards + CTA.',
                'description_es' => 'Tres cards de precio + CTA.',
            ],
            [
                'key'            => 'about',
                'route'          => 'about',
                'title_en'       => 'About',
                'title_es'       => 'Acerca de',
                'category'       => 'Content',
                'engine'         => 'grapesjs',
                'publish'        => true,
                'description_en' => 'Media split, features, testimonial, FAQ.',
                'description_es' => 'Media split, features, testimonio, FAQ.',
            ],
            [
                'key'            => 'contact',
                'route'          => 'contact',
                'title_en'       => 'Contact',
                'title_es'       => 'Contacto',
                'category'       => 'Marketing',
                'engine'         => 'grapesjs',
                'publish'        => true,
                'description_en' => 'Contact hero + cards + FAQ.',
                'description_es' => 'Hero de contacto + cards + FAQ.',
            ],
            [
                'key'            => 'blog',
                'route'          => 'blog',
                'title_en'       => 'Blog article',
                'title_es'       => 'Artículo blog',
                'category'       => 'Content',
                'engine'         => 'grapesjs',
                'publish'        => true,
                'description_en' => 'Long-form article layout (header, body, aside CTA).',
                'description_es' => 'Artículo largo (cabecera, cuerpo, CTA lateral).',
            ],
            [
                'key'            => 'faq',
                'route'          => 'faq',
                'title_en'       => 'FAQ hub',
                'title_es'       => 'Centro FAQ',
                'category'       => 'Content',
                'engine'         => 'grapesjs',
                'publish'        => true,
                'description_en' => 'Stacked FAQ compounds for support pages.',
                'description_es' => 'FAQ compounds apilados para soporte.',
            ],
            [
                'key'            => 'portfolio',
                'route'          => 'portfolio',
                'title_en'       => 'Portfolio',
                'title_es'       => 'Portfolio',
                'category'       => 'Marketing',
                'engine'         => 'grapesjs',
                'publish'        => true,
                'description_en' => 'Image grid / case studies.',
                'description_es' => 'Grid de imágenes / casos.',
            ],
            [
                'key'            => 'product',
                'route'          => 'product',
                'title_en'       => 'Product',
                'title_es'       => 'Producto',
                'category'       => 'Commerce',
                'engine'         => 'grapesjs',
                'publish'        => true,
                'description_en' => 'Product detail: gallery + specs + CTA.',
                'description_es' => 'Detalle de producto: galería + specs + CTA.',
            ],
            [
                'key'            => 'newsletter',
                'route'          => 'newsletter',
                'title_en'       => 'Newsletter',
                'title_es'       => 'Newsletter',
                'category'       => 'Forms',
                'engine'         => 'grapesjs',
                'publish'        => true,
                'description_en' => 'Lead capture band with email form markup.',
                'description_es' => 'Captación con markup de formulario email.',
            ],
            [
                'key'            => 'forms',
                'route'          => 'forms',
                'title_en'       => 'Forms showcase',
                'title_es'       => 'Showcase formularios',
                'category'       => 'Forms',
                'engine'         => 'grapesjs',
                'publish'        => true,
                'description_en' => 'Native HTML form fields (GrapesJS forms plugin compatible).',
                'description_es' => 'Campos HTML nativos (compatible plugin forms).',
            ],
            [
                'key'            => 'legal',
                'route'          => 'legal',
                'title_en'       => 'Legal / Terms',
                'title_es'       => 'Legal / Términos',
                'category'       => 'Content',
                'engine'         => 'grapesjs',
                'publish'        => true,
                'description_en' => 'Dense typographic legal page.',
                'description_es' => 'Página legal tipográfica densa.',
            ],
            [
                'key'            => 'empty',
                'route'          => 'empty',
                'title_en'       => 'Empty canvas',
                'title_es'       => 'Canvas vacío',
                'category'       => 'Builder',
                'engine'         => 'grapesjs',
                'publish'        => true,
                'description_en' => 'Minimal starter — open canvas and build from scratch.',
                'description_es' => 'Starter mínimo — abre el canvas y construye.',
            ],
            [
                'key'            => 'i18n',
                'route'          => 'i18n',
                'title_en'       => 'Locale divergence',
                'title_es'       => 'Divergencia de locale',
                'category'       => 'i18n',
                'engine'         => 'grapesjs',
                'publish'        => true,
                'description_en' => 'EN and ES layouts intentionally different (localeContent).',
                'description_es' => 'Layouts EN y ES deliberadamente distintos (localeContent).',
            ],
            [
                'key'            => 'classic',
                'route'          => 'classic',
                'title_en'       => 'Classic schema v1',
                'title_es'       => 'Schema clásico v1',
                'category'       => 'Sections',
                'engine'         => 'classic',
                'publish'        => true,
                'description_en' => 'Section/column/widget tree with nested container + locale props.',
                'description_es' => 'Árbol section/column/widget con container anidado + props por locale.',
            ],
            [
                'key'            => 'sections',
                'route'          => 'sections',
                'title_en'       => 'Multi-section locales',
                'title_es'       => 'Multi-sección por locale',
                'category'       => 'Sections',
                'engine'         => 'classic',
                'publish'        => true,
                'description_en' => 'Hero / features / story / CTA — shared layout, EN≠ES props via Sections editor.',
                'description_es' => 'Hero / features / historia / CTA — layout compartido, props EN≠ES en editor Secciones.',
            ],
            [
                'key'            => 'sections-i18n',
                'route'          => 'sections_i18n',
                'title_en'       => 'Grapes sections ≠ locale',
                'title_es'       => 'Grapes secciones ≠ locale',
                'category'       => 'Sections',
                'engine'         => 'grapesjs',
                'publish'        => true,
                'description_en' => 'EN and ES are different section trees (localeContent), not just translated copy.',
                'description_es' => 'EN y ES son árboles de secciones distintos (localeContent), no solo copy traducido.',
            ],
            [
                'key'            => 'twig',
                'route'          => 'twig',
                'title_en'       => 'Twig variables',
                'title_es'       => 'Variables Twig',
                'category'       => 'Twig',
                'engine'         => 'grapesjs',
                'publish'        => true,
                'description_en' => 'GrapesJS HTML with {{ title }}, {{ locale }}, conditionals (sandboxed).',
                'description_es' => 'HTML GrapesJS con {{ title }}, {{ locale }}, condicionales (sandbox).',
            ],
            [
                'key'            => 'fields',
                'route'          => 'fields',
                'title_en'       => 'Content fields',
                'title_es'       => 'Content fields',
                'category'       => 'Content fields',
                'engine'         => 'grapesjs',
                'publish'        => true,
                'description_en' => 'Typed fields + labels i18n + repeater/reference; slots [[fields.*]] and Twig {{ fields.* }}.',
                'description_es' => 'Campos tipados + labels i18n + repeater/reference; slots [[fields.*]] y Twig {{ fields.* }}.',
            ],
            [
                'key'            => 'seo',
                'route'          => 'seo',
                'title_en'       => 'SEO & accessibility',
                'title_es'       => 'SEO y accesibilidad',
                'category'       => 'SEO',
                'engine'         => 'grapesjs',
                'publish'        => true,
                'description_en' => 'Meta / Open Graph / robots + landmarks, alt text, skip link.',
                'description_es' => 'Meta / Open Graph / robots + landmarks, alt text, skip link.',
            ],
            [
                'key'            => 'draft',
                'route'          => 'draft',
                'title_en'       => 'Draft (unpublished)',
                'title_es'       => 'Borrador (sin publicar)',
                'category'       => 'Workflow',
                'engine'         => 'grapesjs',
                'publish'        => false,
                'description_en' => 'Visible in demo app; /p/draft returns 404 until published.',
                'description_es' => 'Visible en la demo; /p/draft da 404 hasta publicar.',
            ],
            [
                'key'            => 'multi-render',
                'route'          => 'multi_render',
                'title_en'       => 'Multi-render (collector)',
                'title_es'       => 'Multi-render (collector)',
                'category'       => 'Debug',
                'engine'         => 'grapesjs',
                'publish'        => true,
                'seed'           => false,
                'embeds'         => self::MULTI_RENDER_EMBED_KEYS,
                'description_en' => 'One HTTP request renders pricing + about + faq — Web Profiler shows render count ≥ 3.',
                'description_es' => 'Una petición HTTP renderiza pricing + about + faq — el Web Profiler muestra ≥ 3 renders.',
            ],
        ];
    }

    /**
     * @param UseCase $case
     */
    public static function shouldSeed(array $case): bool
    {
        return ($case['seed'] ?? true) !== false;
    }

    /**
     * @return UseCase|null
     */
    public static function byKey(string $key): ?array
    {
        foreach (self::all() as $case) {
            if ($case['key'] === $key) {
                return $case;
            }
        }

        return null;
    }

    /**
     * @return array<string, list<UseCase>>
     */
    public static function byCategory(): array
    {
        $grouped = [];
        foreach (self::all() as $case) {
            $grouped[$case['category']][] = $case;
        }

        return $grouped;
    }
}
