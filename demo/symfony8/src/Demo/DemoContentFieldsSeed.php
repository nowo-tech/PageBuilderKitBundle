<?php

declare(strict_types=1);

namespace App\Demo;

/**
 * Content-fields showcase (Phase 5/6): schema + locale values + Twig/slots in Grapes HTML.
 */
final class DemoContentFieldsSeed
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function schema(): array
    {
        return [
            self::field('hero_title', 'string', 'Hero title', 'Título hero', true),
            self::field('hero_lead', 'text', 'Lead', 'Entradilla', true),
            self::field('show_cta', 'bool', 'Show CTA', 'Mostrar CTA', false, true),
            self::field('cta_url', 'url', 'CTA URL', 'URL CTA', false),
            self::field('hero_image', 'image', 'Hero image', 'Imagen hero', false),
            self::field('related_page', 'reference', 'Related page', 'Página relacionada', false),
            [
                'key'       => 'faqs',
                'type'      => 'repeater',
                'label'     => 'FAQs',
                'labels'    => ['en' => 'FAQs', 'es' => 'Preguntas'],
                'required'  => true,
                'options'   => [],
                'default'   => null,
                'min'       => 1,
                'max'       => 10,
                'reference' => 'page',
                'fields'    => [
                    self::field('question', 'string', 'Question', 'Pregunta', true),
                    self::field('answer', 'text', 'Answer', 'Respuesta', true),
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function values(): array
    {
        return [
            'en' => [
                'hero_title'   => 'Content fields demo',
                'hero_lead'    => 'Edit values in Content admin — layout stays in Grapes. Use field slots without Twig.',
                'show_cta'     => true,
                'cta_url'      => '/admin/page-builder/pages/fields/content',
                'hero_image'   => 'https://picsum.photos/seed/pbk-fields/960/420',
                'related_page' => 'pricing',
                'faqs'         => [
                    ['question' => 'What is a content field?', 'answer' => 'A typed value stored per locale without duplicating Grapes HTML.'],
                    ['question' => 'Do slots need Twig?', 'answer' => 'No. Use [[fields.key]] even when Twig is disabled.'],
                ],
            ],
            'es' => [
                'hero_title'   => 'Demo de content fields',
                'hero_lead'    => 'Edita valores en Contenido — el layout vive en Grapes. Usa slots de campos sin Twig.',
                'show_cta'     => true,
                'cta_url'      => '/admin/page-builder/pages/fields/content',
                'hero_image'   => 'https://picsum.photos/seed/pbk-fields-es/960/420',
                'related_page' => 'pricing',
                'faqs'         => [
                    ['question' => '¿Qué es un content field?', 'answer' => 'Un valor tipado por locale sin duplicar el HTML de Grapes.'],
                    ['question' => '¿Los slots necesitan Twig?', 'answer' => 'No. Usa [[fields.key]] aunque Twig esté desactivado.'],
                ],
            ],
        ];
    }

    public static function html(string $locale): string
    {
        $es = $locale === 'es';
        $v  = DemoUseCases::SEED_VERSION;
        $eyebrow = $es ? 'Campos tipados · Phase 5/6' : 'Typed fields · Phase 5/6';
        $cta    = $es ? 'Editar contenido' : 'Edit content';
        $faqH   = $es ? 'Referencia + FAQ (repeater)' : 'Reference + FAQ (repeater)';
        $refL   = $es ? 'Campo reference apunta a' : 'Reference field points to';

        $inner = <<<HTML
<section class="pbk-band" style="padding:3rem 1.25rem;background:#0f172a;color:#fff;text-align:center">
  <div class="pbk-wrap">
    <p style="color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;font-size:.75rem">{$eyebrow}</p>
    <h1 style="font-size:clamp(1.75rem,4vw,2.5rem);margin:.5rem 0 1rem">[[fields.hero_title]]</h1>
    <p style="opacity:.9;max-width:40rem;margin:0 auto 1.5rem">[[fields.hero_lead]]</p>
    <img src="[[fields.hero_image]]" alt="" style="max-width:100%;border-radius:.75rem;margin:0 auto 1.5rem">
    {% if fields.show_cta %}
      <a class="pbk-btn pbk-btn--primary" href="[[fields.cta_url]]">{$cta}</a>
    {% endif %}
  </div>
</section>
<section class="pbk-band" style="padding:2.5rem 1.25rem">
  <div class="pbk-wrap">
    <h2 style="margin-top:0">{$faqH}</h2>
    <p class="pbk-muted">{$refL} <code>pricing</code> → <strong>{{ fields.related_page }}</strong></p>
    <div style="display:grid;gap:.75rem;margin-top:1.5rem">
      {% for row in fields.faqs %}
        <details class="pbk-compound--faq" open>
          <summary>{{ row.question }}</summary>
          <p>{{ row.answer }}</p>
        </details>
      {% endfor %}
    </div>
  </div>
</section>
HTML;

        return '<div class="pbk-demo" data-pbk-demo-seed="'.$v.'">'.$inner.'</div>';
    }

    /**
     * @return array<string, mixed>
     */
    private static function field(
        string $key,
        string $type,
        string $labelEn,
        string $labelEs,
        bool $required,
        mixed $default = null,
    ): array {
        return [
            'key'       => $key,
            'type'      => $type,
            'label'     => $labelEn,
            'labels'    => ['en' => $labelEn, 'es' => $labelEs],
            'required'  => $required,
            'options'   => [],
            'default'   => $default,
            'fields'    => [],
            'min'       => null,
            'max'       => null,
            'reference' => 'page',
        ];
    }
}
