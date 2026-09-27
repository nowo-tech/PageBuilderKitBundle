<?php

declare(strict_types=1);

namespace App\Demo;

use function sprintf;

/**
 * Additional GrapesJS HTML seeds for expanded use cases.
 */
final class DemoContentExtraPages
{
    public static function wrap(string $inner): string
    {
        $v = DemoUseCases::SEED_VERSION;

        return '<div class="pbk-demo" data-pbk-demo-seed="' . $v . '">' . $inner . '</div>';
    }

    public static function blog(string $locale): string
    {
        if ($locale === 'es') {
            return self::wrap(<<<'HTML'
<article class="pbk-band"><div class="pbk-wrap" style="max-width:760px">
  <p class="pbk-muted" style="text-transform:uppercase;letter-spacing:.08em;font-size:.75rem">Blog · 12 min</p>
  <h1>Cómo montar un page builder en Symfony con GrapesJS</h1>
  <p class="pbk-muted">Por el equipo Nowo · seed demo</p>
  <img src="https://picsum.photos/seed/pbk-blog-es/960/420" alt="Cover" width="960" height="420" style="border-radius:.75rem;margin:1.5rem 0">
  <p>Este artículo es un documento GrapesJS v2 pensado para contenido largo: tipografía cómoda, imagen de portada y CTA al final.</p>
  <h2>Estructura del documento</h2>
  <p>Guardamos <code>html</code>, <code>css</code> y <code>localeContent</code> en Doctrine. El canvas admin sincroniza por pestaña de idioma.</p>
  <h2>Siguiente paso</h2>
  <p>Abre el canvas de <strong>blog</strong> y reescribe el tono de marca.</p>
  <section class="pbk-compound pbk-compound--cta" data-pbk-compound="cta"><div class="pbk-cta-row">
    <div><h2 style="margin:0 0 .35rem">¿Listo para editar?</h2><p style="margin:0">Canvas GrapesJS del artículo.</p></div>
    <a class="pbk-btn pbk-btn--light" href="/admin/page-builder/pages/blog/canvas">Editar artículo</a>
  </div></section>
</div></article>
HTML);
        }

        return self::wrap(<<<'HTML'
<article class="pbk-band"><div class="pbk-wrap" style="max-width:760px">
  <p class="pbk-muted" style="text-transform:uppercase;letter-spacing:.08em;font-size:.75rem">Blog · 12 min read</p>
  <h1>How to ship a Symfony page builder with GrapesJS</h1>
  <p class="pbk-muted">By the Nowo team · demo seed</p>
  <img src="https://picsum.photos/seed/pbk-blog/960/420" alt="Cover" width="960" height="420" style="border-radius:.75rem;margin:1.5rem 0">
  <p>This article is a GrapesJS v2 document aimed at long-form content: comfortable typography, cover image, and a closing CTA.</p>
  <h2>Document shape</h2>
  <p>We persist <code>html</code>, <code>css</code>, and <code>localeContent</code> in Doctrine. The admin canvas syncs per locale tab.</p>
  <h2>Next step</h2>
  <p>Open the <strong>blog</strong> canvas and rewrite the brand voice.</p>
  <section class="pbk-compound pbk-compound--cta" data-pbk-compound="cta"><div class="pbk-cta-row">
    <div><h2 style="margin:0 0 .35rem">Ready to edit?</h2><p style="margin:0">GrapesJS canvas for this article.</p></div>
    <a class="pbk-btn pbk-btn--light" href="/admin/page-builder/pages/blog/canvas">Edit article</a>
  </div></section>
</div></article>
HTML);
    }

