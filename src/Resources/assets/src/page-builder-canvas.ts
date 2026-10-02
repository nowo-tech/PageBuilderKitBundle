// @ts-nocheck — Grapes editor/plugins from esm.sh; config helpers typed in grapes-types.ts.
/**
 * Page Builder Kit — GrapesJS canvas (ESM + official plugins).
 * Loads GrapesJS and community plugins from esm.sh; persists schema v2.
 * Built with Vite from this file → src/Resources/public/js/page-builder-canvas.js
 */
import {
  type GrapesDocumentStructure,
  type GrapesFrontendConfig,
  ensureLocaleContent,
  parseJsonAttr,
} from './grapes-types';


/** Grapes editor instance — typed loosely until official GrapesJS types are vendored. */
type GrapesEditor = any;
type GrapesModule = any;

const PLUGIN_SPECS = {
  blocks_basic: { pkg: 'grapesjs-blocks-basic@1.0.2', opts: { flexGrid: true, category: 'Basic' } },
  forms: { pkg: 'grapesjs-plugin-forms@2.0.6', opts: { category: 'Forms' } },
  navbar: { pkg: 'grapesjs-navbar@1.0.2', opts: {} },
  countdown: { pkg: 'grapesjs-component-countdown@1.0.2', opts: {} },
  export: { pkg: 'grapesjs-plugin-export@1.0.12', opts: {} },
  tabs: { pkg: 'grapesjs-tabs@1.0.6', opts: { tabsBlock: { category: 'Extra' } } },
  custom_code: { pkg: 'grapesjs-custom-code@1.0.2', opts: { blockLabel: 'Custom code', blockCustomCode: { category: 'Extra' } } },
  touch: { pkg: 'grapesjs-touch@0.1.1', opts: {} },
  parser_postcss: { pkg: 'grapesjs-parser-postcss@1.0.3', opts: {} },
  tooltip: { pkg: 'grapesjs-tooltip@0.1.8', opts: {} },
  style_bg: { pkg: 'grapesjs-style-bg@2.0.2', opts: {} },
  typed: { pkg: 'grapesjs-typed@2.0.1', opts: { block: { category: 'Extra' } } },
  tui_image_editor: {
    pkg: 'grapesjs-tui-image-editor@1.0.2',
    opts: {
      config: { includeUI: { initMenu: 'filter' } },
    },
  },
  preset_webpage: {
    pkg: 'grapesjs-preset-webpage@1.0.3',
    opts: {
      modalImportTitle: 'Import HTML',
      modalImportLabel: '<div style="margin-bottom:10px;font-size:13px;">Paste HTML/CSS and click Import</div>',
      modalImportContent: (editor) => editor.getHtml() + '<style>' + editor.getCss() + '</style>',
      textBlocks: 1,
    },
  },
};

async function importDefault(url) {
  const mod = await import(url);
  return mod.default || mod;
}

async function loadGrapesStack(config: GrapesFrontendConfig): Promise<{ grapesjs: GrapesModule; plugins: GrapesModule[]; pluginsOpts: Record<string, unknown> }> {
  const base = (config.pluginCdnBase || 'https://esm.sh').replace(/\/$/, '');
  const version = config.cdnVersion || '0.22.9';
  const grapesjs = await importDefault(`${base}/grapesjs@${version}`);
  const plugins = [];
  const pluginsOpts = {};
  const flags = config.plugins || {};

  // Order matters: feature plugins first, preset webpage last (UI chrome).
  const order = [
    'blocks_basic', 'forms', 'navbar', 'countdown', 'export', 'tabs',
    'custom_code', 'touch', 'parser_postcss', 'tooltip', 'style_bg', 'typed',
    'tui_image_editor', 'preset_webpage',
  ];

  for (const key of order) {
    if (!flags[key]) continue;
    if (key === 'custom_code' && config.allowCustomCode === false) continue;
    const spec = PLUGIN_SPECS[key];
    if (!spec) continue;
    try {
      const plugin = await importDefault(`${base}/${spec.pkg}`);
      plugins.push(plugin);
      pluginsOpts[plugin] = typeof spec.opts === 'function' ? spec.opts : { ...spec.opts };
      if (key === 'preset_webpage') {
        pluginsOpts[plugin].modalImportContent = (editor) =>
          editor.getHtml() + '<style>' + editor.getCss() + '</style>';
      }
    } catch (err) {
      console.warn('[PBK] Failed to load GrapesJS plugin', key, err);
    }
  }

  return { grapesjs, plugins, pluginsOpts };
}

