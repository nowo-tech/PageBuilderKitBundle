<?php

declare(strict_types=1);

namespace App\Demo;

/**
 * Pre-built GrapesJS HTML/CSS showcases for the FrankenPHP demo.
 * Seed marker: data-pbk-demo-seed="{version}" must match DemoContentCatalog::SEED_VERSION.
 */
final class DemoContentCatalog
{
    /**
     * @return list<array{key: string, title_en: string, title_es: string, route: string}>
     */
    public static function pages(): array
    {
        return array_map(
            static fn (array $case): array => [
                'key'      => $case['key'],
                'title_en' => $case['title_en'],
                'title_es' => $case['title_es'],
                'route'    => $case['route'],
            ],
            DemoUseCases::all(),
        );
    }

    public static function sharedCss(): string
    {
        return <<<'CSS'
.pbk-demo{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:#0f172a;line-height:1.55}
.pbk-demo a{color:inherit}
.pbk-demo img{max-width:100%;height:auto;display:block}
.pbk-demo .pbk-wrap{max-width:1100px;margin:0 auto;padding:0 1.25rem}
.pbk-demo .pbk-compound--hero{padding:4.5rem 1.5rem;background:#0f172a;color:#f8fafc;text-align:center}
.pbk-demo .pbk-compound--hero .pbk-compound__inner{max-width:720px;margin:0 auto}
.pbk-demo .pbk-compound--hero .pbk-compound__eyebrow{text-transform:uppercase;letter-spacing:.08em;font-size:.75rem;opacity:.8;margin-bottom:.75rem}
.pbk-demo .pbk-compound--hero .pbk-compound__title{font-size:clamp(1.85rem,4vw,2.75rem);line-height:1.15;margin:0 0 1rem}
.pbk-demo .pbk-compound--hero .pbk-compound__lead{font-size:1.125rem;opacity:.9;margin:0 0 1.5rem}
.pbk-demo .pbk-compound__actions{display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap}
.pbk-demo .pbk-btn{display:inline-block;padding:.75rem 1.25rem;border-radius:.375rem;text-decoration:none;font-weight:600}
.pbk-demo .pbk-btn--primary{background:#38bdf8;color:#0f172a}
.pbk-demo .pbk-btn--ghost{border:1px solid #94a3b8;color:#f8fafc}
.pbk-demo .pbk-btn--dark{background:#0f172a;color:#fff}
.pbk-demo .pbk-btn--light{background:#fff;color:#1d4ed8}
.pbk-demo .pbk-compound--feature-grid{padding:3rem 0}
.pbk-demo .pbk-compound__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem}
.pbk-demo .pbk-compound--feature-card{padding:1.5rem;border:1px solid #e2e8f0;border-radius:.75rem;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.06)}
.pbk-demo .pbk-compound--feature-card .pbk-compound__icon{font-size:1.75rem;margin-bottom:.75rem}
.pbk-demo .pbk-compound--feature-card h3{font-size:1.125rem;margin:0 0 .5rem}
.pbk-demo .pbk-compound--feature-card p{color:#475569;margin:0}
.pbk-demo .pbk-compound--cta{padding:2.5rem 1.5rem;background:linear-gradient(135deg,#1d4ed8,#0ea5e9);color:#fff;border-radius:.75rem;margin:1.5rem 0}
.pbk-demo .pbk-compound--cta .pbk-cta-row{max-width:900px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:1.5rem;flex-wrap:wrap}
.pbk-demo .pbk-compound--testimonial{padding:2rem;border-left:4px solid #0ea5e9;background:#f8fafc;border-radius:0 .75rem .75rem 0;margin:1.5rem 0}
.pbk-demo .pbk-compound--testimonial .pbk-compound__quote{font-size:1.125rem;font-style:italic;margin:0 0 1rem}
.pbk-demo .pbk-compound--testimonial footer{display:flex;align-items:center;gap:.75rem}
.pbk-demo .pbk-compound--testimonial img{border-radius:999px;width:48px;height:48px;object-fit:cover}
.pbk-demo .pbk-compound--pricing{padding:2rem;border:1px solid #cbd5e1;border-radius:1rem;text-align:center;background:#fff}
.pbk-demo .pbk-compound--pricing .pbk-compound__price{font-size:2.25rem;font-weight:700;margin:.5rem 0 1rem}
.pbk-demo .pbk-pricing-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;align-items:stretch}
.pbk-demo .pbk-compound--pricing ul{list-style:none;padding:0;margin:0 0 1.5rem;text-align:left}
.pbk-demo .pbk-compound--pricing li{padding:.35rem 0;border-bottom:1px solid #e2e8f0}
.pbk-demo .pbk-compound--pricing li:last-child{border-bottom:0}
.pbk-demo .pbk-compound--media-split{padding:2.5rem 0}
.pbk-demo .pbk-compound--media-split .pbk-split{display:grid;grid-template-columns:1fr 1fr;gap:2rem;align-items:center}
.pbk-demo .pbk-compound--media-split img{border-radius:.75rem;width:100%}
.pbk-demo .pbk-compound--stats{padding:2.5rem 0;background:#f1f5f9}
.pbk-demo .pbk-compound--stats .pbk-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;text-align:center}
.pbk-demo .pbk-compound--stats .pbk-stat-value{font-size:2rem;font-weight:700}
.pbk-demo .pbk-compound--stats .pbk-stat-label{color:#64748b}
.pbk-demo .pbk-compound--faq{padding:1rem 1.25rem;border:1px solid #e2e8f0;border-radius:.5rem;margin:.5rem 0;background:#fff}
.pbk-demo .pbk-compound--faq summary{font-weight:600;cursor:pointer}
.pbk-demo .pbk-compound--faq p{margin:.75rem 0 0;color:#475569}
.pbk-demo .pbk-section-title{text-align:center;margin:0 0 2rem;font-size:1.75rem}
.pbk-demo .pbk-muted{color:#64748b}
.pbk-demo .pbk-band{padding:2.5rem 0}
@media (max-width:768px){
  .pbk-demo .pbk-compound__grid,
  .pbk-demo .pbk-pricing-grid,
  .pbk-demo .pbk-compound--media-split .pbk-split,
  .pbk-demo .pbk-compound--stats .pbk-stats{grid-template-columns:1fr}
}
CSS;
    }

    /**
     * @return array{html: string, css: string}
     */
    public static function contentFor(string $pageKey, string $locale): array
    {
        $css = self::sharedCss();
        $html = match ($pageKey) {
            'home' => $locale === 'es' ? self::homeEs() : self::homeEn(),
            'compounds' => $locale === 'es' ? self::compoundsEs() : self::compoundsEn(),
            'pricing' => $locale === 'es' ? self::pricingEs() : self::pricingEn(),
            'about' => $locale === 'es' ? self::aboutEs() : self::aboutEn(),
            'contact' => $locale === 'es' ? self::contactEs() : self::contactEn(),
            'blog' => DemoContentExtraPages::blog($locale),
            'faq' => DemoContentExtraPages::faq($locale),
            'portfolio' => DemoContentExtraPages::portfolio($locale),
            'product' => DemoContentExtraPages::product($locale),
            'newsletter' => DemoContentExtraPages::newsletter($locale),
            'forms' => DemoContentExtraPages::forms($locale),
            'legal' => DemoContentExtraPages::legal($locale),
            'empty' => DemoContentExtraPages::emptyPage($locale),
            'i18n' => DemoContentExtraPages::i18n($locale),
            'draft' => DemoContentExtraPages::draft($locale),
            'twig' => DemoContentExtraPages::twig($locale),
            'fields' => DemoContentFieldsSeed::html($locale),
            'seo' => DemoContentExtraPages::seo($locale),
            'sections-i18n' => DemoGrapesSectionsI18n::html($locale),
            default => $locale === 'es'
                ? self::wrap('<section class="pbk-band"><div class="pbk-wrap"><h1>Página demo</h1><p>Edítala en el canvas.</p></div></section>')
                : self::wrap('<section class="pbk-band"><div class="pbk-wrap"><h1>Demo page</h1><p>Edit it in the canvas.</p></div></section>'),
        };

        return ['html' => $html, 'css' => $css];
    }

    private static function wrap(string $inner): string
    {
        $v = DemoUseCases::SEED_VERSION;

        return '<div class="pbk-demo" data-pbk-demo-seed="' . $v . '">' . $inner . '</div>';
    }

    private static function homeEn(): string
    {
        return self::wrap(<<<'HTML'
<section class="pbk-compound pbk-compound--hero" data-pbk-compound="hero">
  <div class="pbk-compound__inner">
    <p class="pbk-compound__eyebrow">Page Builder Kit · GrapesJS</p>
    <h1 class="pbk-compound__title">Ship Elementor-like pages on Symfony</h1>
    <p class="pbk-compound__lead">This landing is a pre-built GrapesJS document: hero, feature grid, stats, media split, testimonials, pricing, FAQ and CTA — all editable in the admin canvas.</p>
    <div class="pbk-compound__actions">
      <a class="pbk-btn pbk-btn--primary" href="/admin/page-builder/pages/home/canvas">Open canvas</a>
      <a class="pbk-btn pbk-btn--ghost" href="/showcase">All use cases</a>
    </div>
  </div>
</section>

<section class="pbk-compound pbk-compound--stats" data-pbk-compound="stats">
  <div class="pbk-wrap">
    <div class="pbk-stats">
      <div><div class="pbk-stat-value">5</div><div class="pbk-stat-label">Demo pages seeded</div></div>
      <div><div class="pbk-stat-value">9</div><div class="pbk-stat-label">Compound block types</div></div>
      <div><div class="pbk-stat-value">2</div><div class="pbk-stat-label">Locales (EN / ES)</div></div>
    </div>
  </div>
</section>

<section class="pbk-compound pbk-compound--feature-grid" data-pbk-compound="feature-grid">
  <div class="pbk-wrap">
    <h2 class="pbk-section-title">Why this demo exists</h2>
    <div class="pbk-compound__grid">
      <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card">
        <div class="pbk-compound__icon" aria-hidden="true">▣</div>
        <h3>Visual canvas</h3>
        <p>GrapesJS Block Manager with layout, basic and compound categories.</p>
      </article>
      <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card">
        <div class="pbk-compound__icon" aria-hidden="true">⎇</div>
        <h3>Locale tabs</h3>
        <p>EN and ES HTML live under <code>localeContent</code> with fallback.</p>
      </article>
      <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card">
        <div class="pbk-compound__icon" aria-hidden="true">⬡</div>
        <h3>Safe render</h3>
        <p>Public HTML/CSS pass through <code>GrapesDocumentSanitizer</code>.</p>
      </article>
    </div>
  </div>
</section>

<section class="pbk-compound pbk-compound--media-split" data-pbk-compound="media-split">
  <div class="pbk-wrap">
    <div class="pbk-split">
      <img src="https://picsum.photos/seed/pbk-home/800/520" alt="Editor preview" width="800" height="520">
      <div>
        <h2>Edit every nested node</h2>
        <p class="pbk-muted">Compound blocks are DomComponents trees. Select a child in Layers, change Style Manager values, save, publish, preview.</p>
        <p><a class="pbk-btn pbk-btn--dark" href="/p/home">Public route /p/home</a></p>
      </div>
    </div>
  </div>
</section>

<blockquote class="pbk-compound pbk-compound--testimonial pbk-wrap" data-pbk-compound="testimonial">
  <p class="pbk-compound__quote">“We needed Symfony-native page building without forking a React stack. GrapesJS compounds got us there.”</p>
  <footer>
    <img src="https://i.pravatar.cc/96?u=pbk-alex" alt="Alex Rivera" width="48" height="48">
    <div><strong>Alex Rivera</strong><div class="pbk-muted">Product designer</div></div>
  </footer>
</blockquote>

<section class="pbk-band">
  <div class="pbk-wrap">
    <h2 class="pbk-section-title">Plans that mirror compound pricing cards</h2>
    <div class="pbk-pricing-grid">
      <article class="pbk-compound pbk-compound--pricing" data-pbk-compound="pricing">
        <h3>Starter</h3>
        <p class="pbk-compound__price">€0</p>
        <p class="pbk-muted">Explore the demo</p>
        <ul><li>All seed pages</li><li>Read-only public routes</li><li>Login to edit</li></ul>
        <a class="pbk-btn pbk-btn--dark" href="/login">Sign in</a>
      </article>
      <article class="pbk-compound pbk-compound--pricing" data-pbk-compound="pricing">
        <h3>Pro</h3>
        <p class="pbk-compound__price">€29</p>
        <p class="pbk-muted">per editor / month</p>
        <ul><li>Unlimited pages</li><li>Locale-aware canvas</li><li>Custom compounds</li></ul>
        <a class="pbk-btn pbk-btn--dark" href="/pricing">See pricing page</a>
      </article>
      <article class="pbk-compound pbk-compound--pricing" data-pbk-compound="pricing">
        <h3>Enterprise</h3>
        <p class="pbk-compound__price">Talk</p>
        <p class="pbk-muted">SSO &amp; hardening</p>
        <ul><li>Access roles</li><li>Allowlist sanitize</li><li>FrankenPHP ready</li></ul>
        <a class="pbk-btn pbk-btn--dark" href="/contact">Contact</a>
      </article>
    </div>
  </div>
</section>

<section class="pbk-band pbk-wrap">
  <h2 class="pbk-section-title">FAQ</h2>
  <details class="pbk-compound pbk-compound--faq" data-pbk-compound="faq" open>
    <summary>Where is this HTML stored?</summary>
    <p>In <code>BuilderDocument.structure</code> as GrapesJS schema v2 (<code>html</code>, <code>css</code>, <code>localeContent</code>).</p>
  </details>
  <details class="pbk-compound pbk-compound--faq" data-pbk-compound="faq">
    <summary>Can I rebuild seeds?</summary>
    <p>Bump <code>DemoContentCatalog::SEED_VERSION</code> or delete pages in admin — the demo reseeds when the marker is missing/outdated.</p>
  </details>
  <details class="pbk-compound pbk-compound--faq" data-pbk-compound="faq">
    <summary>Credentials?</summary>
    <p><code>admin</code> / <code>admin</code> — then open any canvas from the top nav.</p>
  </details>
</section>

<section class="pbk-compound pbk-compound--cta" data-pbk-compound="cta">
  <div class="pbk-cta-row">
    <div>
      <h2 style="margin:0 0 .35rem">Ready to tweak this page?</h2>
      <p style="margin:0;opacity:.95">Open the GrapesJS canvas for <strong>home</strong> and rearrange compounds.</p>
    </div>
    <a class="pbk-btn pbk-btn--light" href="/admin/page-builder/pages/home/canvas">Edit home</a>
  </div>
</section>
HTML);
    }

    private static function homeEs(): string
    {
        return self::wrap(<<<'HTML'
<section class="pbk-compound pbk-compound--hero" data-pbk-compound="hero">
  <div class="pbk-compound__inner">
    <p class="pbk-compound__eyebrow">Page Builder Kit · GrapesJS</p>
    <h1 class="pbk-compound__title">Páginas tipo Elementor en Symfony</h1>
    <p class="pbk-compound__lead">Esta landing es un documento GrapesJS ya montado: hero, features, stats, media, testimonios, precios, FAQ y CTA — todo editable en el canvas admin.</p>
    <div class="pbk-compound__actions">
      <a class="pbk-btn pbk-btn--primary" href="/admin/page-builder/pages/home/canvas">Abrir canvas</a>
      <a class="pbk-btn pbk-btn--ghost" href="/showcase">Todos los casos</a>
    </div>
  </div>
</section>

<section class="pbk-compound pbk-compound--stats" data-pbk-compound="stats">
  <div class="pbk-wrap">
    <div class="pbk-stats">
      <div><div class="pbk-stat-value">5</div><div class="pbk-stat-label">Páginas demo sembradas</div></div>
      <div><div class="pbk-stat-value">9</div><div class="pbk-stat-label">Tipos compound</div></div>
      <div><div class="pbk-stat-value">2</div><div class="pbk-stat-label">Idiomas (EN / ES)</div></div>
    </div>
  </div>
</section>

<section class="pbk-compound pbk-compound--feature-grid" data-pbk-compound="feature-grid">
  <div class="pbk-wrap">
    <h2 class="pbk-section-title">Para qué sirve esta demo</h2>
    <div class="pbk-compound__grid">
      <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card">
        <div class="pbk-compound__icon" aria-hidden="true">▣</div>
        <h3>Canvas visual</h3>
        <p>Block Manager con categorías Layout, Basic y Compound.</p>
      </article>
      <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card">
        <div class="pbk-compound__icon" aria-hidden="true">⎇</div>
        <h3>Pestañas de locale</h3>
        <p>HTML EN/ES en <code>localeContent</code> con fallback.</p>
      </article>
      <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card">
        <div class="pbk-compound__icon" aria-hidden="true">⬡</div>
        <h3>Render seguro</h3>
        <p>HTML/CSS público pasan por <code>GrapesDocumentSanitizer</code>.</p>
      </article>
    </div>
  </div>
</section>

<section class="pbk-compound pbk-compound--media-split" data-pbk-compound="media-split">
  <div class="pbk-wrap">
    <div class="pbk-split">
      <img src="https://picsum.photos/seed/pbk-home-es/800/520" alt="Vista del editor" width="800" height="520">
      <div>
        <h2>Edita cada nodo anidado</h2>
        <p class="pbk-muted">Los compounds son árboles DomComponents. Selecciona un hijo, cambia estilos, guarda, publica y previsualiza.</p>
        <p><a class="pbk-btn pbk-btn--dark" href="/p/home">Ruta pública /p/home</a></p>
      </div>
    </div>
  </div>
</section>

<blockquote class="pbk-compound pbk-compound--testimonial pbk-wrap" data-pbk-compound="testimonial">
  <p class="pbk-compound__quote">“Necesitábamos page building nativo en Symfony sin un stack React. Los compounds de GrapesJS lo resolvieron.”</p>
  <footer>
    <img src="https://i.pravatar.cc/96?u=pbk-alex" alt="Alex Rivera" width="48" height="48">
    <div><strong>Alex Rivera</strong><div class="pbk-muted">Product designer</div></div>
  </footer>
</blockquote>

<section class="pbk-band">
  <div class="pbk-wrap">
    <h2 class="pbk-section-title">Planes con cards de pricing</h2>
    <div class="pbk-pricing-grid">
      <article class="pbk-compound pbk-compound--pricing" data-pbk-compound="pricing">
        <h3>Starter</h3>
        <p class="pbk-compound__price">€0</p>
        <p class="pbk-muted">Explora la demo</p>
        <ul><li>Todas las seeds</li><li>Rutas públicas</li><li>Login para editar</li></ul>
        <a class="pbk-btn pbk-btn--dark" href="/login">Entrar</a>
      </article>
      <article class="pbk-compound pbk-compound--pricing" data-pbk-compound="pricing">
        <h3>Pro</h3>
        <p class="pbk-compound__price">€29</p>
        <p class="pbk-muted">por editor / mes</p>
        <ul><li>Páginas ilimitadas</li><li>Canvas multi-idioma</li><li>Compounds custom</li></ul>
        <a class="pbk-btn pbk-btn--dark" href="/pricing">Ver precios</a>
      </article>
      <article class="pbk-compound pbk-compound--pricing" data-pbk-compound="pricing">
        <h3>Enterprise</h3>
        <p class="pbk-compound__price">Hablar</p>
        <p class="pbk-muted">SSO y hardening</p>
        <ul><li>Roles de acceso</li><li>Sanitize allowlist</li><li>FrankenPHP</li></ul>
        <a class="pbk-btn pbk-btn--dark" href="/contact">Contacto</a>
      </article>
    </div>
  </div>
</section>

<section class="pbk-band pbk-wrap">
  <h2 class="pbk-section-title">FAQ</h2>
  <details class="pbk-compound pbk-compound--faq" data-pbk-compound="faq" open>
    <summary>¿Dónde se guarda este HTML?</summary>
    <p>En <code>BuilderDocument.structure</code> como schema GrapesJS v2 (<code>html</code>, <code>css</code>, <code>localeContent</code>).</p>
  </details>
  <details class="pbk-compound pbk-compound--faq" data-pbk-compound="faq">
    <summary>¿Cómo regenero las seeds?</summary>
    <p>Sube <code>DemoContentCatalog::SEED_VERSION</code> o borra las páginas en admin: la demo regenera si falta el marcador.</p>
  </details>
  <details class="pbk-compound pbk-compound--faq" data-pbk-compound="faq">
    <summary>¿Credenciales?</summary>
    <p><code>admin</code> / <code>admin</code> — luego abre cualquier canvas desde la barra superior.</p>
  </details>
</section>

<section class="pbk-compound pbk-compound--cta" data-pbk-compound="cta">
  <div class="pbk-cta-row">
    <div>
      <h2 style="margin:0 0 .35rem">¿Listo para retocar esta página?</h2>
      <p style="margin:0;opacity:.95">Abre el canvas GrapesJS de <strong>home</strong> y reordena compounds.</p>
    </div>
    <a class="pbk-btn pbk-btn--light" href="/admin/page-builder/pages/home/canvas">Editar home</a>
  </div>
</section>
HTML);
    }

    private static function compoundsEn(): string
    {
        return self::wrap(<<<'HTML'
<section class="pbk-compound pbk-compound--hero" data-pbk-compound="hero">
  <div class="pbk-compound__inner">
    <p class="pbk-compound__eyebrow">Compound gallery</p>
    <h1 class="pbk-compound__title">Every built-in compound on one page</h1>
    <p class="pbk-compound__lead">Scroll and inspect. In the canvas, the same types appear under the Compound category.</p>
    <div class="pbk-compound__actions">
      <a class="pbk-btn pbk-btn--primary" href="/admin/page-builder/pages/compounds/canvas">Edit gallery</a>
    </div>
  </div>
</section>
<section class="pbk-band pbk-wrap">
  <h2 class="pbk-section-title">Feature card</h2>
  <div class="pbk-compound__grid">
    <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><div class="pbk-compound__icon">①</div><h3>Card A</h3><p>Nested icon, title and body.</p></article>
    <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><div class="pbk-compound__icon">②</div><h3>Card B</h3><p>Style each node independently.</p></article>
    <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><div class="pbk-compound__icon">③</div><h3>Card C</h3><p>Used inside Feature grid.</p></article>
  </div>
</section>
<section class="pbk-compound pbk-compound--media-split" data-pbk-compound="media-split"><div class="pbk-wrap"><div class="pbk-split">
  <img src="https://picsum.photos/seed/pbk-compounds/720/480" alt="Media" width="720" height="480">
  <div><h2>Media + text</h2><p class="pbk-muted">Two-column compound with image and copy CTA.</p><a class="pbk-btn pbk-btn--dark" href="#">Action</a></div>
</div></div></section>
<section class="pbk-compound pbk-compound--stats" data-pbk-compound="stats"><div class="pbk-wrap"><div class="pbk-stats">
  <div><div class="pbk-stat-value">01</div><div class="pbk-stat-label">Hero</div></div>
  <div><div class="pbk-stat-value">02</div><div class="pbk-stat-label">Stats</div></div>
  <div><div class="pbk-stat-value">03</div><div class="pbk-stat-label">FAQ</div></div>
</div></div></section>
<blockquote class="pbk-compound pbk-compound--testimonial pbk-wrap" data-pbk-compound="testimonial">
  <p class="pbk-compound__quote">“Testimonial compound: quote + avatar + author footer.”</p>
  <footer><img src="https://i.pravatar.cc/96?u=pbk-sam" alt="Sam" width="48" height="48"><div><strong>Sam Ortega</strong><div class="pbk-muted">Engineer</div></div></footer>
</blockquote>
<section class="pbk-band pbk-wrap">
  <article class="pbk-compound pbk-compound--pricing" data-pbk-compound="pricing" style="max-width:360px;margin:0 auto">
    <h3>Pricing card</h3><p class="pbk-compound__price">€49</p><p class="pbk-muted">single card sample</p>
    <ul><li>Feature one</li><li>Feature two</li><li>Feature three</li></ul>
    <a class="pbk-btn pbk-btn--dark" href="/pricing">Full pricing page</a>
  </article>
</section>
<section class="pbk-band pbk-wrap">
  <details class="pbk-compound pbk-compound--faq" data-pbk-compound="faq" open><summary>FAQ item compound</summary><p>Uses native <code>details</code>/<code>summary</code>.</p></details>
  <details class="pbk-compound pbk-compound--faq" data-pbk-compound="faq"><summary>Another question</summary><p>Stack as many as you need.</p></details>
</section>
<section class="pbk-compound pbk-compound--cta" data-pbk-compound="cta"><div class="pbk-cta-row">
  <div><h2 style="margin:0 0 .35rem">CTA banner</h2><p style="margin:0">Gradient band with primary action.</p></div>
  <a class="pbk-btn pbk-btn--light" href="/admin/page-builder/pages/compounds/canvas">Open canvas</a>
</div></section>
HTML);
    }

    private static function compoundsEs(): string
    {
        return self::wrap(<<<'HTML'
<section class="pbk-compound pbk-compound--hero" data-pbk-compound="hero">
  <div class="pbk-compound__inner">
    <p class="pbk-compound__eyebrow">Galería compound</p>
    <h1 class="pbk-compound__title">Todos los compounds built-in en una página</h1>
    <p class="pbk-compound__lead">Desplázate e inspéctalos. En el canvas aparecen en la categoría Compound.</p>
    <div class="pbk-compound__actions">
      <a class="pbk-btn pbk-btn--primary" href="/admin/page-builder/pages/compounds/canvas">Editar galería</a>
    </div>
  </div>
</section>
<section class="pbk-band pbk-wrap">
  <h2 class="pbk-section-title">Feature card</h2>
  <div class="pbk-compound__grid">
    <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><div class="pbk-compound__icon">①</div><h3>Card A</h3><p>Icono, título y cuerpo anidados.</p></article>
    <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><div class="pbk-compound__icon">②</div><h3>Card B</h3><p>Estiliza cada nodo por separado.</p></article>
    <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><div class="pbk-compound__icon">③</div><h3>Card C</h3><p>Se usa dentro de Feature grid.</p></article>
  </div>
</section>
<section class="pbk-compound pbk-compound--media-split" data-pbk-compound="media-split"><div class="pbk-wrap"><div class="pbk-split">
  <img src="https://picsum.photos/seed/pbk-compounds-es/720/480" alt="Media" width="720" height="480">
  <div><h2>Media + texto</h2><p class="pbk-muted">Compound de dos columnas con imagen y CTA.</p><a class="pbk-btn pbk-btn--dark" href="#">Acción</a></div>
</div></div></section>
<section class="pbk-compound pbk-compound--stats" data-pbk-compound="stats"><div class="pbk-wrap"><div class="pbk-stats">
  <div><div class="pbk-stat-value">01</div><div class="pbk-stat-label">Hero</div></div>
  <div><div class="pbk-stat-value">02</div><div class="pbk-stat-label">Stats</div></div>
  <div><div class="pbk-stat-value">03</div><div class="pbk-stat-label">FAQ</div></div>
</div></div></section>
<blockquote class="pbk-compound pbk-compound--testimonial pbk-wrap" data-pbk-compound="testimonial">
  <p class="pbk-compound__quote">“Compound testimonial: cita + avatar + autor.”</p>
  <footer><img src="https://i.pravatar.cc/96?u=pbk-sam" alt="Sam" width="48" height="48"><div><strong>Sam Ortega</strong><div class="pbk-muted">Engineer</div></div></footer>
</blockquote>
<section class="pbk-band pbk-wrap">
  <article class="pbk-compound pbk-compound--pricing" data-pbk-compound="pricing" style="max-width:360px;margin:0 auto">
    <h3>Pricing card</h3><p class="pbk-compound__price">€49</p><p class="pbk-muted">ejemplo suelto</p>
    <ul><li>Feature one</li><li>Feature two</li><li>Feature three</li></ul>
    <a class="pbk-btn pbk-btn--dark" href="/pricing">Página de precios</a>
  </article>
</section>
<section class="pbk-band pbk-wrap">
  <details class="pbk-compound pbk-compound--faq" data-pbk-compound="faq" open><summary>FAQ item</summary><p>Usa <code>details</code>/<code>summary</code> nativo.</p></details>
  <details class="pbk-compound pbk-compound--faq" data-pbk-compound="faq"><summary>Otra pregunta</summary><p>Apila las que necesites.</p></details>
</section>
<section class="pbk-compound pbk-compound--cta" data-pbk-compound="cta"><div class="pbk-cta-row">
  <div><h2 style="margin:0 0 .35rem">CTA banner</h2><p style="margin:0">Banda con acción principal.</p></div>
  <a class="pbk-btn pbk-btn--light" href="/admin/page-builder/pages/compounds/canvas">Abrir canvas</a>
</div></section>
HTML);
    }

    private static function pricingEn(): string
    {
        return self::wrap(<<<'HTML'
<section class="pbk-compound pbk-compound--hero" data-pbk-compound="hero">
  <div class="pbk-compound__inner">
    <p class="pbk-compound__eyebrow">Pricing</p>
    <h1 class="pbk-compound__title">Simple plans, compound cards</h1>
    <p class="pbk-compound__lead">Three pricing compounds side by side — duplicate or restyle them in the canvas.</p>
  </div>
</section>
<section class="pbk-band"><div class="pbk-wrap"><div class="pbk-pricing-grid">
  <article class="pbk-compound pbk-compound--pricing" data-pbk-compound="pricing"><h3>Starter</h3><p class="pbk-compound__price">€0</p><p class="pbk-muted">forever free demo</p><ul><li>5 seed pages</li><li>Public preview</li><li>Community docs</li></ul><a class="pbk-btn pbk-btn--dark" href="/">Start</a></article>
  <article class="pbk-compound pbk-compound--pricing" data-pbk-compound="pricing" style="border-color:#0ea5e9;box-shadow:0 0 0 2px #0ea5e9"><h3>Pro</h3><p class="pbk-compound__price">€29</p><p class="pbk-muted">most popular</p><ul><li>Unlimited pages</li><li>Locales</li><li>Custom compounds</li></ul><a class="pbk-btn pbk-btn--dark" href="/contact">Choose Pro</a></article>
  <article class="pbk-compound pbk-compound--pricing" data-pbk-compound="pricing"><h3>Scale</h3><p class="pbk-compound__price">€99</p><p class="pbk-muted">teams</p><ul><li>Roles &amp; audit</li><li>Allowlist HTML</li><li>Priority support</li></ul><a class="pbk-btn pbk-btn--dark" href="/contact">Talk to us</a></article>
</div></div></section>
<section class="pbk-compound pbk-compound--cta" data-pbk-compound="cta"><div class="pbk-cta-row">
  <div><h2 style="margin:0 0 .35rem">Need a custom compound pack?</h2><p style="margin:0">Register DomComponents types like the built-in examples.</p></div>
  <a class="pbk-btn pbk-btn--light" href="/admin/page-builder/pages/pricing/canvas">Edit pricing</a>
</div></section>
HTML);
    }

    private static function pricingEs(): string
    {
        return self::wrap(<<<'HTML'
<section class="pbk-compound pbk-compound--hero" data-pbk-compound="hero">
  <div class="pbk-compound__inner">
    <p class="pbk-compound__eyebrow">Precios</p>
    <h1 class="pbk-compound__title">Planes simples, cards compound</h1>
    <p class="pbk-compound__lead">Tres pricing compounds en fila — duplícalos o restilízalos en el canvas.</p>
  </div>
</section>
<section class="pbk-band"><div class="pbk-wrap"><div class="pbk-pricing-grid">
  <article class="pbk-compound pbk-compound--pricing" data-pbk-compound="pricing"><h3>Starter</h3><p class="pbk-compound__price">€0</p><p class="pbk-muted">demo gratuita</p><ul><li>5 páginas seed</li><li>Preview público</li><li>Docs comunidad</li></ul><a class="pbk-btn pbk-btn--dark" href="/">Empezar</a></article>
  <article class="pbk-compound pbk-compound--pricing" data-pbk-compound="pricing" style="border-color:#0ea5e9;box-shadow:0 0 0 2px #0ea5e9"><h3>Pro</h3><p class="pbk-compound__price">€29</p><p class="pbk-muted">más popular</p><ul><li>Páginas ilimitadas</li><li>Locales</li><li>Compounds custom</li></ul><a class="pbk-btn pbk-btn--dark" href="/contact">Elegir Pro</a></article>
  <article class="pbk-compound pbk-compound--pricing" data-pbk-compound="pricing"><h3>Scale</h3><p class="pbk-compound__price">€99</p><p class="pbk-muted">equipos</p><ul><li>Roles y auditoría</li><li>Allowlist HTML</li><li>Soporte prioritario</li></ul><a class="pbk-btn pbk-btn--dark" href="/contact">Hablar</a></article>
</div></div></section>
<section class="pbk-compound pbk-compound--cta" data-pbk-compound="cta"><div class="pbk-cta-row">
  <div><h2 style="margin:0 0 .35rem">¿Necesitas un pack compound?</h2><p style="margin:0">Registra tipos DomComponents como los ejemplos built-in.</p></div>
  <a class="pbk-btn pbk-btn--light" href="/admin/page-builder/pages/pricing/canvas">Editar precios</a>
</div></section>
HTML);
    }

    private static function aboutEn(): string
    {
        return self::wrap(<<<'HTML'
<section class="pbk-compound pbk-compound--media-split" data-pbk-compound="media-split"><div class="pbk-wrap"><div class="pbk-split">
  <img src="https://picsum.photos/seed/pbk-about/800/560" alt="Team" width="800" height="560">
  <div>
    <p class="pbk-compound__eyebrow" style="text-transform:uppercase;letter-spacing:.08em;font-size:.75rem;color:#64748b">About</p>
    <h1>Built for Symfony hosts</h1>
    <p class="pbk-muted">Page Builder Kit Bundle stores GrapesJS projects in Doctrine, exposes a CSRF JSON API, and renders sanitized HTML on <code>/p/{pageKey}</code>.</p>
    <a class="pbk-btn pbk-btn--dark" href="/compounds">See compounds</a>
  </div>
</div></div></section>
<section class="pbk-compound pbk-compound--feature-grid" data-pbk-compound="feature-grid"><div class="pbk-wrap">
  <h2 class="pbk-section-title">Stack highlights</h2>
  <div class="pbk-compound__grid">
    <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><div class="pbk-compound__icon">SF</div><h3>Symfony 8</h3><p>FrankenPHP demo on port 8137.</p></article>
    <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><div class="pbk-compound__icon">GJ</div><h3>GrapesJS</h3><p>Canvas CDN with compound examples.</p></article>
    <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><div class="pbk-compound__icon">i18n</div><h3>Locales</h3><p>EN/ES seeds out of the box.</p></article>
  </div>
</div></section>
<blockquote class="pbk-compound pbk-compound--testimonial pbk-wrap" data-pbk-compound="testimonial">
  <p class="pbk-compound__quote">“The about page is itself a seed — open the canvas and rewrite the story.”</p>
  <footer><img src="https://i.pravatar.cc/96?u=pbk-jordan" alt="Jordan" width="48" height="48"><div><strong>Jordan Lee</strong><div class="pbk-muted">Maintainer</div></div></footer>
</blockquote>
<section class="pbk-band pbk-wrap">
  <details class="pbk-compound pbk-compound--faq" data-pbk-compound="faq" open><summary>Is classic schema v1 gone?</summary><p>No — it still renders. New pages default to GrapesJS v2.</p></details>
  <details class="pbk-compound pbk-compound--faq" data-pbk-compound="faq"><summary>Where are seeds defined?</summary><p><code>App\Demo\DemoContentCatalog</code> in the demo app.</p></details>
</section>
HTML);
    }

    private static function aboutEs(): string
    {
        return self::wrap(<<<'HTML'
<section class="pbk-compound pbk-compound--media-split" data-pbk-compound="media-split"><div class="pbk-wrap"><div class="pbk-split">
  <img src="https://picsum.photos/seed/pbk-about-es/800/560" alt="Equipo" width="800" height="560">
  <div>
    <p style="text-transform:uppercase;letter-spacing:.08em;font-size:.75rem;color:#64748b">Acerca de</p>
    <h1>Hecho para hosts Symfony</h1>
    <p class="pbk-muted">Page Builder Kit Bundle guarda proyectos GrapesJS en Doctrine, expone una API JSON con CSRF y renderiza HTML sanitizado en <code>/p/{pageKey}</code>.</p>
    <a class="pbk-btn pbk-btn--dark" href="/compounds">Ver compounds</a>
  </div>
</div></div></section>
<section class="pbk-compound pbk-compound--feature-grid" data-pbk-compound="feature-grid"><div class="pbk-wrap">
  <h2 class="pbk-section-title">Highlights del stack</h2>
  <div class="pbk-compound__grid">
    <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><div class="pbk-compound__icon">SF</div><h3>Symfony 8</h3><p>Demo FrankenPHP en el puerto 8137.</p></article>
    <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><div class="pbk-compound__icon">GJ</div><h3>GrapesJS</h3><p>Canvas CDN con ejemplos compound.</p></article>
    <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><div class="pbk-compound__icon">i18n</div><h3>Locales</h3><p>Seeds EN/ES de serie.</p></article>
  </div>
</div></section>
<blockquote class="pbk-compound pbk-compound--testimonial pbk-wrap" data-pbk-compound="testimonial">
  <p class="pbk-compound__quote">“La página about también es una seed — abre el canvas y reescribe la historia.”</p>
  <footer><img src="https://i.pravatar.cc/96?u=pbk-jordan" alt="Jordan" width="48" height="48"><div><strong>Jordan Lee</strong><div class="pbk-muted">Maintainer</div></div></footer>
</blockquote>
<section class="pbk-band pbk-wrap">
  <details class="pbk-compound pbk-compound--faq" data-pbk-compound="faq" open><summary>¿Desaparece el schema v1?</summary><p>No — sigue renderizando. Las páginas nuevas usan GrapesJS v2.</p></details>
  <details class="pbk-compound pbk-compound--faq" data-pbk-compound="faq"><summary>¿Dónde están las seeds?</summary><p><code>App\Demo\DemoContentCatalog</code> en la app demo.</p></details>
</section>
HTML);
    }

    private static function contactEn(): string
    {
        return self::wrap(<<<'HTML'
<section class="pbk-compound pbk-compound--hero" data-pbk-compound="hero">
  <div class="pbk-compound__inner">
    <p class="pbk-compound__eyebrow">Contact</p>
    <h1 class="pbk-compound__title">Talk to the demo team</h1>
    <p class="pbk-compound__lead">This page is a thinner GrapesJS seed focused on CTA + FAQ. Replace the copy in the canvas.</p>
    <div class="pbk-compound__actions">
      <a class="pbk-btn pbk-btn--primary" href="mailto:hello@example.com">hello@example.com</a>
      <a class="pbk-btn pbk-btn--ghost" href="/admin/page-builder/pages/contact/canvas">Edit contact</a>
    </div>
  </div>
</section>
<section class="pbk-compound pbk-compound--feature-grid" data-pbk-compound="feature-grid"><div class="pbk-wrap">
  <div class="pbk-compound__grid">
    <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><div class="pbk-compound__icon">✉</div><h3>Email</h3><p>hello@example.com — placeholder for your host.</p></article>
    <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><div class="pbk-compound__icon">⌘</div><h3>Admin</h3><p>Login <code>admin</code>/<code>admin</code> then open canvases.</p></article>
    <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><div class="pbk-compound__icon">/</div><h3>Public</h3><p>Also available at <code>/p/contact</code> when published.</p></article>
  </div>
</div></section>
<section class="pbk-band pbk-wrap">
  <details class="pbk-compound pbk-compound--faq" data-pbk-compound="faq" open><summary>Is this a real form?</summary><p>No — the demo focuses on page composition. Wire FormKit when you need forms.</p></details>
  <details class="pbk-compound pbk-compound--faq" data-pbk-compound="faq"><summary>How do I change locale?</summary><p>Use <code>/contact?_locale=es</code> (or your host locale switcher).</p></details>
</section>
<section class="pbk-compound pbk-compound--cta" data-pbk-compound="cta"><div class="pbk-cta-row">
  <div><h2 style="margin:0 0 .35rem">Prefer browsing compounds?</h2><p style="margin:0">The gallery page stacks every example block.</p></div>
  <a class="pbk-btn pbk-btn--light" href="/compounds">Open gallery</a>
</div></section>
HTML);
    }

    private static function contactEs(): string
    {
        return self::wrap(<<<'HTML'
<section class="pbk-compound pbk-compound--hero" data-pbk-compound="hero">
  <div class="pbk-compound__inner">
    <p class="pbk-compound__eyebrow">Contacto</p>
    <h1 class="pbk-compound__title">Habla con el equipo demo</h1>
    <p class="pbk-compound__lead">Seed GrapesJS más ligera centrada en CTA + FAQ. Cambia el copy en el canvas.</p>
    <div class="pbk-compound__actions">
      <a class="pbk-btn pbk-btn--primary" href="mailto:hello@example.com">hello@example.com</a>
      <a class="pbk-btn pbk-btn--ghost" href="/admin/page-builder/pages/contact/canvas">Editar contacto</a>
    </div>
  </div>
</section>
<section class="pbk-compound pbk-compound--feature-grid" data-pbk-compound="feature-grid"><div class="pbk-wrap">
  <div class="pbk-compound__grid">
    <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><div class="pbk-compound__icon">✉</div><h3>Email</h3><p>hello@example.com — placeholder de tu host.</p></article>
    <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><div class="pbk-compound__icon">⌘</div><h3>Admin</h3><p>Login <code>admin</code>/<code>admin</code> y abre canvases.</p></article>
    <article class="pbk-compound pbk-compound--feature-card" data-pbk-compound="feature-card"><div class="pbk-compound__icon">/</div><h3>Público</h3><p>También en <code>/p/contact</code> si está publicada.</p></article>
  </div>
</div></section>
<section class="pbk-band pbk-wrap">
  <details class="pbk-compound pbk-compound--faq" data-pbk-compound="faq" open><summary>¿Hay un formulario real?</summary><p>No — la demo se centra en composición. Conecta FormKit cuando lo necesites.</p></details>
  <details class="pbk-compound pbk-compound--faq" data-pbk-compound="faq"><summary>¿Cómo cambio el idioma?</summary><p>Usa <code>/contact?_locale=es</code> (o el switcher del host).</p></details>
</section>
<section class="pbk-compound pbk-compound--cta" data-pbk-compound="cta"><div class="pbk-cta-row">
  <div><h2 style="margin:0 0 .35rem">¿Prefieres ver compounds?</h2><p style="margin:0">La galería apila todos los bloques de ejemplo.</p></div>
  <a class="pbk-btn pbk-btn--light" href="/compounds">Abrir galería</a>
</div></section>
HTML);
    }
}