    public static function faq(string $locale): string
    {
        $es = $locale === 'es';

        return self::wrap(sprintf(<<<'HTML'
<section class="pbk-compound pbk-compound--hero" data-pbk-compound="hero"><div class="pbk-compound__inner">
  <p class="pbk-compound__eyebrow">%s</p>
  <h1 class="pbk-compound__title">%s</h1>
  <p class="pbk-compound__lead">%s</p>
</div></section>
<section class="pbk-band pbk-wrap">
  <details class="pbk-compound pbk-compound--faq" data-pbk-compound="faq" open><summary>%s</summary><p>%s</p></details>
  <details class="pbk-compound pbk-compound--faq" data-pbk-compound="faq"><summary>%s</summary><p>%s</p></details>
  <details class="pbk-compound pbk-compound--faq" data-pbk-compound="faq"><summary>%s</summary><p>%s</p></details>
  <details class="pbk-compound pbk-compound--faq" data-pbk-compound="faq"><summary>%s</summary><p>%s</p></details>
  <details class="pbk-compound pbk-compound--faq" data-pbk-compound="faq"><summary>%s</summary><p>%s</p></details>
</section>
HTML,
            $es ? 'Soporte' : 'Support',
            $es ? 'Preguntas frecuentes' : 'Frequently asked questions',
            $es ? 'Hub FAQ construido solo con compounds details/summary.' : 'FAQ hub built only with details/summary compounds.',
            $es ? '¿Qué es schema v2?' : 'What is schema v2?',
            $es ? 'Documento GrapesJS con html, css, grapes y localeContent.' : 'GrapesJS document with html, css, grapes, and localeContent.',
            $es ? '¿Sigue existiendo v1?' : 'Does v1 still exist?',
            $es ? 'Sí — mira la página classic.' : 'Yes — see the classic page.',
            $es ? '¿Cómo publico?' : 'How do I publish?',
            $es ? 'Canvas → Publish, o DocumentService::publish().' : 'Canvas → Publish, or DocumentService::publish().',
            $es ? '¿Los scripts están permitidos?' : 'Are scripts allowed?',
            $es ? 'Solo si grapesjs.allow_scripts=true (XSS).' : 'Only if grapesjs.allow_scripts=true (XSS risk).',
            $es ? '¿Credenciales demo?' : 'Demo credentials?',
            $es ? 'admin / admin' : 'admin / admin',
        ));
    }

    public static function portfolio(string $locale): string
    {
        $es = $locale === 'es';

        return self::wrap(sprintf(<<<'HTML'
<section class="pbk-compound pbk-compound--hero" data-pbk-compound="hero"><div class="pbk-compound__inner">
  <p class="pbk-compound__eyebrow">Portfolio</p>
  <h1 class="pbk-compound__title">%s</h1>
  <p class="pbk-compound__lead">%s</p>
</div></section>
<section class="pbk-band"><div class="pbk-wrap">
  <div class="pbk-compound__grid">
    <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><img src="https://picsum.photos/seed/pf1/600/400" alt=""><h3>%s</h3><p class="pbk-muted">%s</p></article>
    <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><img src="https://picsum.photos/seed/pf2/600/400" alt=""><h3>%s</h3><p class="pbk-muted">%s</p></article>
    <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><img src="https://picsum.photos/seed/pf3/600/400" alt=""><h3>%s</h3><p class="pbk-muted">%s</p></article>
  </div>
</div></section>
HTML,
            $es ? 'Casos seleccionados' : 'Selected work',
            $es ? 'Grid de proyectos con cards compound.' : 'Project grid using compound cards.',
            $es ? 'Checkout visual' : 'Visual checkout',
            $es ? 'Commerce + builder' : 'Commerce + builder',
            $es ? 'Portal i18n' : 'i18n portal',
            $es ? '7 locales' : '7 locales',
            $es ? 'Design system' : 'Design system',
            $es ? 'Tokens + canvas' : 'Tokens + canvas',
        ));
    }

