import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { boot, loadGrapesStack, reportBootError, type ModuleImporter } from '../src/page-builder-canvas';

/* eslint-disable @typescript-eslint/no-explicit-any */
type Handler = (...args: any[]) => void;

function flush(): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, 0));
}

function typeDef(traits: unknown): any {
  return { model: { prototype: { defaults: { traits } } } };
}

function fakeComponent(type: string, children: any[] | null = null): any {
  const attrs: Record<string, unknown> = {};
  const props: Record<string, unknown> = { type };
  const handlers: Record<string, Handler> = {};
  const component: any = {
    attrs,
    props,
    handlers,
    get: (key: string) => props[key],
    set: vi.fn((key: string, value: unknown) => {
      if (key === 'src' && type === 'image-throws') throw new Error('readonly');
      props[key] = value;
    }),
    addAttributes: vi.fn((a: Record<string, unknown>) => Object.assign(attrs, a)),
    on: vi.fn((event: string, fn: Handler) => { handlers[event] = fn; }),
    off: vi.fn(),
    is: (t: string) => t === props.type,
  };
  if (children !== null) {
    const list = children;
    component.components = vi.fn((value?: unknown) => {
      if (value !== undefined) {
        props.children = value;
        return undefined;
      }
      return Object.assign(list, { each: (fn: Handler) => list.forEach(fn) });
    });
  }
  return component;
}

function fakeGrapes() {
  const handlers: Record<string, Handler> = {};
  const editor: any = {
    BlockManager: { add: vi.fn() },
    DomComponents: {
      addType: vi.fn(),
      getType: vi.fn((name: string) => {
        if (name === 'video') return undefined;
        if (name === 'map') return { model: null };
        if (name === 'default') throw new Error('missing');
        if (name === 'image') return typeDef([{ name: 'src' }]);
        if (name === 'link') return { model: { prototype: {} } };
        return typeDef(['title', { name: 'pbk-dynamic-tag' }]);
      }),
    },
    AssetManager: { add: vi.fn() },
    on: vi.fn((event: string, fn: Handler) => { handlers[event] = fn; }),
    stopCommand: vi.fn(),
    loadProjectData: vi.fn(),
    setComponents: vi.fn(),
    setStyle: vi.fn(),
    getProjectData: vi.fn(() => ({ pages: [1] })),
    getHtml: vi.fn(() => '<p>edited</p>'),
    getCss: vi.fn(() => 'p{color:red}'),
  };
  const grapesjs = { init: vi.fn((_options: any) => editor) };
  return { editor, grapesjs, handlers };
}

function importerFor(grapesjs: unknown, failing: string[] = []): ModuleImporter & ReturnType<typeof vi.fn> {
  return vi.fn(async (url: string) => {
    if (url.includes('/grapesjs@')) return grapesjs;
    if (failing.some((pkg) => url.includes(pkg))) throw new Error('cdn down ' + url);
    return function plugin() { /* plugin */ };
  }) as any;
}

function mount(config: Record<string, unknown>, structure: unknown = null, extra = ''): HTMLElement {
  document.body.innerHTML = `
    <span data-pbk-status-badge data-pbk-class-published="badge ok" data-pbk-class-draft="badge muted">Draft</span>
    <div id="page-builder-canvas"
         data-pbk-save-url="/save" data-pbk-publish-url="/publish" data-pbk-unpublish-url="/unpublish"
         data-pbk-label-draft="Draft" data-pbk-label-published="Published" data-pbk-label-loading="Loading…"
         data-pbk-class-tab="tab" data-pbk-class-tab-active="tab tab--active"
         data-pbk-csrf="tok" data-pbk-default-locale="en" ${extra}>
      <button type="button" data-pbk-action="save">Save</button>
      <button type="button" data-pbk-action="publish">Publish</button>
      <button type="button" data-pbk-action="unpublish" hidden>Unpublish</button>
      <a href="#gjs" class="tab tab--active" data-pbk-locale-tab="en">EN</a>
      <a href="#gjs" class="tab" data-pbk-locale-tab="es">ES</a>
      <div id="gjs"></div>
      <p data-pbk-status></p>
    </div>`;
  const root = document.getElementById('page-builder-canvas') as HTMLElement;
  root.setAttribute('data-pbk-grapes-config', JSON.stringify(config));
  if (structure !== null) root.setAttribute('data-pbk-structure', JSON.stringify(structure));
  return root;
}

