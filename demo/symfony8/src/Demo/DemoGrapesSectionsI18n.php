<?php

declare(strict_types=1);

namespace App\Demo;

/**
 * GrapesJS page where EN and ES have different section sets (localeContent).
 */
final class DemoGrapesSectionsI18n
{
    public static function html(string $locale): string
    {
        $v = DemoUseCases::SEED_VERSION;
        if ($locale === 'es') {
            return <<<HTML
<div class="pbk-demo" data-pbk-demo-seed="{$v}">
<section class="pbk-band" id="sec-es-intro" aria-label="Introducción" style="padding:3rem 1.25rem;background:#0f172a;color:#fff;text-align:center">
  <div class="pbk-wrap" style="max-width:720px;margin:0 auto">
    <p style="text-transform:uppercase;letter-spacing:.08em;font-size:.75rem;opacity:.8">Grapes · secciones ES</p>
    <h1 style="font-size:clamp(1.75rem,4vw,2.5rem);margin:0 0 1rem">Secciones distintas por locale</h1>
    <p style="opacity:.9">El HTML ES no comparte el mismo árbol que EN: <code>localeContent.es</code> define otras secciones.</p>
  </div>
</section>
<section class="pbk-band pbk-wrap" id="sec-es-precios" aria-label="Precios" style="padding:2.5rem 1.25rem">
  <h2 class="pbk-section-title">Sección solo ES: Precios</h2>
  <p class="pbk-muted">Esta sección no existe en la variante inglesa.</p>
  <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;margin-top:1.5rem">
    <div style="border:1px solid #e2e8f0;border-radius:.75rem;padding:1.25rem"><strong>Starter</strong><p style="margin:.5rem 0 0">19 €/mes</p></div>
    <div style="border:1px solid #e2e8f0;border-radius:.75rem;padding:1.25rem"><strong>Pro</strong><p style="margin:.5rem 0 0">49 €/mes</p></div>
  </div>
</section>
<section class="pbk-band" id="sec-es-faq" aria-label="FAQ" style="padding:2.5rem 1.25rem;background:#f8fafc">
  <div class="pbk-wrap">
    <h2 class="pbk-section-title">FAQ (ES)</h2>
    <details style="background:#fff;border:1px solid #e2e8f0;border-radius:.5rem;padding:1rem;margin:.5rem 0"><summary>¿Cómo edito por locale?</summary><p style="margin:.75rem 0 0;color:#475569">Pestañas EN/ES del canvas GrapesJS cargan <code>localeContent</code> distinto.</p></details>
  </div>
</section>
</div>
HTML;
        }

        return <<<HTML
<div class="pbk-demo" data-pbk-demo-seed="{$v}">
<section class="pbk-band" id="sec-en-intro" aria-label="Introduction" style="padding:3rem 1.25rem;background:#0f172a;color:#fff;text-align:center">
  <div class="pbk-wrap" style="max-width:720px;margin:0 auto">
    <p style="text-transform:uppercase;letter-spacing:.08em;font-size:.75rem;opacity:.8">Grapes · EN sections</p>
    <h1 style="font-size:clamp(1.75rem,4vw,2.5rem);margin:0 0 1rem">Different sections per locale</h1>
    <p style="opacity:.9">EN HTML is a different section tree from ES via <code>localeContent.en</code>.</p>
  </div>
</section>
<section class="pbk-band pbk-wrap" id="sec-en-product" aria-label="Product" style="padding:2.5rem 1.25rem">
  <h2 class="pbk-section-title">EN-only section: Product</h2>
  <p class="pbk-muted">This block does not appear in the Spanish variant — swap <code>?_locale=es</code>.</p>
  <img src="https://picsum.photos/seed/pbk-sec-en/960/360" alt="Product banner" width="960" height="360" style="border-radius:.75rem;margin-top:1rem;max-width:100%;height:auto">
</section>
<section class="pbk-band" id="sec-en-contact" aria-label="Contact" style="padding:2.5rem 1.25rem;background:#eff6ff">
  <div class="pbk-wrap" style="text-align:center">
    <h2 class="pbk-section-title">Contact (EN)</h2>
    <p class="pbk-muted">support@example.com · US hours</p>
    <a class="pbk-btn pbk-btn--primary" href="/sections">Also see classic multi-section</a>
  </div>
</section>
</div>
HTML;
    }

    public static function css(): string
    {
        return DemoContentCatalog::sharedCssPublic();
    }
}