    public static function product(string $locale): string
    {
        $es = $locale === 'es';

        return self::wrap(sprintf(<<<'HTML'
<section class="pbk-compound pbk-compound--media-split" data-pbk-compound="media-split"><div class="pbk-wrap"><div class="pbk-split">
  <img src="https://picsum.photos/seed/pbk-product/800/800" alt="Product" width="800" height="800">
  <div>
    <p class="pbk-muted" style="text-transform:uppercase;letter-spacing:.08em;font-size:.75rem">SKU · PBK-100</p>
    <h1>%s</h1>
    <p class="pbk-compound__price" style="font-size:2rem;font-weight:700">€129</p>
    <p class="pbk-muted">%s</p>
    <ul><li>%s</li><li>%s</li><li>%s</li></ul>
    <a class="pbk-btn pbk-btn--dark" href="/contact">%s</a>
  </div>
</div></div></section>
<section class="pbk-compound pbk-compound--feature-grid" data-pbk-compound="feature-grid"><div class="pbk-wrap">
  <h2 class="pbk-section-title">%s</h2>
  <div class="pbk-compound__grid">
    <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><h3>%s</h3><p>%s</p></article>
    <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><h3>%s</h3><p>%s</p></article>
    <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><h3>%s</h3><p>%s</p></article>
  </div>
</div></section>
HTML,
            $es ? 'Kit Page Builder Pro' : 'Page Builder Pro Kit',
            $es ? 'Licencia por editor · updates 12 meses' : 'Per-editor license · 12 months updates',
            $es ? 'Canvas GrapesJS completo' : 'Full GrapesJS canvas',
            $es ? 'Seeds multi-idioma' : 'Multi-locale seeds',
            $es ? 'Schema v1 + v2' : 'Schema v1 + v2',
            $es ? 'Solicitar demo' : 'Request demo',
            $es ? 'Especificaciones' : 'Specs',
            $es ? 'Doctrine' : 'Doctrine',
            $es ? 'Persistencia pb_*' : 'pb_* persistence',
            $es ? 'CSRF API' : 'CSRF API',
            $es ? 'Save/publish JSON' : 'Save/publish JSON',
            $es ? 'Sanitize' : 'Sanitize',
            $es ? 'HTML/CSS allowlist' : 'HTML/CSS allowlist',
        ));
    }

    public static function newsletter(string $locale): string
    {
        $es = $locale === 'es';

        return self::wrap(sprintf(<<<'HTML'
<section class="pbk-compound pbk-compound--hero" data-pbk-compound="hero"><div class="pbk-compound__inner">
  <h1 class="pbk-compound__title">%s</h1>
  <p class="pbk-compound__lead">%s</p>
  <form action="#" method="post" style="display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap;margin-top:1.5rem" onsubmit="return false">
    <input type="email" name="email" required placeholder="%s" style="padding:.75rem 1rem;border-radius:.375rem;border:0;min-width:240px">
    <button type="submit" class="pbk-btn pbk-btn--primary">%s</button>
  </form>
  <p class="pbk-muted" style="margin-top:1rem;font-size:.875rem">%s</p>
</div></section>
HTML,
            $es ? 'Únete a la newsletter' : 'Join the newsletter',
            $es ? 'Formulario HTML nativo listo para el plugin Forms de GrapesJS.' : 'Native HTML form ready for the GrapesJS Forms plugin.',
            $es ? 'tu@email.com' : 'you@email.com',
            $es ? 'Suscribirme' : 'Subscribe',
            $es ? 'Demo: no envía datos reales.' : 'Demo: does not submit real data.',
        ));
    }