async function boot() {
  const root = document.getElementById('page-builder-canvas');
  if (!root) return;

  const statusEl = root.querySelector('[data-pbk-status]');
  const setBootStatus = (msg, isError) => {
    if (!statusEl) return;
    statusEl.textContent = msg || '';
    statusEl.classList.toggle('text-danger', !!isError);
    statusEl.classList.toggle('text-success', !isError && !!msg);
  };

  setBootStatus('Loading GrapesJS…');

  const saveUrl = root.getAttribute('data-pbk-save-url');
  const publishUrl = root.getAttribute('data-pbk-publish-url');
  const unpublishUrl = root.getAttribute('data-pbk-unpublish-url');
  const labelDraft = root.getAttribute('data-pbk-label-draft') || 'draft';
  const labelPublished = root.getAttribute('data-pbk-label-published') || 'published';
  const csrf = root.getAttribute('data-pbk-csrf');
  const defaultLocale = root.getAttribute('data-pbk-default-locale') || 'en';
  const config = parseJsonAttr<GrapesFrontendConfig>(root, 'data-pbk-grapes-config', {});
  const allowScripts = !!config.allowScripts;
  const allowCustomCode = config.allowCustomCode !== false;
  const compoundExamples = config.compoundExamples !== false;
  const a11yHelpers = config.a11yHelpers !== false;
  const twigCfg = config.twig && typeof config.twig === 'object' ? config.twig : {};

  let structure = parseJsonAttr<GrapesDocumentStructure>(root, 'data-pbk-structure', {
    version: 2, engine: 'grapesjs', html: '', css: '', grapes: {}, localeContent: {},
  });

  const localeTabs = Array.from(root.querySelectorAll('[data-pbk-locale-tab]'));
  let locales = localeTabs.map((btn) => btn.getAttribute('data-pbk-locale-tab')).filter(Boolean);
  if (!locales.length) locales = [defaultLocale];
  structure = ensureLocaleContent(structure, locales, defaultLocale);
  let activeLocale = defaultLocale;

  let grapesjs, plugins, pluginsOpts;
  try {
    ({ grapesjs, plugins, pluginsOpts } = await loadGrapesStack(config));
  } catch (err) {
    console.error(err);
    setBootStatus('Failed to load GrapesJS: ' + (err && err.message ? err.message : err), true);
    return;
  }

  const devices = config.showDevices === false ? [] : [
    { name: 'Desktop', width: '' },
    { name: 'Tablet', width: '768px', widthMedia: '992px' },
    { name: 'Mobile', width: '375px', widthMedia: '480px' },
  ];

    const editor = grapesjs.init({
    container: '#gjs',
    height: config.height || 'calc(100vh - 220px)',
    width: 'auto',
    fromElement: false,
    storageManager: false,
    noticeOnUnload: !!config.noticeOnUnload,
    showDevices: config.showDevices !== false,
    plugins,
    pluginsOpts,
    canvas: {
      styles: Array.isArray(config.canvasStyles) ? config.canvasStyles : [],
    },
    deviceManager: { devices },
    assetManager: {
      embedAsBase64: config.assetEmbedAsBase64 === true,
      assets: Array.isArray(config.assets) ? config.assets : [],
      upload: config.uploadUrl || false,
      uploadName: 'files',
      multiUpload: true,
      autoAdd: 1,
      credentials: 'same-origin',
      headers: config.assetCsrf
        ? { 'X-CSRF-TOKEN': config.assetCsrf }
        : {},
    },
    selectorManager: { componentFirst: true },
  });

  if (config.assetsLibraryUrl) {
    fetch(config.assetsLibraryUrl, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (payload) {
        var list = payload && Array.isArray(payload.data) ? payload.data : [];
        if (!list.length) {
          return;
        }
        try {
          editor.AssetManager.add(list);
        } catch (e) { /* Asset Manager may not be ready */ }
      })
      .catch(function () { /* library optional */ });
  }

  setBootStatus('');

    function setStatus(msg, isError) {
      if (!statusEl) {
        return;
      }
      statusEl.textContent = msg || '';
      statusEl.classList.toggle('text-danger', !!isError);
      statusEl.classList.toggle('text-success', !isError && !!msg);
    }

    function loadLocale(locale) {
      activeLocale = locale;
      var payload = structure.localeContent[locale] || emptyLocalePayload();
      try { editor.stopCommand('core:component-outline'); } catch (e) { /* optional */ }
      if (payload.grapes && Object.keys(payload.grapes).length) {
        editor.loadProjectData(payload.grapes);
      } else if (payload.html) {
        editor.setComponents(payload.html);
        editor.setStyle(payload.css || '');
      } else {
        editor.setComponents('');
        editor.setStyle('');
      }
      localeTabs.forEach(function (btn) {
        btn.classList.toggle('active', btn.getAttribute('data-pbk-locale-tab') === locale);
      });
    }

    function captureActiveLocale() {
      var grapes = editor.getProjectData();
      var html = editor.getHtml();
      var css = editor.getCss();
      structure.localeContent[activeLocale] = {
        html: html,
        css: css,
        grapes: grapes,
      };
      if (activeLocale === defaultLocale) {
        structure.html = html;
        structure.css = css;
        structure.grapes = grapes;
      }
      structure.version = 2;
      structure.engine = 'grapesjs';
      structure.sections = [];
    }

    function registerBlocks() {
      var bm = editor.BlockManager;
      var dc = editor.DomComponents;

      bm.add('pbk-section', {
        label: 'Section',
        category: 'Layout',
        content: '<section class="pbk-section-block" style="padding:2rem 1rem;"><div class="pbk-container" style="max-width:1100px;margin:0 auto;"><h2>Section title</h2><p>Edit this content.</p></div></section>',
      });
      bm.add('pbk-heading', {
        label: 'Heading',
        category: 'Basic',
        content: { type: 'text', tagName: 'h2', content: 'Heading' },
      });
      bm.add('pbk-text', {
        label: 'Text',
        category: 'Basic',
        content: { type: 'text', tagName: 'p', content: 'Insert your text here' },
      });
      bm.add('pbk-image', {
        label: 'Image',
        category: 'Basic',
        content: { type: 'image' },
      });
      bm.add('pbk-button', {
        label: 'Button',
        category: 'Basic',
        content: '<a href="#" class="btn btn-primary" style="display:inline-block;padding:.5rem 1rem;background:#0d6efd;color:#fff;text-decoration:none;border-radius:.25rem;">Button</a>',
      });
      bm.add('pbk-spacer', {
        label: 'Spacer',
        category: 'Basic',
        content: '<div style="height:40px;"></div>',
      });
      bm.add('pbk-container', {
        label: 'Container',
        category: 'Layout',
        content: '<div class="pbk-container" style="max-width:1100px;margin:0 auto;padding:1rem;min-height:60px;border:1px dashed #ccc;"></div>',
      });

      // --- Custom compound component types (editable nested trees) ---
      if (compoundExamples) {
      dc.addType('pbk-compound-hero', {
        model: {
          defaults: {
            name: 'Hero',
            tagName: 'section',
            attributes: { class: 'pbk-compound pbk-compound--hero', 'data-pbk-compound': 'hero' },
            traits: [
              { type: 'text', name: 'id', label: 'ID' },
              { type: 'text', name: 'class', label: 'CSS class' },
            ],
            style: {
              padding: '4rem 1.5rem',
              'background-color': '#0f172a',
              color: '#f8fafc',
              'text-align': 'center',
            },
            components: [
              {
                tagName: 'div',
                attributes: { class: 'pbk-compound__inner' },
                style: { 'max-width': '720px', margin: '0 auto' },
                components: [
                  {
                    type: 'text',
                    tagName: 'p',
                    attributes: { class: 'pbk-compound__eyebrow' },
                    style: { 'text-transform': 'uppercase', 'letter-spacing': '0.08em', 'font-size': '0.75rem', opacity: '0.8', 'margin-bottom': '0.75rem' },
                    content: 'Product launch',
                  },
                  {
                    type: 'text',
                    tagName: 'h1',
                    attributes: { class: 'pbk-compound__title' },
                    style: { 'font-size': '2.5rem', 'line-height': '1.15', 'margin-bottom': '1rem' },
                    content: 'Build pages visually with GrapesJS',
                  },
                  {
                    type: 'text',
                    tagName: 'p',
                    attributes: { class: 'pbk-compound__lead' },
                    style: { 'font-size': '1.125rem', opacity: '0.9', 'margin-bottom': '1.5rem' },
                    content: 'Drop this compound block, then edit every nested element independently.',
                  },
                  {
                    tagName: 'div',
                    attributes: { class: 'pbk-compound__actions' },
                    style: { display: 'flex', gap: '0.75rem', 'justify-content': 'center', 'flex-wrap': 'wrap' },
                    components: [
                      {
                        type: 'link',
                        attributes: { href: '#', class: 'pbk-btn pbk-btn--primary' },
                        style: { display: 'inline-block', padding: '0.75rem 1.25rem', background: '#38bdf8', color: '#0f172a', 'text-decoration': 'none', 'border-radius': '0.375rem', 'font-weight': '600' },
                        content: 'Get started',
                      },
                      {
                        type: 'link',
                        attributes: { href: '#', class: 'pbk-btn pbk-btn--ghost' },
                        style: { display: 'inline-block', padding: '0.75rem 1.25rem', border: '1px solid #94a3b8', color: '#f8fafc', 'text-decoration': 'none', 'border-radius': '0.375rem' },
                        content: 'Learn more',
                      },
                    ],
                  },
                ],
              },
            ],
          },
        },
      });

      dc.addType('pbk-compound-feature-card', {
        model: {
          defaults: {
            name: 'Feature card',
            tagName: 'article',
            attributes: { class: 'pbk-compound pbk-compound--feature-card', 'data-pbk-compound': 'feature-card' },
            traits: [
              { type: 'text', name: 'class', label: 'CSS class' },
            ],
            style: {
              padding: '1.5rem',
              border: '1px solid #e2e8f0',
              'border-radius': '0.75rem',
              background: '#ffffff',
              'box-shadow': '0 1px 2px rgba(15,23,42,0.06)',
            },
            components: [
              {
                type: 'text',
                tagName: 'div',
                attributes: { class: 'pbk-compound__icon', 'aria-hidden': 'true' },
                style: { 'font-size': '1.75rem', 'margin-bottom': '0.75rem' },
                content: '✦',
              },
              {
                type: 'text',
                tagName: 'h3',
                attributes: { class: 'pbk-compound__title' },
                style: { 'font-size': '1.125rem', 'margin-bottom': '0.5rem' },
                content: 'Feature title',
              },
              {
                type: 'text',
                tagName: 'p',
                attributes: { class: 'pbk-compound__body' },
                style: { color: '#475569', margin: '0' },
                content: 'Short description of the feature. Nested text is editable.',
              },
            ],
          },
        },
      });

      dc.addType('pbk-compound-feature-grid', {
        model: {
          defaults: {
            name: 'Feature grid',
            tagName: 'section',
            attributes: { class: 'pbk-compound pbk-compound--feature-grid', 'data-pbk-compound': 'feature-grid' },
            style: { padding: '3rem 1.5rem' },
            components: [
              {
                tagName: 'div',
                attributes: { class: 'pbk-compound__inner' },
                style: { 'max-width': '1100px', margin: '0 auto' },
                components: [
                  {
                    type: 'text',
                    tagName: 'h2',
                    style: { 'text-align': 'center', 'margin-bottom': '2rem' },
                    content: 'Why teams choose us',
                  },
                  {
                    tagName: 'div',
                    attributes: { class: 'pbk-compound__grid' },
                    style: { display: 'grid', 'grid-template-columns': 'repeat(3, minmax(0, 1fr))', gap: '1rem' },
                    components: [
                      { type: 'pbk-compound-feature-card' },
                      { type: 'pbk-compound-feature-card' },
                      { type: 'pbk-compound-feature-card' },
                    ],
                  },
                ],
              },
            ],
          },
        },
      });

      dc.addType('pbk-compound-cta', {
        model: {
          defaults: {
            name: 'CTA banner',
            tagName: 'section',
            attributes: { class: 'pbk-compound pbk-compound--cta', 'data-pbk-compound': 'cta' },
            style: {
              padding: '2.5rem 1.5rem',
              background: 'linear-gradient(135deg, #1d4ed8, #0ea5e9)',
              color: '#fff',
              'border-radius': '0.75rem',
              margin: '1rem 0',
            },
            components: [
              {
                tagName: 'div',
                style: { 'max-width': '800px', margin: '0 auto', display: 'flex', 'align-items': 'center', 'justify-content': 'space-between', gap: '1.5rem', 'flex-wrap': 'wrap' },
                components: [
                  {
                    tagName: 'div',
                    components: [
                      { type: 'text', tagName: 'h2', style: { margin: '0 0 0.35rem' }, content: 'Ready to publish?' },
                      { type: 'text', tagName: 'p', style: { margin: '0', opacity: '0.95' }, content: 'Save the canvas and open the public preview.' },
                    ],
                  },
                  {
                    type: 'link',
                    attributes: { href: '#', class: 'pbk-btn' },
                    style: { display: 'inline-block', padding: '0.75rem 1.25rem', background: '#fff', color: '#1d4ed8', 'text-decoration': 'none', 'border-radius': '0.375rem', 'font-weight': '600' },
                    content: 'Start now',
                  },
                ],
              },
            ],
          },
        },
      });

      dc.addType('pbk-compound-testimonial', {
        model: {
          defaults: {
            name: 'Testimonial',
            tagName: 'blockquote',
            attributes: { class: 'pbk-compound pbk-compound--testimonial', 'data-pbk-compound': 'testimonial' },
            style: {
              padding: '2rem',
              'border-left': '4px solid #0ea5e9',
              background: '#f8fafc',
              'border-radius': '0 0.75rem 0.75rem 0',
              margin: '1rem 0',
            },
            components: [
              {
                type: 'text',
                tagName: 'p',
                attributes: { class: 'pbk-compound__quote' },
                style: { 'font-size': '1.125rem', 'font-style': 'italic', 'margin-bottom': '1rem' },
                content: '“This compound block nests quote + author so editors can restyle each part.”',
              },
              {
                tagName: 'footer',
                attributes: { class: 'pbk-compound__author' },
                style: { display: 'flex', 'align-items': 'center', gap: '0.75rem' },
                components: [
                  {
                    type: 'image',
                    attributes: {
                      src: 'https://via.placeholder.com/48',
                      alt: 'Author',
                      width: '48',
                      height: '48',
                    },
                    style: { 'border-radius': '999px', width: '48px', height: '48px', 'object-fit': 'cover' },
                  },
                  {
                    tagName: 'div',
                    components: [
                      { type: 'text', tagName: 'strong', content: 'Alex Rivera' },
                      { type: 'text', tagName: 'div', style: { color: '#64748b', 'font-size': '0.875rem' }, content: 'Product designer' },
                    ],
                  },
                ],
              },
            ],
          },
        },
      });

      dc.addType('pbk-compound-pricing', {
        model: {
          defaults: {
            name: 'Pricing card',
            tagName: 'article',
            attributes: { class: 'pbk-compound pbk-compound--pricing', 'data-pbk-compound': 'pricing' },
            style: {
              padding: '2rem',
              border: '1px solid #cbd5e1',
              'border-radius': '1rem',
              'text-align': 'center',
              background: '#fff',
              'max-width': '360px',
            },
            components: [
              { type: 'text', tagName: 'h3', content: 'Pro' },
              {
                type: 'text',
                tagName: 'p',
                attributes: { class: 'pbk-compound__price' },
                style: { 'font-size': '2.25rem', 'font-weight': '700', margin: '0.5rem 0 1rem' },
                content: '€29',
              },
              {
                type: 'text',
                tagName: 'p',
                style: { color: '#64748b', 'margin-bottom': '1.25rem' },
                content: 'per editor / month',
              },
              {
                tagName: 'ul',
                attributes: { class: 'pbk-compound__features' },
                style: { 'list-style': 'none', padding: '0', margin: '0 0 1.5rem', 'text-align': 'left' },
                components: [
                  { type: 'text', tagName: 'li', style: { padding: '0.35rem 0', 'border-bottom': '1px solid #e2e8f0' }, content: 'Unlimited pages' },
                  { type: 'text', tagName: 'li', style: { padding: '0.35rem 0', 'border-bottom': '1px solid #e2e8f0' }, content: 'Locale-aware canvas' },
                  { type: 'text', tagName: 'li', style: { padding: '0.35rem 0' }, content: 'Custom compound blocks' },
                ],
              },
              {
                type: 'link',
                attributes: { href: '#' },
                style: { display: 'inline-block', padding: '0.75rem 1.25rem', background: '#0f172a', color: '#fff', 'text-decoration': 'none', 'border-radius': '0.375rem' },
                content: 'Choose plan',
              },
            ],
          },
        },
      });

      dc.addType('pbk-compound-media-split', {
        model: {
          defaults: {
            name: 'Media + text',
            tagName: 'section',
            attributes: { class: 'pbk-compound pbk-compound--media-split', 'data-pbk-compound': 'media-split' },
            style: { padding: '2rem 1rem' },
            components: [
              {
                tagName: 'div',
                style: {
                  display: 'grid',
                  'grid-template-columns': '1fr 1fr',
                  gap: '2rem',
                  'align-items': 'center',
                  'max-width': '1100px',
                  margin: '0 auto',
                },
                components: [
                  {
                    type: 'image',
                    attributes: {
                      src: 'https://via.placeholder.com/640x400',
                      alt: 'Media',
                    },
                    style: { width: '100%', height: 'auto', 'border-radius': '0.75rem' },
                  },
                  {
                    tagName: 'div',
                    components: [
                      { type: 'text', tagName: 'h2', content: 'Compose rich layouts' },
                      { type: 'text', tagName: 'p', style: { color: '#475569' }, content: 'This split compound nests image + copy. Drag either child or restyle from the Style Manager.' },
                      {
                        type: 'link',
                        attributes: { href: '#' },
                        style: { display: 'inline-block', 'margin-top': '0.75rem', padding: '0.65rem 1rem', background: '#0ea5e9', color: '#fff', 'text-decoration': 'none', 'border-radius': '0.375rem' },
                        content: 'Explore',
                      },
                    ],
                  },
                ],
              },
            ],
          },
        },
      });

      dc.addType('pbk-compound-stats', {
        model: {
          defaults: {
            name: 'Stats row',
            tagName: 'section',
            attributes: { class: 'pbk-compound pbk-compound--stats', 'data-pbk-compound': 'stats' },
            style: { padding: '2rem 1rem', background: '#f1f5f9' },
            components: [
              {
                tagName: 'div',
                style: { display: 'grid', 'grid-template-columns': 'repeat(3, 1fr)', gap: '1rem', 'max-width': '900px', margin: '0 auto', 'text-align': 'center' },
                components: [
                  {
                    tagName: 'div',
                    components: [
                      { type: 'text', tagName: 'div', style: { 'font-size': '2rem', 'font-weight': '700' }, content: '120+' },
                      { type: 'text', tagName: 'div', style: { color: '#64748b' }, content: 'Pages published' },
                    ],
                  },
                  {
                    tagName: 'div',
                    components: [
                      { type: 'text', tagName: 'div', style: { 'font-size': '2rem', 'font-weight': '700' }, content: '7' },
                      { type: 'text', tagName: 'div', style: { color: '#64748b' }, content: 'Locales ready' },
                    ],
                  },
                  {
                    tagName: 'div',
                    components: [
                      { type: 'text', tagName: 'div', style: { 'font-size': '2rem', 'font-weight': '700' }, content: '99%' },
                      { type: 'text', tagName: 'div', style: { color: '#64748b' }, content: 'Editor satisfaction' },
                    ],
                  },
                ],
              },
            ],
          },
        },
      });

      dc.addType('pbk-compound-faq', {
        model: {
          defaults: {
            name: 'FAQ item',
            tagName: 'details',
            attributes: { class: 'pbk-compound pbk-compound--faq', 'data-pbk-compound': 'faq', open: true },
            style: {
              padding: '1rem 1.25rem',
              border: '1px solid #e2e8f0',
              'border-radius': '0.5rem',
              margin: '0.5rem 0',
              background: '#fff',
            },
            components: [
              {
                type: 'text',
                tagName: 'summary',
                style: { 'font-weight': '600', cursor: 'pointer' },
                content: 'How do I add my own compound block?',
              },
              {
                type: 'text',
                tagName: 'p',
                style: { margin: '0.75rem 0 0', color: '#475569' },
                content: 'Register a DomComponents type with nested `components`, then expose it in BlockManager (see docs/USAGE.md).',
              },
            ],
          },
        },
      });

      // Block Manager entries for compound examples
      bm.add('pbk-compound-hero', {
        label: 'Hero',
        category: 'Compound',
        media: '<svg viewBox="0 0 24 24" width="24" height="24"><rect x="2" y="4" width="20" height="16" rx="2" fill="none" stroke="currentColor"/><path d="M6 14h8M6 10h12" stroke="currentColor"/></svg>',
        content: { type: 'pbk-compound-hero' },
      });
      bm.add('pbk-compound-feature-grid', {
        label: 'Feature grid',
        category: 'Compound',
        media: '<svg viewBox="0 0 24 24" width="24" height="24"><rect x="2" y="4" width="6" height="16" rx="1" fill="none" stroke="currentColor"/><rect x="9" y="4" width="6" height="16" rx="1" fill="none" stroke="currentColor"/><rect x="16" y="4" width="6" height="16" rx="1" fill="none" stroke="currentColor"/></svg>',
        content: { type: 'pbk-compound-feature-grid' },
      });
      bm.add('pbk-compound-feature-card', {
        label: 'Feature card',
        category: 'Compound',
        media: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8" cy="10" r="2"/><path d="M6 16h4M14 10h4M14 14h4"/></svg>',
        content: { type: 'pbk-compound-feature-card' },
      });
      bm.add('pbk-compound-cta', {
        label: 'CTA banner',
        category: 'Compound',
        media: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor"><rect x="2" y="7" width="20" height="10" rx="2"/><path d="M14 12h4M16 10v4"/></svg>',
        content: { type: 'pbk-compound-cta' },
      });
      bm.add('pbk-compound-testimonial', {
        label: 'Testimonial',
        category: 'Compound',
        media: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor"><path d="M8 11h3v5H6v-3a3 3 0 0 1 2-3zm8 0h3v5h-5v-3a3 3 0 0 1 2-3z"/><path d="M8 11V9a4 4 0 0 1 4-4M16 11V9a4 4 0 0 1 4-4"/></svg>',
        content: { type: 'pbk-compound-testimonial' },
      });
      bm.add('pbk-compound-pricing', {
        label: 'Pricing card',
        category: 'Compound',
        media: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h5M9 16h6"/></svg>',
        content: { type: 'pbk-compound-pricing' },
      });
      bm.add('pbk-compound-media-split', {
        label: 'Media + text',
        category: 'Compound',
        media: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor"><rect x="2" y="5" width="9" height="14" rx="1"/><path d="M14 8h8M14 12h6M14 16h4"/></svg>',
        content: { type: 'pbk-compound-media-split' },
      });
      bm.add('pbk-compound-stats', {
        label: 'Stats row',
        category: 'Compound',
        media: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor"><path d="M4 18V9M10 18V5M16 18v-7M22 18H2"/></svg>',
        content: { type: 'pbk-compound-stats' },
      });
      bm.add('pbk-compound-faq', {
        label: 'FAQ item',
        category: 'Compound',
        media: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 0 1 4.6 1.2c0 1.5-2.1 2-2.1 3.3M12 17h.01"/></svg>',
        content: { type: 'pbk-compound-faq' },
      });
      } // end compoundExamples

      var hostPacks = Array.isArray(config.blockPacks) ? config.blockPacks : [];
      hostPacks.forEach(function (pack) {
        if (!pack || typeof pack !== 'object') {
          return;
        }
        var packBlocks = Array.isArray(pack.blocks) ? pack.blocks : [];
        var packCategory = pack.name || 'Host';
        packBlocks.forEach(function (block) {
          if (!block || !block.id) {
            return;
          }
          var entry = {
            label: block.label || block.id,
            category: block.category || packCategory,
            content: block.content != null ? block.content : '<div></div>',
          };
          if (block.media) {
            entry.media = block.media;
          }
          if (block.attributes && typeof block.attributes === 'object') {
            entry.attributes = block.attributes;
          }
          bm.add(String(block.id), entry);
        });
      });

      if (allowCustomCode) {
        bm.add('pbk-custom-html', {
          label: 'Custom HTML',
          category: 'Extra',
          media: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor"><path d="M8 8l-4 4 4 4M16 8l4 4-4 4M13 6l-2 12"/></svg>',
          content: {
            type: 'default',
            tagName: 'div',
            attributes: { class: 'pbk-custom-html', 'data-pbk-custom': '1' },
            content: '<div class="pbk-custom-placeholder">Custom HTML block — edit in code view</div>',
          },
        });
      }

      if (allowScripts) {
        bm.add('pbk-script', {
          label: 'Script',
          category: 'Extra',
          media: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 9h6M9 13h4"/></svg>',
          content: '<script>/* custom script */<\/script>',
        });
      }

      if (twigCfg.enabled !== false && twigCfg.canvasHelpers !== false) {
        var vars = Array.isArray(twigCfg.variables) ? twigCfg.variables : [];
        if (!vars.length) {
          vars = [
            { name: 'title', sample: '{{ title }}', label: 'Page title' },
            { name: 'locale', sample: '{{ locale }}', label: 'Locale' },
            { name: 'pageKey', sample: '{{ pageKey }}', label: 'Page key' },
            { name: 'slug', sample: '{{ slug }}', label: 'Slug' },
            { name: 'status', sample: '{{ status }}', label: 'Status' },
          ];
        }
        vars.forEach(function (item) {
          var sample = item.sample || ('{{ ' + item.name + ' }}');
          bm.add('pbk-twig-' + String(item.name).replace(/[^a-z0-9_-]/gi, '-'), {
            label: item.label || item.name,
            category: 'Twig',
            content: '<span class="pbk-twig-var" data-pbk-twig="' + String(item.name).replace(/"/g, '') + '" style="display:inline;padding:0 .15rem;background:#fef3c7;border:1px dashed #d97706;border-radius:.25rem;font-family:ui-monospace,monospace;font-size:.9em">' + sample + '</span>',
          });
        });
        bm.add('pbk-twig-if-locale', {
          label: 'If locale',
          category: 'Twig',
          content: '{% if locale == "es" %}<p>Contenido ES</p>{% else %}<p>EN content</p>{% endif %}',
        });
        bm.add('pbk-twig-title-heading', {
          label: 'Title heading',
          category: 'Twig',
          content: '<h1>{{ title }}</h1><p class="pbk-muted">{{ pageKey }} · {{ locale }}</p>',
        });
      }

      var contentFields = Array.isArray(config.contentFields) ? config.contentFields : [];
      contentFields.forEach(function (field) {
        if (!field || !field.key) {
          return;
        }
        var sample = field.sample || ('[[fields.' + field.key + ']]');
        bm.add('pbk-field-' + String(field.key).replace(/[^a-z0-9_-]/gi, '-'), {
          label: (field.label || field.key) + (field.type ? ' (' + field.type + ')' : ''),
          category: 'Content fields',
          content: '<span class="pbk-content-field" data-pbk-field="' + String(field.key).replace(/"/g, '') + '" style="display:inline;padding:0 .15rem;background:#dbeafe;border:1px dashed #2563eb;border-radius:.25rem;font-family:ui-monospace,monospace;font-size:.9em">' + sample + '</span>',
        });
      });
      if (contentFields.length) {
        bm.add('pbk-field-slot-help', {
          label: 'Slot help',
          category: 'Content fields',
          content: '<p class="pbk-muted" style="font-size:.85rem">Use <code>[[fields.key]]</code> (no Twig) or <code>{{ fields.key }}</code> (Twig). Nested: <code>[[fields.group.sub]]</code>, <code>[[fields.list.0.item]]</code>.</p>',
        });
      }

      if (a11yHelpers) {
        var a11yTraits = [
          { type: 'text', name: 'title', label: 'Title attr' },
          { type: 'text', name: 'aria-label', label: 'ARIA label' },
          { type: 'text', name: 'aria-labelledby', label: 'ARIA labelledby' },
          { type: 'text', name: 'aria-describedby', label: 'ARIA describedby' },
          { type: 'text', name: 'role', label: 'Role' },
          { type: 'checkbox', name: 'aria-hidden', label: 'ARIA hidden', valueTrue: 'true', valueFalse: '' },
        ];

        ['default', 'text', 'link', 'image', 'video', 'map'].forEach(function (typeName) {
          try {
            var type = dc.getType(typeName);
            if (!type || !type.model) {
              return;
            }
            var defaults = type.model.prototype.defaults || {};
            var existing = Array.isArray(defaults.traits) ? defaults.traits.slice() : [];
            var names = existing.map(function (t) { return typeof t === 'string' ? t : t.name; });
            a11yTraits.forEach(function (trait) {
              if (names.indexOf(trait.name) === -1) {
                existing.push(trait);
              }
            });
            if (typeName === 'image' && names.indexOf('alt') === -1) {
              existing.unshift({ type: 'text', name: 'alt', label: 'Alt text' });
            }
            dc.addType(typeName, {
              model: {
                defaults: Object.assign({}, defaults, { traits: existing }),
              },
            });
          } catch (e) { /* type may be missing */ }
        });

        bm.add('pbk-a11y-skip', {
          label: 'Skip link',
          category: 'A11y',
          content: '<a class="pbk-skip-link" href="#pbk-main-content">Skip to content</a>',
        });
        bm.add('pbk-a11y-main', {
          label: 'Main landmark',
          category: 'A11y',
          content: '<main id="pbk-main-content" class="pbk-landmark pbk-landmark--main" role="main" aria-label="Main content" style="padding:1.5rem;"><h2>Main content</h2><p>Primary page content goes here.</p></main>',
        });
        bm.add('pbk-a11y-nav', {
          label: 'Nav landmark',
          category: 'A11y',
          content: '<nav class="pbk-landmark pbk-landmark--nav" role="navigation" aria-label="Primary" style="padding:1rem;"><ul style="display:flex;gap:1rem;list-style:none;margin:0;padding:0;"><li><a href="#">Home</a></li><li><a href="#">About</a></li><li><a href="#">Contact</a></li></ul></nav>',
        });
        bm.add('pbk-a11y-aside', {
          label: 'Aside landmark',
          category: 'A11y',
          content: '<aside class="pbk-landmark pbk-landmark--aside" role="complementary" aria-label="Sidebar" style="padding:1rem;background:#f8fafc;"><h2>Related</h2><p> complementary content.</p></aside>',
        });
        bm.add('pbk-a11y-footer', {
          label: 'Footer landmark',
          category: 'A11y',
          content: '<footer class="pbk-landmark pbk-landmark--footer" role="contentinfo" aria-label="Footer" style="padding:1.5rem;border-top:1px solid #e2e8f0;"><p>© Your brand</p></footer>',
        });
        bm.add('pbk-a11y-decorative', {
          label: 'Decorative icon',
          category: 'A11y',
          content: '<span class="pbk-decorative-icon" aria-hidden="true" style="display:inline-flex;width:2.5rem;height:2.5rem;align-items:center;justify-content:center;border-radius:999px;background:#e2e8f0;font-size:1.25rem;">★</span>',
        });
        bm.add('pbk-a11y-figure', {
          label: 'Figure + figcaption',
          category: 'A11y',
          content: '<figure style="margin:0;"><img src="https://picsum.photos/seed/pbk-a11y/640/360" alt="Describe the image for screen readers" width="640" height="360" style="max-width:100%;height:auto;border-radius:.5rem;"><figcaption style="margin-top:.5rem;color:#64748b;font-size:.875rem;">Caption that clarifies the image.</figcaption></figure>',
        });
      }

      // Dynamic tags: bind text/link/image to [[fields.*]] from the Traits panel.
      if (contentFields.length) {
        var tagOptions = [{ id: '', name: '— None —' }];
        contentFields.forEach(function (field) {
          if (!field || !field.key || field.bind === 'none') {
            return;
          }
          tagOptions.push({
            id: field.key,
            name: (field.label || field.key) + (field.type ? ' (' + field.type + ')' : ''),
          });
        });

        function applyDynamicTag(component, fieldKey) {
          if (!fieldKey) {
            return;
          }
          var match = null;
          for (var i = 0; i < contentFields.length; i++) {
            if (contentFields[i] && contentFields[i].key === fieldKey) {
              match = contentFields[i];
              break;
            }
          }
          if (!match) {
            return;
          }
          var slot = '[[fields.' + match.key + ']]';
          var typeName = component.get('type');
          if (typeName === 'image' || match.bind === 'src') {
            component.addAttributes({ src: slot });
            try { component.set('src', slot); } catch (e) { /* ignore */ }
          } else if (typeName === 'link') {
            var children = component.components();
            if (children && children.length) {
              children.each(function (child) {
                if (child.is('text') || child.get('type') === 'textnode' || child.get('type') === 'text') {
                  child.set('content', slot);
                }
              });
            } else {
              component.components(slot);
            }
          } else {
            component.set('content', slot);
            try {
              if (component.components && component.components().length === 0) {
                component.components(slot);
              }
            } catch (e) { /* ignore */ }
          }
        }

        ['text', 'link', 'image'].forEach(function (typeName) {
          try {
            var type = dc.getType(typeName);
            if (!type || !type.model) {
              return;
            }
            var defaults = type.model.prototype.defaults || {};
            var existing = Array.isArray(defaults.traits) ? defaults.traits.slice() : [];
            var names = existing.map(function (t) { return typeof t === 'string' ? t : t.name; });
            if (names.indexOf('pbk-dynamic-tag') === -1) {
              existing.push({
                type: 'select',
                name: 'pbk-dynamic-tag',
                label: 'Dynamic tag',
                options: tagOptions,
                changeProp: 1,
              });
            }
            dc.addType(typeName, {
              model: {
                defaults: Object.assign({}, defaults, { traits: existing }),
              },
            });
          } catch (e) { /* type may be missing */ }
        });

        editor.on('component:selected', function (component) {
          if (!component || typeof component.on !== 'function') {
            return;
          }
          component.off('change:pbk-dynamic-tag');
          component.on('change:pbk-dynamic-tag', function () {
            applyDynamicTag(component, component.get('pbk-dynamic-tag'));
          });
        });
      }
    }

    registerBlocks();
    loadLocale(activeLocale);

    localeTabs.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var next = btn.getAttribute('data-pbk-locale-tab');
        if (!next || next === activeLocale) {
          return;
        }
        captureActiveLocale();
        loadLocale(next);
      });
    });

    function postJson(url, body) {
      return fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf || '',
          Accept: 'application/json',
        },
        body: JSON.stringify(body),
      }).then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok) {
            throw new Error((data && data.error) || ('HTTP ' + res.status));
          }
          return data;
        });
      });
    }

    function saveDocument() {
      captureActiveLocale();
      setStatus('Saving…');
      return postJson(saveUrl, {
        structure: structure,
        widgetPropsByLocale: {},
      }).then(function () {
        setStatus('Saved.');
      }).catch(function (err) {
        setStatus(err.message || 'Save failed', true);
      });
    }

    function setPageStatus(status) {
      root.setAttribute('data-pbk-page-status', status);
      var badge = document.querySelector('[data-pbk-status-badge]');
      if (badge) {
        badge.setAttribute('data-pbk-status-value', status);
        badge.textContent = status === 'published' ? labelPublished : labelDraft;
        badge.classList.toggle('text-bg-success', status === 'published');
        badge.classList.toggle('text-bg-secondary', status !== 'published');
      }
      document.querySelectorAll('[data-pbk-action="publish"]').forEach(function (btn) {
        btn.hidden = status === 'published';
      });
      document.querySelectorAll('[data-pbk-action="unpublish"]').forEach(function (btn) {
        btn.hidden = status !== 'published';
      });
    }

    function publishDocument() {
      return saveDocument().then(function () {
        setStatus('Publishing…');
        return postJson(publishUrl, {}).then(function (data) {
          setPageStatus((data && data.status) || 'published');
          setStatus('Published.');
        });
      }).catch(function (err) {
        setStatus(err.message || 'Publish failed', true);
      });
    }

    function unpublishDocument() {
      if (!unpublishUrl) {
        setStatus('Unpublish URL missing', true);
        return Promise.resolve();
      }
      setStatus('Moving to draft…');
      return postJson(unpublishUrl, {}).then(function (data) {
        setPageStatus((data && data.status) || 'draft');
        setStatus('Saved as draft. Public /p/… returns 404 until published again.');
      }).catch(function (err) {
        setStatus(err.message || 'Unpublish failed', true);
      });
    }

    document.querySelectorAll('[data-pbk-action="save"]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        saveDocument();
      });
    });
    document.querySelectorAll('[data-pbk-action="publish"]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        publishDocument();
      });
    });
    document.querySelectorAll('[data-pbk-action="unpublish"]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        unpublishDocument();
      });
    });
}

boot().catch((err) => {
  console.error(err);
  const el = document.querySelector('[data-pbk-status]');
  if (el) {
    el.textContent = 'GrapesJS boot error: ' + (err && err.message ? err.message : err);
    el.classList.add('text-danger');
  }
});
