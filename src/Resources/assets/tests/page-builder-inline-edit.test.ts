import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

function flush(): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, 0));
}

const MODAL = `
  <dialog data-pbk-field-modal hidden>
    <form data-pbk-field-form>
      <h2 data-pbk-modal-title></h2>
      <div data-pbk-modal-body></div>
      <p data-pbk-modal-error hidden></p>
      <button type="button" data-pbk-modal-close>Close</button>
      <button type="submit" data-pbk-modal-save>Save</button>
    </form>
  </dialog>`;

function field(type: string, value: string, attrs = ''): string {
  return `<span data-pbk-field data-pbk-key="${type}_key" data-pbk-type="${type}" data-pbk-save-url="/save/${type}" data-pbk-csrf="tok" data-pbk-locale="en" ${attrs}>
    <span data-pbk-value>${value}</span><button type="button" data-pbk-edit>Edit</button></span>`;
}

async function boot(html: string): Promise<HTMLDialogElement> {
  document.body.innerHTML = html;
  const modal = document.querySelector('[data-pbk-field-modal]') as HTMLDialogElement | null;
  if (modal) {
    modal.showModal = vi.fn(function (this: HTMLDialogElement) { this.setAttribute('open', ''); });
    modal.close = vi.fn(function (this: HTMLDialogElement) { this.removeAttribute('open'); this.dispatchEvent(new Event('close')); });
  }
  window.__pbkInlineEditInit = false;
  vi.resetModules();
  await import('../src/page-builder-inline-edit');

  return modal as HTMLDialogElement;
}

function edit(type: string): void {
  (document.querySelector(`[data-pbk-type="${type}"] [data-pbk-edit]`) as HTMLElement).click();
}

function input(): HTMLInputElement & HTMLTextAreaElement & HTMLSelectElement {
  return document.getElementById('pbk-field-input') as HTMLInputElement & HTMLTextAreaElement & HTMLSelectElement;
}

function save(): void {
  (document.querySelector('[data-pbk-modal-save]') as HTMLElement).click();
}