    public static function forms(string $locale): string
    {
        $es = $locale === 'es';

        return self::wrap(sprintf(<<<'HTML'
<section class="pbk-band"><div class="pbk-wrap" style="max-width:640px">
  <h1>%s</h1>
  <p class="pbk-muted">%s</p>
  <form class="pbk-demo-form" action="#" method="post" onsubmit="return false" style="display:grid;gap:1rem;margin-top:1.5rem">
    <label>%s<input name="name" style="display:block;width:100%%;padding:.65rem;margin-top:.35rem"></label>
    <label>%s<input type="email" name="email" style="display:block;width:100%%;padding:.65rem;margin-top:.35rem"></label>
    <label>%s<select name="topic" style="display:block;width:100%%;padding:.65rem;margin-top:.35rem"><option>%s</option><option>%s</option></select></label>
    <label><input type="checkbox" name="terms"> %s</label>
    <label>%s<textarea name="message" rows="4" style="display:block;width:100%%;padding:.65rem;margin-top:.35rem"></textarea></label>
    <button type="submit" class="pbk-btn pbk-btn--dark">%s</button>
  </form>
</div></section>
HTML,
            $es ? 'Showcase de formularios' : 'Forms showcase',
            $es ? 'Campos que el plugin grapesjs-plugin-forms también puede insertar desde el canvas.' : 'Fields the grapesjs-plugin-forms plugin can also insert from the canvas.',
            $es ? 'Nombre' : 'Name',
            $es ? 'Email' : 'Email',
            $es ? 'Tema' : 'Topic',
            $es ? 'Soporte' : 'Support',
            $es ? 'Ventas' : 'Sales',
            $es ? 'Acepto términos' : 'Accept terms',
            $es ? 'Mensaje' : 'Message',
            $es ? 'Enviar (demo)' : 'Submit (demo)',
        ));
    }

    public static function legal(string $locale): string
    {
        $es = $locale === 'es';

        return self::wrap(sprintf(<<<'HTML'
<section class="pbk-band"><div class="pbk-wrap" style="max-width:720px">
  <h1>%s</h1>
  <p class="pbk-muted">%s</p>
  <h2>1. %s</h2>
  <p>%s</p>
  <h2>2. %s</h2>
  <p>%s</p>
  <h2>3. %s</h2>
  <p>%s</p>
</div></section>
HTML,
            $es ? 'Términos de uso (demo)' : 'Terms of use (demo)',
            $es ? 'Página legal densa — tipografía simple, sin marketing chrome.' : 'Dense legal page — plain typography, no marketing chrome.',
            $es ? 'Objeto' : 'Purpose',
            $es ? 'Estos términos ilustran un caso de uso de contenido estático multilinea.' : 'These terms illustrate a multi-line static content use case.',
            $es ? 'Datos' : 'Data',
            $es ? 'La demo no almacena PII fuera de Doctrine de páginas.' : 'The demo does not store PII outside page Doctrine entities.',
            $es ? 'Contacto' : 'Contact',
            $es ? 'Usa la página contact o el canvas admin.' : 'Use the contact page or the admin canvas.',
        ));
    }

    public static function emptyPage(string $locale): string
    {
        $es = $locale === 'es';

        return self::wrap(sprintf(
            '<section class="pbk-band"><div class="pbk-wrap" style="text-align:center;padding:4rem 1rem"><p class="pbk-muted">%s</p><p><a class="pbk-btn pbk-btn--dark" href="/admin/page-builder/pages/empty/canvas">%s</a></p></div></section>',
            $es ? 'Página casi vacía — empieza desde el canvas.' : 'Nearly empty page — start from the canvas.',
            $es ? 'Abrir canvas vacío' : 'Open empty canvas',
        ));
    }

    public static function i18n(string $locale): string
    {
        if ($locale === 'es') {
            return self::wrap(<<<'HTML'
<section class="pbk-compound pbk-compound--hero" data-pbk-compound="hero" style="background:#7c2d12"><div class="pbk-compound__inner">
  <p class="pbk-compound__eyebrow">Solo ES</p>
  <h1 class="pbk-compound__title">Este layout es distinto al inglés</h1>
  <p class="pbk-compound__lead">localeContent.es no reutiliza el HTML de EN — prueba ?_locale=en</p>
</div></section>
<section class="pbk-band pbk-wrap"><div class="pbk-compound__grid">
  <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><h3>Bloque A (ES)</h3><p>Contenido solo en español.</p></article>
  <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><h3>Bloque B (ES)</h3><p>Estructura diferente a EN.</p></article>
</div></section>
HTML);
        }

        return self::wrap(<<<'HTML'
<section class="pbk-compound pbk-compound--hero" data-pbk-compound="hero" style="background:#14532d"><div class="pbk-compound__inner">
  <p class="pbk-compound__eyebrow">EN only</p>
  <h1 class="pbk-compound__title">This layout diverges from Spanish</h1>
  <p class="pbk-compound__lead">localeContent.en is intentionally different — try ?_locale=es</p>
</div></section>
<section class="pbk-compound pbk-compound--stats" data-pbk-compound="stats"><div class="pbk-wrap"><div class="pbk-stats">
  <div><div class="pbk-stat-value">EN</div><div class="pbk-stat-label">Green hero</div></div>
  <div><div class="pbk-stat-value">ES</div><div class="pbk-stat-label">Terracotta hero</div></div>
  <div><div class="pbk-stat-value">2</div><div class="pbk-stat-label">Trees</div></div>
</div></div></section>
HTML);
    }