const FULL_CONFIG = {
  pluginCdnBase: 'https://cdn.test/',
  cdnVersion: '1.2.3',
  height: '500px',
  allowScripts: true,
  allowCustomCode: true,
  showDevices: true,
  noticeOnUnload: true,
  canvasStyles: ['/c.css'],
  assets: [{ src: '/a.png' }],
  assetEmbedAsBase64: true,
  uploadUrl: '/upload',
  assetCsrf: 'atok',
  assetsLibraryUrl: '/library',
  plugins: { blocks_basic: true, custom_code: true, preset_webpage: true, typed: true, unknown: true },
  blockPacks: [
    { name: 'Acme', blocks: [
      { id: 'acme-hero', label: 'Hero', category: 'Acme', content: '<section></section>', media: '<svg></svg>', attributes: { class: 'x' } },
      { id: 'acme-null', content: null },
      { label: 'no id' },
      null,
    ] },
    null,
    { blocks: 'nope' },
  ],
  twig: { enabled: true, canvasHelpers: true, variables: [{ name: 'title', label: 'Title' }, { name: 'we"ird', sample: '{{ x }}' }] },
  contentFields: [
    { key: 'hero_title', label: 'Hero', type: 'string' },
    { key: 'hero_image', type: 'image', bind: 'src' },
    { key: 'cta', bind: 'none' },
    { key: '' },
    null,
  ],
};

describe('loadGrapesStack', () => {
  it('loads GrapesJS and the enabled plugins in order, skipping failures', async () => {
    const { grapesjs } = fakeGrapes();
    const importer = importerFor(grapesjs, ['grapesjs-typed']);
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);

    const stack = await loadGrapesStack({ pluginCdnBase: 'https://cdn.test/', cdnVersion: '1.0.0', plugins: { preset_webpage: true, forms: true, typed: true, custom_code: true }, allowCustomCode: false }, importer);

    expect(importer.mock.calls.map((c) => c[0])).toEqual([
      'https://cdn.test/grapesjs@1.0.0',
      'https://cdn.test/grapesjs-plugin-forms@2.0.6',
      'https://cdn.test/grapesjs-typed@2.0.1',
      'https://cdn.test/grapesjs-preset-webpage@1.0.3',
    ]);
    expect(stack.grapesjs).toBe(grapesjs);
    expect(stack.plugins).toHaveLength(2);
    expect(warn).toHaveBeenCalledOnce();
    const presetOpts = stack.pluginsOpts[stack.plugins[1] as PropertyKey];
    const fakeEditor = { getHtml: () => '<p>a</p>', getCss: () => 'p{}' };
    expect((presetOpts.modalImportContent as (e: unknown) => string)(fakeEditor)).toBe('<p>a</p><style>p{}</style>');
    warn.mockRestore();
  });

  it('defaults to esm.sh and the bundled GrapesJS version', async () => {
    const { grapesjs } = fakeGrapes();
    const importer = importerFor(grapesjs);
    await loadGrapesStack({}, importer);
    expect(importer).toHaveBeenCalledWith('https://esm.sh/grapesjs@0.22.9');
  });
});