describe('page-builder-inline-edit', () => {
  beforeEach(() => {
    vi.unstubAllGlobals();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('does nothing without a modal and initialises once', async () => {
    await boot('<p></p>');
    expect(window.__pbkInlineEditInit).toBe(true);
    window.__pbkInlineEditInit = true;
    vi.resetModules();
    await import('../src/page-builder-inline-edit');
  });

  it('builds an input per field type from the rendered value', async () => {
    const modal = await boot(MODAL
      + field('string', 'Hello', 'data-pbk-label="Title"')
      + field('bool', 'Sí')
      + field('select', 'b', 'data-pbk-raw-value="b" data-pbk-options=\'["a","b"]\'')
      + field('text', 'Long')
      + field('html', '<b>Rich</b>')
      + field('image', '<img src="/i.png">')
      + field('icon', '<i class="bi bi-star"></i>')
      + field('url', '<a href="https://x.test">x</a>')
      + field('number', '3')
      + field('raw', '')
      + field('richtext', '')
      + field('select', '', 'data-pbk-options="{bad"'));

    edit('string');
    expect(modal.showModal).toHaveBeenCalled();
    expect(document.querySelector('[data-pbk-modal-title]')?.textContent).toBe('Title');
    expect(input().value).toBe('Hello');
    edit('bool');
    expect(input().checked).toBe(true);
    edit('select');
    expect(input().tagName).toBe('SELECT');
    expect(input().value).toBe('b');
    edit('text');
    expect(input().tagName).toBe('TEXTAREA');
    expect(input().rows).toBe(4);
    edit('html');
    expect(input().value).toBe('<b>Rich</b>');
    expect(input().rows).toBe(8);
    edit('image');
    expect(input().type).toBe('url');
    expect(input().value).toBe('/i.png');
    edit('icon');
    expect(input().value).toBe('bi bi-star');
    expect(input().placeholder).toBe('bi bi-star');
    edit('url');
    expect(input().value).toBe('https://x.test');
    edit('number');
    expect(input().type).toBe('number');
    (document.querySelectorAll('[data-pbk-type="select"] [data-pbk-edit]')[1] as HTMLElement).click();
    expect(input().tagName).toBe('SELECT');
  });

  it('reads empty values from nodes without markup', async () => {
    await boot(MODAL
      + field('image', '')
      + field('icon', 'text-icon')
      + field('url', 'plain')
      + field('bool', 'No')
      + '<span data-pbk-field data-pbk-type="string"><button type="button" data-pbk-edit>E</button></span>'
      + '<span data-pbk-field data-pbk-type="html" data-pbk-key="h2"><button type="button" data-pbk-edit>E</button></span>'
      + '<span data-pbk-field data-pbk-type="image" data-pbk-key="i2"><button type="button" data-pbk-edit>E</button></span>'
      + '<span data-pbk-field data-pbk-type="icon" data-pbk-key="c2"><button type="button" data-pbk-edit>E</button></span>'
      + '<span data-pbk-field data-pbk-type="url" data-pbk-key="u2"><button type="button" data-pbk-edit>E</button></span>'
      + '<span data-pbk-field data-pbk-type="bool" data-pbk-key="b2"><button type="button" data-pbk-edit>E</button></span>'
      + '<span data-pbk-field><button type="button" data-pbk-edit>E</button></span>'
      + '<button type="button" data-pbk-edit id="orphan">E</button>');

    edit('image');
    expect(input().value).toBe('');
    edit('icon');
    expect(input().value).toBe('text-icon');
    edit('url');
    expect(input().value).toBe('plain');
    edit('bool');
    expect(input().checked).toBe(false);
    for (const key of ['h2', 'i2', 'c2', 'u2', 'b2']) {
      (document.querySelector(`[data-pbk-key="${key}"] [data-pbk-edit]`) as HTMLElement).click();
    }
    expect(input().checked).toBe(false);
    (document.querySelector('[data-pbk-type="string"]:not([data-pbk-key]) [data-pbk-edit]') as HTMLElement).click();
    expect(input().value).toBe('');
    (document.querySelector('span[data-pbk-field]:not([data-pbk-type]) [data-pbk-edit]') as HTMLElement).click();
    expect(document.querySelector('[data-pbk-modal-title]')?.textContent).toBe('');
    (document.getElementById('orphan') as HTMLElement).click();
    document.body.dispatchEvent(new Event('click', { bubbles: true }));
    document.dispatchEvent(new Event('click'));
  });

  it('saves through fetch and updates the rendered value', async () => {
    const modal = await boot(MODAL
      + field('string', 'Old', 'data-pbk-label="Title" data-pbk-labels=\'{"es":"Título"}\' data-pbk-required="1" data-pbk-options=\'["x"]\'')
      + field('bool', 'No', 'data-pbk-labels="[1]"')
      + field('image', '')
      + field('icon', '')
      + field('url', '')
      + field('html', '')
      + field('number', '1', 'data-pbk-labels="{bad" data-pbk-options="{bad"'));
    const fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
    const respond = (ok: boolean, data: unknown): void => {
      fetchMock.mockResolvedValueOnce({ ok, status: ok ? 200 : 400, json: () => Promise.resolve(data) });
    };

    edit('string');
    input().value = 'New';
    respond(true, { ok: true, value: 'New <b>' });
    save();
    await flush();
    const body = JSON.parse(fetchMock.mock.calls[0][1].body);
    expect(body).toMatchObject({ locale: 'en', value: 'New', type: 'string', label: 'Title', labels: { es: 'Título', en: 'Title' }, required: true, options: ['x'] });
    expect(fetchMock.mock.calls[0][1].headers['X-CSRF-TOKEN']).toBe('tok');
    expect(document.querySelector('[data-pbk-type="string"] [data-pbk-value]')?.textContent).toBe('New <b>');
    expect(modal.close).toHaveBeenCalled();

    edit('bool');
    input().checked = true;
    respond(true, { value: true });
    save();
    await flush();
    expect(document.querySelector('[data-pbk-type="bool"] [data-pbk-value]')?.textContent).toBe('Yes');
    expect(document.querySelector('[data-pbk-type="bool"]')?.getAttribute('data-pbk-raw-value')).toBe('1');
    edit('bool');
    respond(true, { value: false });
    save();
    await flush();
    expect(document.querySelector('[data-pbk-type="bool"] [data-pbk-value]')?.textContent).toBe('No');

    for (const [type, value, check] of [
      ['image', '/a"<.png', 'img'],
      ['image', '', null],
      ['icon', 'bi bi-x', 'i'],
      ['icon', '', null],
      ['url', 'https://a.test/?a=1&b=<2>', 'a'],
      ['url', '', null],
      ['html', '<em>ok</em>', 'em'],
      ['html', null, null],
      ['number', null, null],
    ] as Array<[string, unknown, string | null]>) {
      edit(type);
      respond(true, { value });
      save();
      await flush();
      const node = document.querySelector(`[data-pbk-type="${type}"] [data-pbk-value]`) as HTMLElement;
      if (check) {
        expect(node.querySelector(check)).not.toBeNull();
      } else {
        expect(node.innerHTML).toBe('');
      }
    }

    edit('string');
    respond(false, { error: 'Nope' });
    save();
    await flush();
    expect(document.querySelector('[data-pbk-modal-error]')?.textContent).toBe('Nope');
    respond(false, {});
    save();
    await flush();
    expect(document.querySelector('[data-pbk-modal-error]')?.textContent).toBe('Save failed');
    fetchMock.mockRejectedValueOnce(new Error('offline'));
    save();
    await flush();
    expect(document.querySelector('[data-pbk-modal-error]')?.textContent).toBe('Network error');
  });

  it('handles a missing save URL, value node, close buttons and native submit', async () => {
    const modal = await boot(MODAL
      + '<span data-pbk-field data-pbk-type="string" data-pbk-key="nourl"><span data-pbk-value>v</span><button type="button" data-pbk-edit>E</button></span>'
      + '<span data-pbk-field data-pbk-type="string" data-pbk-key="novalue" data-pbk-save-url="/s"><button type="button" data-pbk-edit>E</button></span>');
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 200, json: () => Promise.resolve({ value: 'x' }) });
    vi.stubGlobal('fetch', fetchMock);

    save();
    expect(fetchMock).not.toHaveBeenCalled();

    (document.querySelector('[data-pbk-key="nourl"] [data-pbk-edit]') as HTMLElement).click();
    save();
    expect(document.querySelector('[data-pbk-modal-error]')?.textContent).toBe('Missing save URL');

    (document.querySelector('[data-pbk-key="novalue"] [data-pbk-edit]') as HTMLElement).click();
    save();
    await flush();
    expect(fetchMock).toHaveBeenCalledOnce();
    expect(JSON.parse(fetchMock.mock.calls[0][1].body).labels).toEqual({});

    (document.querySelector('[data-pbk-key="nourl"] [data-pbk-edit]') as HTMLElement).click();
    document.getElementById('pbk-field-input')?.remove();
    save();
    expect(fetchMock).toHaveBeenCalledOnce();

    (document.querySelector('[data-pbk-modal-close]') as HTMLElement).click();
    expect(modal.hidden).toBe(true);
    const submit = new Event('submit', { cancelable: true });
    (document.querySelector('form') as HTMLFormElement).dispatchEvent(submit);
    expect(submit.defaultPrevented).toBe(true);
  });

  it('works without showModal / close and without the optional modal parts', async () => {
    document.body.innerHTML = '<div data-pbk-field-modal hidden><button data-pbk-modal-close>x</button></div>'
      + field('string', 'v');
    window.__pbkInlineEditInit = false;
    vi.resetModules();
    await import('../src/page-builder-inline-edit');
    edit('string');
    (document.querySelector('[data-pbk-modal-close]') as HTMLElement).click();
    expect((document.querySelector('[data-pbk-field-modal]') as HTMLElement).hidden).toBe(true);

    document.body.innerHTML = '<div data-pbk-field-modal hidden><h2 data-pbk-modal-title></h2><div data-pbk-modal-body></div><button data-pbk-modal-save>s</button></div>'
      + field('string', 'v');
    window.__pbkInlineEditInit = false;
    vi.resetModules();
    await import('../src/page-builder-inline-edit');
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 200, json: () => Promise.resolve({ value: 'w' }) });
    vi.stubGlobal('fetch', fetchMock);
    edit('string');
    expect((document.querySelector('[data-pbk-field-modal]') as HTMLElement).hidden).toBe(false);
    save();
    await flush();
    expect((document.querySelector('[data-pbk-field-modal]') as HTMLElement).hidden).toBe(true);
    expect(document.querySelector('[data-pbk-value]')?.textContent).toBe('w');
  });
});