    public static function draft(string $locale): string
    {
        $es = $locale === 'es';

        return self::wrap(sprintf(<<<'HTML'
<section class="pbk-compound pbk-compound--hero" data-pbk-compound="hero" style="background:#334155"><div class="pbk-compound__inner">
  <p class="pbk-compound__eyebrow">%s</p>
  <h1 class="pbk-compound__title">%s</h1>
  <p class="pbk-compound__lead">%s</p>
  <div class="pbk-compound__actions">
    <a class="pbk-btn pbk-btn--primary" href="/admin/page-builder/pages/draft/canvas">%s</a>
    <a class="pbk-btn pbk-btn--ghost" href="/p/draft">%s</a>
  </div>
</div></section>
HTML,
            $es ? 'Workflow' : 'Workflow',
            $es ? 'Borrador sin publicar' : 'Unpublished draft',
            $es ? 'La app demo la muestra; /p/draft debe responder 404.' : 'Demo app shows it; /p/draft should 404.',
            $es ? 'Editar borrador' : 'Edit draft',
            $es ? 'Probar /p/draft' : 'Try /p/draft',
        ));
    }

    public static function twig(string $locale): string
    {
        // Twig tokens below are evaluated on public render by GrapesTwigRenderer.
        return self::wrap(<<<'HTML'
<section class="pbk-compound pbk-compound--hero" data-pbk-compound="hero"><div class="pbk-compound__inner">
  <p class="pbk-compound__eyebrow">Twig · sandboxed</p>
  <h1 class="pbk-compound__title">{{ title }}</h1>
  <p class="pbk-compound__lead">pageKey={{ pageKey }} · locale={{ locale }} · slug={{ slug }} · status={{ status }}</p>
  <p>{% if locale == "es" %}Estás viendo la variante española.{% else %}You are viewing the English variant.{% endif %}</p>
  <p>Nested: <strong>{{ page.title }}</strong></p>
  {% if demoApp is defined %}<p class="pbk-muted">Host var demoApp={{ demoApp }}</p>{% endif %}
  <div class="pbk-compound__actions">
    <a class="pbk-btn pbk-btn--primary" href="/admin/page-builder/pages/twig/canvas">Edit canvas</a>
    <a class="pbk-btn pbk-btn--ghost" href="/showcase">Use cases</a>
  </div>
</div></section>
<section class="pbk-band pbk-wrap">
  <h2 class="pbk-section-title">How it works</h2>
  <p class="pbk-muted">Editors insert Twig tokens in GrapesJS (category Twig). On render, a sandboxed Twig environment interpolates variables, then HTML is sanitized again.</p>
  <ul>
    <li>Built-in: title, slug, pageKey, locale, status, page.*</li>
    <li>Host extras via GrapesTwigContextProviderInterface</li>
    <li>Allowed tags: if, for, set — no include/embed/extends</li>
  </ul>
</section>
HTML);
    }