describe('boot', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.body.innerHTML = '';
  });

  it('returns without the canvas root', async () => {
    document.body.innerHTML = '';
    const importer = importerFor({});
    await boot(importer);
    expect(importer).not.toHaveBeenCalled();
  });

  it('reports a GrapesJS load failure in the status line', async () => {
    mount({});
    const error = vi.spyOn(console, 'error').mockImplementation(() => undefined);
    await boot(vi.fn(async () => { throw new Error('offline'); }) as any);
    const status = document.querySelector('[data-pbk-status]') as HTMLElement;
    expect(status.textContent).toBe('Failed to load GrapesJS: offline');
    expect(status.getAttribute('data-pbk-state')).toBe('error');
    await boot(vi.fn(async () => { throw 'plain'; }) as any);
    expect(status.textContent).toBe('Failed to load GrapesJS: plain');
    expect(error).toHaveBeenCalled();
  });

  it('initialises the editor with the full configuration and registers every block family', async () => {
    const { editor, grapesjs, handlers } = fakeGrapes();
    (fetch as any).mockResolvedValueOnce({ ok: true, json: () => Promise.resolve({ data: [{ src: '/lib.png' }] }) });
    mount(FULL_CONFIG, { version: 2, engine: 'grapesjs', html: '', css: '', grapes: {}, localeContent: { en: { html: '', css: '', grapes: { pages: [] } } } });

    await boot(importerFor(grapesjs));
    await flush();

    const init = grapesjs.init.mock.calls[0][0];
    expect(init).toMatchObject({ container: '#gjs', height: '500px', noticeOnUnload: true, canvas: { styles: ['/c.css'] } });
    expect(init.assetManager).toMatchObject({ embedAsBase64: true, upload: '/upload', headers: { 'X-CSRF-TOKEN': 'atok' } });
    expect(init.deviceManager.devices).toHaveLength(3);
    expect(editor.AssetManager.add).toHaveBeenCalledWith([{ src: '/lib.png' }]);
    expect(editor.loadProjectData).toHaveBeenCalledWith({ pages: [] });

    const ids = editor.BlockManager.add.mock.calls.map((c: unknown[]) => c[0]);
    for (const id of ['pbk-section', 'pbk-compound-hero', 'pbk-compound-faq', 'acme-hero', 'acme-null', 'pbk-custom-html', 'pbk-script', 'pbk-twig-title', 'pbk-twig-we-ird', 'pbk-twig-if-locale', 'pbk-field-hero_title', 'pbk-field-slot-help', 'pbk-a11y-skip', 'pbk-a11y-figure']) {
      expect(ids).toContain(id);
    }
    const acme = editor.BlockManager.add.mock.calls.find((c: unknown[]) => c[0] === 'acme-hero')[1];
    expect(acme).toMatchObject({ label: 'Hero', category: 'Acme', media: '<svg></svg>', attributes: { class: 'x' } });
    expect(editor.BlockManager.add.mock.calls.find((c: unknown[]) => c[0] === 'acme-null')[1]).toMatchObject({ label: 'acme-null', category: 'Acme', content: '<div></div>' });
    const typeNames = editor.DomComponents.addType.mock.calls.map((c: unknown[]) => c[0]);
    expect(typeNames).toContain('pbk-compound-hero');
    expect(typeNames).toContain('text');
    const imageType = editor.DomComponents.addType.mock.calls.filter((c: unknown[]) => c[0] === 'image');
    expect(imageType[0][1].model.defaults.traits[0]).toMatchObject({ name: 'alt' });
    expect(typeof handlers['component:selected']).toBe('function');
    expect(document.querySelector('[data-pbk-status]')?.textContent).toBe('');
  });

  it('binds dynamic tags to the selected component', async () => {
    const { grapesjs, handlers } = fakeGrapes();
    (fetch as any).mockResolvedValueOnce({ ok: false, json: () => Promise.resolve(null) });
    mount(FULL_CONFIG);
    await boot(importerFor(grapesjs));
    const select = handlers['component:selected'];

    select(null);
    select({ on: 'nope' });

    const image = fakeComponent('image');
    select(image);
    image.props['pbk-dynamic-tag'] = 'hero_image';
    image.handlers['change:pbk-dynamic-tag']();
    expect(image.attrs.src).toBe('[[fields.hero_image]]');

    const throwing = fakeComponent('image-throws');
    select(throwing);
    throwing.props['pbk-dynamic-tag'] = 'hero_image';
    throwing.handlers['change:pbk-dynamic-tag']();
    expect(throwing.attrs.src).toBe('[[fields.hero_image]]');

    const textChild = fakeComponent('text');
    const nodeChild = fakeComponent('textnode');
    const otherChild = fakeComponent('box');
    const link = fakeComponent('link', [textChild, nodeChild, otherChild]);
    select(link);
    link.props['pbk-dynamic-tag'] = 'hero_title';
    link.handlers['change:pbk-dynamic-tag']();
    expect(textChild.props.content).toBe('[[fields.hero_title]]');
    expect(nodeChild.props.content).toBe('[[fields.hero_title]]');
    expect(otherChild.props.content).toBeUndefined();

    const emptyLink = fakeComponent('link', []);
    select(emptyLink);
    emptyLink.props['pbk-dynamic-tag'] = 'hero_title';
    emptyLink.handlers['change:pbk-dynamic-tag']();
    expect(emptyLink.props.children).toBe('[[fields.hero_title]]');

    const text = fakeComponent('text', []);
    select(text);
    text.props['pbk-dynamic-tag'] = 'hero_title';
    text.handlers['change:pbk-dynamic-tag']();
    expect(text.props.content).toBe('[[fields.hero_title]]');
    expect(text.props.children).toBe('[[fields.hero_title]]');

    const plain = fakeComponent('text');
    select(plain);
    plain.props['pbk-dynamic-tag'] = 'hero_title';
    plain.handlers['change:pbk-dynamic-tag']();
    expect(plain.props.content).toBe('[[fields.hero_title]]');

    const broken = fakeComponent('text');
    broken.components = () => { throw new Error('x'); };
    select(broken);
    broken.props['pbk-dynamic-tag'] = 'hero_title';
    broken.handlers['change:pbk-dynamic-tag']();

    const none = fakeComponent('text');
    select(none);
    none.props['pbk-dynamic-tag'] = '';
    none.handlers['change:pbk-dynamic-tag']();
    none.props['pbk-dynamic-tag'] = 'unknown';
    none.handlers['change:pbk-dynamic-tag']();
    expect(none.set).not.toHaveBeenCalled();
  });

  it('switches locales, saves, publishes and unpublishes', async () => {
    const { editor, grapesjs } = fakeGrapes();
    const root = mount({ showDevices: false, twig: { enabled: false } }, { version: 2, engine: 'grapesjs', html: '<p>en</p>', css: '', grapes: {}, localeContent: { es: { html: '<p>es</p>', css: 'p{}', grapes: {} } } });
    await boot(importerFor(grapesjs));
    expect(grapesjs.init.mock.calls[0][0].deviceManager.devices).toEqual([]);
    expect(editor.setComponents).toHaveBeenCalledWith('<p>en</p>');

    const tabs = document.querySelectorAll<HTMLElement>('[data-pbk-locale-tab]');
    tabs[0].click();
    expect(editor.setComponents).toHaveBeenCalledTimes(1);
    const click = new MouseEvent('click', { bubbles: true, cancelable: true });
    tabs[1].dispatchEvent(click);
    expect(click.defaultPrevented).toBe(true);
    expect(editor.setComponents).toHaveBeenLastCalledWith('<p>es</p>');
    expect(editor.setStyle).toHaveBeenLastCalledWith('p{}');
    expect(tabs[1].className).toBe('tab tab--active');
    expect(tabs[1].getAttribute('aria-current')).toBe('page');
    expect(tabs[0].className).toBe('tab');

    const fetchMock = fetch as any;
    const respond = (ok: boolean, data: unknown, status = 200): void => {
      fetchMock.mockResolvedValueOnce({ ok, status, json: () => Promise.resolve(data) });
    };
    const status = document.querySelector('[data-pbk-status]') as HTMLElement;
    const badge = document.querySelector('[data-pbk-status-badge]') as HTMLElement;

    respond(true, { ok: true });
    (document.querySelector('[data-pbk-action="save"]') as HTMLElement).click();
    await flush();
    const saved = JSON.parse(fetchMock.mock.calls[0][1].body);
    expect(saved.structure.localeContent.es.html).toBe('<p>edited</p>');
    expect(saved.structure.html).toBe('<p>edited</p>');
    expect(saved.structure.localeContent.en.grapes).toEqual({ pages: [1] });
    expect(fetchMock.mock.calls[0][1].headers['X-CSRF-TOKEN']).toBe('tok');
    expect(status.textContent).toBe('Saved.');
    expect(status.getAttribute('data-pbk-state')).toBe('success');

    tabs[0].click();
    respond(true, {});
    respond(true, { status: 'published' });
    (document.querySelector('[data-pbk-action="publish"]') as HTMLElement).click();
    await flush();
    await flush();
    expect(JSON.parse(fetchMock.mock.calls[1][1].body).structure.html).toBe('<p>edited</p>');
    expect(status.textContent).toBe('Published.');
    expect(root.getAttribute('data-pbk-page-status')).toBe('published');
    expect(badge.textContent).toBe('Published');
    expect(badge.className).toBe('badge ok');
    expect((document.querySelector('[data-pbk-action="publish"]') as HTMLElement).hidden).toBe(true);
    expect((document.querySelector('[data-pbk-action="unpublish"]') as HTMLElement).hidden).toBe(false);

    respond(true, {});
    (document.querySelector('[data-pbk-action="unpublish"]') as HTMLElement).click();
    await flush();
    expect(badge.textContent).toBe('Draft');
    expect(badge.className).toBe('badge muted');

    respond(true, {});
    respond(true, {});
    (document.querySelector('[data-pbk-action="publish"]') as HTMLElement).click();
    await flush();
    await flush();
    expect(badge.textContent).toBe('Published');

    respond(false, { error: 'Locked' }, 409);
    (document.querySelector('[data-pbk-action="save"]') as HTMLElement).click();
    await flush();
    expect(status.textContent).toBe('Locked');
    expect(status.getAttribute('data-pbk-state')).toBe('error');

    respond(false, null, 500);
    (document.querySelector('[data-pbk-action="unpublish"]') as HTMLElement).click();
    await flush();
    expect(status.textContent).toBe('HTTP 500');

    fetchMock.mockRejectedValueOnce('boom');
    (document.querySelector('[data-pbk-action="save"]') as HTMLElement).click();
    await flush();
    expect(status.textContent).toBe('Save failed');

    respond(true, {});
    fetchMock.mockRejectedValueOnce('boom');
    (document.querySelector('[data-pbk-action="publish"]') as HTMLElement).click();
    await flush();
    await flush();
    expect(status.textContent).toBe('Publish failed');

    respond(true, {});
    fetchMock.mockRejectedValueOnce(new Error('Gone'));
    (document.querySelector('[data-pbk-action="publish"]') as HTMLElement).click();
    await flush();
    await flush();
    expect(status.textContent).toBe('Gone');

    fetchMock.mockRejectedValueOnce('boom');
    (document.querySelector('[data-pbk-action="unpublish"]') as HTMLElement).click();
    await flush();
    expect(status.textContent).toBe('Unpublish failed');
  });

  it('reports missing URLs and loads empty locales', async () => {
    const { editor, grapesjs } = fakeGrapes();
    editor.stopCommand.mockImplementation(() => { throw new Error('no command'); });
    mount({ compoundExamples: false, a11yHelpers: false, allowCustomCode: false, twig: { variables: [] } });
    const root = document.getElementById('page-builder-canvas') as HTMLElement;
    for (const name of ['data-pbk-save-url', 'data-pbk-publish-url', 'data-pbk-unpublish-url', 'data-pbk-csrf', 'data-pbk-label-loading', 'data-pbk-label-draft', 'data-pbk-label-published', 'data-pbk-default-locale']) {
      root.removeAttribute(name);
    }
    root.querySelectorAll('[data-pbk-locale-tab]').forEach((tab) => tab.remove());
    document.querySelector('[data-pbk-status-badge]')?.remove();

    await boot(importerFor(grapesjs));
    expect(editor.setComponents).toHaveBeenCalledWith('');
    const ids = editor.BlockManager.add.mock.calls.map((c: unknown[]) => c[0]);
    expect(ids).not.toContain('pbk-compound-hero');
    expect(ids).not.toContain('pbk-custom-html');
    expect(ids).toContain('pbk-twig-pageKey');

    const status = document.querySelector('[data-pbk-status]') as HTMLElement;
    (document.querySelector('[data-pbk-action="save"]') as HTMLElement).click();
    expect(status.textContent).toBe('Save URL missing');
    (document.querySelector('[data-pbk-action="publish"]') as HTMLElement).click();
    expect(status.textContent).toBe('Publish URL missing');
    (document.querySelector('[data-pbk-action="unpublish"]') as HTMLElement).click();
    expect(status.textContent).toBe('Unpublish URL missing');
    expect(fetch).not.toHaveBeenCalled();
  });

  it('survives a missing status line, tab class hooks and library errors', async () => {
    const { editor, grapesjs } = fakeGrapes();
    editor.AssetManager.add.mockImplementation(() => { throw new Error('not ready'); });
    (fetch as any)
      .mockResolvedValueOnce({ ok: true, json: () => Promise.resolve({ data: [{ src: '/x.png' }] }) });
    mount({ assetsLibraryUrl: '/library', canvasStyles: 'nope', assets: 'nope' });
    const root = document.getElementById('page-builder-canvas') as HTMLElement;
    root.removeAttribute('data-pbk-class-tab');
    document.querySelector('[data-pbk-status]')?.remove();
    await boot(importerFor(grapesjs));
    await flush();
    expect(grapesjs.init.mock.calls[0][0].canvas.styles).toEqual([]);
    const tabs = document.querySelectorAll<HTMLElement>('[data-pbk-locale-tab]');
    tabs[1].click();
    expect(tabs[1].className).toBe('tab');
    expect(tabs[1].getAttribute('aria-current')).toBe('page');

    const second = fakeGrapes();
    (fetch as any).mockRejectedValueOnce(new Error('offline'));
    mount({ assetsLibraryUrl: '/library' });
    await boot(importerFor(second.grapesjs));
    await flush();
    expect(second.editor.AssetManager.add).not.toHaveBeenCalled();

    const third = fakeGrapes();
    (fetch as any).mockResolvedValueOnce({ ok: true, json: () => Promise.resolve({ data: [] }) });
    mount({ assetsLibraryUrl: '/library' });
    await boot(importerFor(third.grapesjs));
    await flush();
    expect(third.editor.AssetManager.add).not.toHaveBeenCalled();
  });
});

describe('reportBootError', () => {
  it('writes the error into the status line', () => {
    const error = vi.spyOn(console, 'error').mockImplementation(() => undefined);
    document.body.innerHTML = '<p data-pbk-status></p>';
    reportBootError(new Error('kaput'));
    expect(document.querySelector('[data-pbk-status]')?.textContent).toBe('GrapesJS boot error: kaput');
    reportBootError('plain');
    expect(document.querySelector('[data-pbk-status]')?.getAttribute('data-pbk-state')).toBe('error');
    document.body.innerHTML = '';
    reportBootError('nothing');
    expect(error).toHaveBeenCalledTimes(3);
    error.mockRestore();
  });
});