    public static function seo(string $locale): string
    {
        $es = $locale === 'es';

        return self::wrap(sprintf(
            <<<'HTML'
<a class="pbk-skip-link" href="#pbk-main-content">%s</a>
<nav class="pbk-landmark pbk-landmark--nav" role="navigation" aria-label="%s" style="padding:1rem 1.25rem;border-bottom:1px solid #e2e8f0;">
  <ul style="display:flex;gap:1rem;list-style:none;margin:0;padding:0;">
    <li><a href="/showcase">Showcase</a></li>
    <li><a href="/seo">SEO</a></li>
    <li><a href="/admin/page-builder/pages/seo/seo">Admin SEO</a></li>
  </ul>
</nav>
<main id="pbk-main-content" class="pbk-landmark pbk-landmark--main" role="main" aria-label="%s">
<section class="pbk-compound pbk-compound--hero" data-pbk-compound="hero"><div class="pbk-compound__inner">
  <p class="pbk-compound__eyebrow">SEO · a11y</p>
  <h1 class="pbk-compound__title">%s</h1>
  <p class="pbk-compound__lead">%s</p>
  <div class="pbk-compound__actions">
    <a class="pbk-btn pbk-btn--primary" href="/admin/page-builder/pages/seo/seo">%s</a>
    <a class="pbk-btn pbk-btn--ghost" href="/p/seo" target="_blank" rel="noopener">/p/seo</a>
  </div>
</div></section>
<section class="pbk-band pbk-wrap" aria-labelledby="seo-a11y-heading">
  <h2 id="seo-a11y-heading" class="pbk-section-title">%s</h2>
  <p class="pbk-muted">%s</p>
  <figure style="margin:1.5rem 0 0;">
    <img src="https://picsum.photos/seed/pbk-seo/960/420" alt="%s" width="960" height="420" style="max-width:100%%;height:auto;border-radius:.75rem;">
    <figcaption style="margin-top:.5rem;color:#64748b;font-size:.875rem;">%s</figcaption>
  </figure>
  <p style="margin-top:1rem;"><span class="pbk-decorative-icon" aria-hidden="true" style="display:inline-flex;width:2rem;height:2rem;align-items:center;justify-content:center;border-radius:999px;background:#e2e8f0;margin-right:.5rem;">★</span>%s</p>
</section>
</main>
<footer class="pbk-landmark pbk-landmark--footer" role="contentinfo" aria-label="%s" style="padding:1.5rem;border-top:1px solid #e2e8f0;margin-top:2rem;">
  <p style="margin:0;color:#64748b;font-size:.875rem;">%s</p>
</footer>
HTML,
            $es ? 'Saltar al contenido' : 'Skip to content',
            $es ? 'Navegación principal' : 'Primary navigation',
            $es ? 'Contenido principal' : 'Main content',
            $es ? 'SEO y accesibilidad' : 'SEO & accessibility',
            $es
                ? 'Meta title/description, Open Graph y robots viven en la traducción de la página. El canvas aporta landmarks, alt y skip link.'
                : 'Meta title/description, Open Graph and robots live on the page translation. The canvas adds landmarks, alt text and a skip link.',
            $es ? 'Editar SEO' : 'Edit SEO',
            $es ? 'Buenas prácticas en el HTML' : 'HTML best practices',
            $es
                ? 'Usa landmarks (main/nav/footer), alt descriptivo en imágenes y aria-hidden en iconos decorativos. Categoría A11y en el Block Manager.'
                : 'Use landmarks (main/nav/footer), descriptive image alt, and aria-hidden on decorative icons. A11y category in the Block Manager.',
            $es ? 'Paisaje de montañas al amanecer, ilustración de demo SEO' : 'Mountain landscape at sunrise, SEO demo illustration',
            $es ? 'Figura con figcaption para contexto adicional.' : 'Figure with figcaption for extra context.',
            $es ? 'Icono decorativo (oculto a lectores de pantalla).' : 'Decorative icon (hidden from screen readers).',
            $es ? 'Pie de página' : 'Footer',
            $es ? 'Page Builder Kit · demo SEO' : 'Page Builder Kit · SEO demo',
        ));
    }
}
