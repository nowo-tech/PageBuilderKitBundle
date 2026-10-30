import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { initAdmin, openLibrary, renderLibrary, safeAssetUrl, setFieldUrl, upload, uploadedUrl } from '../src/admin-media';

function flush(): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, 0));
}

function dialogMarkup(): string {
  return `
    <input type="url" id="field-hero" value="">
    <img data-pbk-image-preview="#field-hero" hidden>
    <button type="button" data-pbk-media-pick data-target="#field-hero" data-library-url="/library">Library</button>
    <button type="button" data-pbk-image-upload-btn data-target="#field-hero" data-upload-url="/upload" data-csrf="tok" data-error-label="Failed">Upload</button>
    <dialog data-pbk-media-dialog>
      <p data-pbk-media-empty hidden>Empty</p>
      <p data-pbk-media-error hidden>Error</p>
      <div data-pbk-media-grid></div>
    </dialog>`;
}

function stubDialog(dialog: HTMLDialogElement): void {
  dialog.showModal = vi.fn(function (this: HTMLDialogElement) { this.setAttribute('open', ''); });
  dialog.close = vi.fn(function (this: HTMLDialogElement) { this.removeAttribute('open'); });
}

describe('admin-media helpers', () => {
  beforeEach(() => {
    document.body.innerHTML = dialogMarkup();
    stubDialog(document.querySelector('dialog') as HTMLDialogElement);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('accepts only http(s) and root-relative URLs', () => {
    expect(safeAssetUrl(' https://a.test/x.png ')).toBe('https://a.test/x.png');
    expect(safeAssetUrl('/uploads/x.png')).toBe('/uploads/x.png');
    expect(safeAssetUrl('//evil.test/x.png')).toBeNull();
    expect(safeAssetUrl('javascript:alert(1)')).toBeNull();
    expect(safeAssetUrl('data:image/png;base64,AA')).toBeNull();
    expect(safeAssetUrl('')).toBeNull();
    expect(safeAssetUrl(42)).toBeNull();
  });

  it('reads the upload response shapes', () => {
    expect(uploadedUrl({ data: [{ src: '/a.png' }] })).toBe('/a.png');
    expect(uploadedUrl({ data: [{ url: '/b.png' }] })).toBe('/b.png');
    expect(uploadedUrl({ data: ['x'] })).toBeNull();
    expect(uploadedUrl({ files: ['/c.png'] })).toBe('/c.png');
    expect(uploadedUrl([{ src: '/d.png' }])).toBe('/d.png');
    expect(uploadedUrl(['/e.png'])).toBe('/e.png');
    expect(uploadedUrl([])).toBeNull();
    expect(uploadedUrl({})).toBeNull();
    expect(uploadedUrl(null)).toBeNull();
    expect(uploadedUrl('x')).toBeNull();
  });

  it('fills the field and its preview', () => {
    const field = document.getElementById('field-hero') as HTMLInputElement;
    const changed = vi.fn();
    field.addEventListener('change', changed);
    setFieldUrl(field, '/x.png');
    expect(field.value).toBe('/x.png');
    expect(changed).toHaveBeenCalledOnce();
    const preview = document.querySelector('img') as HTMLImageElement;
    expect(preview.getAttribute('src')).toBe('/x.png');
    expect(preview.hidden).toBe(false);
    setFieldUrl(null, '/y.png');
    setFieldUrl(field, null);
    expect(field.value).toBe('/x.png');
    const orphan = document.createElement('input');
    orphan.id = 'orphan';
    setFieldUrl(orphan, '/z.png');
    expect(orphan.value).toBe('/z.png');
  });

  it('uploads a file and reports errors on the button', async () => {
    const btn = document.querySelector('[data-pbk-image-upload-btn]') as HTMLElement;
    const field = document.getElementById('field-hero') as HTMLInputElement;
    const fetchMock = vi.fn()
      .mockResolvedValueOnce({ ok: true, json: () => Promise.resolve({ data: [{ src: '/up.png' }] }) })
      .mockResolvedValueOnce({ ok: false, json: () => Promise.resolve({ error: 'Too big' }) })
      .mockResolvedValueOnce({ ok: false, json: () => Promise.resolve({}) })
      .mockRejectedValueOnce(new Error('offline'));
    vi.stubGlobal('fetch', fetchMock);
    const file = new File(['x'], 'x.png', { type: 'image/png' });

    await upload(file, btn, field);
    expect(field.value).toBe('/up.png');
    expect(fetchMock.mock.calls[0][0]).toBe('/upload');
    expect(fetchMock.mock.calls[0][1].headers['X-CSRF-TOKEN']).toBe('tok');
    expect(btn.hasAttribute('data-pbk-error')).toBe(false);

    await upload(file, btn, field);
    expect(btn.getAttribute('data-pbk-error')).toBe('Too big');

    await upload(file, btn, field);
    expect(btn.getAttribute('data-pbk-error')).toBe('Failed');

    btn.removeAttribute('data-error-label');
    btn.removeAttribute('data-upload-url');
    btn.removeAttribute('data-csrf');
    await upload(file, btn, field);
    expect(btn.getAttribute('data-pbk-error')).toBe('Upload failed');
    expect(btn.title).toBe('Upload failed');
    expect(fetchMock.mock.calls[3][0]).toBe('');
  });

  it('renders the library items as text and safe URLs only', () => {
    const dialog = document.querySelector('dialog') as HTMLDialogElement;
    const field = document.getElementById('field-hero') as HTMLInputElement;
    renderLibrary(dialog, [
      { src: '/a.png', name: '<b>A</b>' },
      { src: 'javascript:alert(1)', name: 'bad' },
      { src: '/b.png' },
      null as unknown as { src: string },
    ], field);
    const items = dialog.querySelectorAll('.pbk-media-grid__item');
    expect(items).toHaveLength(2);
    expect(items[0].querySelector('span')?.textContent).toBe('<b>A</b>');
    expect(items[0].querySelector('b')).toBeNull();
    expect(items[1].querySelector('span')?.textContent).toBe('/b.png');
    expect((dialog.querySelector('[data-pbk-media-empty]') as HTMLElement).hidden).toBe(true);

    dialog.setAttribute('open', '');
    (items[0] as HTMLElement).click();
    expect(field.value).toBe('/a.png');
    expect(dialog.close).toHaveBeenCalled();

    renderLibrary(dialog, [], field);
    expect((dialog.querySelector('[data-pbk-media-empty]') as HTMLElement).hidden).toBe(false);
    const noGrid = document.createElement('dialog');
    renderLibrary(noGrid, [{ src: '/a.png' }], field);
    const noEmpty = document.createElement('dialog');
    noEmpty.innerHTML = '<div data-pbk-media-grid></div>';
    renderLibrary(noEmpty, [{ src: '/a.png' }], field);
    expect(noEmpty.querySelectorAll('button')).toHaveLength(1);
  });

  it('opens the library dialog and loads the assets', async () => {
    const btn = document.querySelector('[data-pbk-media-pick]') as HTMLElement;
    const dialog = document.querySelector('dialog') as HTMLDialogElement;
    const fetchMock = vi.fn()
      .mockResolvedValueOnce({ json: () => Promise.resolve({ data: [{ src: '/lib.png', name: 'lib' }] }) })
      .mockResolvedValueOnce({ json: () => Promise.resolve({ nope: true }) })
      .mockResolvedValueOnce({ json: () => Promise.resolve(null) })
      .mockRejectedValueOnce(new Error('down'));
    vi.stubGlobal('fetch', fetchMock);

    await openLibrary(btn);
    await flush();
    expect(dialog.showModal).toHaveBeenCalledOnce();
    expect(dialog.querySelectorAll('.pbk-media-grid__item')).toHaveLength(1);

    await openLibrary(btn);
    await flush();
    expect(dialog.showModal).toHaveBeenCalledOnce();
    expect(dialog.querySelectorAll('.pbk-media-grid__item')).toHaveLength(0);

    await openLibrary(btn);
    expect((dialog.querySelector('[data-pbk-media-empty]') as HTMLElement).hidden).toBe(false);

    await openLibrary(btn);
    expect((dialog.querySelector('[data-pbk-media-error]') as HTMLElement).hidden).toBe(false);

    dialog.querySelector('[data-pbk-media-error]')?.remove();
    fetchMock.mockRejectedValueOnce(new Error('down'));
    await openLibrary(btn);

    btn.removeAttribute('data-library-url');
    await openLibrary(btn);
    expect(fetchMock).toHaveBeenCalledTimes(5);

    dialog.remove();
    btn.setAttribute('data-library-url', '/library');
    await openLibrary(btn);
    expect(fetchMock).toHaveBeenCalledTimes(5);
  });

  it('opens nothing when the dialog has no showModal', async () => {
    const btn = document.querySelector('[data-pbk-media-pick]') as HTMLElement;
    const dialog = document.querySelector('dialog') as HTMLDialogElement;
    Object.defineProperty(dialog, 'showModal', { value: undefined });
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: () => Promise.resolve({ data: [] }) }));
    await openLibrary(btn);
    await flush();
    expect(dialog.hasAttribute('open')).toBe(false);
  });

  it('wires the clicks once', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ json: () => Promise.resolve({ data: [] }) });
    vi.stubGlobal('fetch', fetchMock);
    const clickSpy = vi.spyOn(HTMLInputElement.prototype, 'click').mockImplementation(function (this: HTMLInputElement) {
      Object.defineProperty(this, 'files', { value: [new File(['x'], 'x.png')] });
      this.dispatchEvent(new Event('change'));
    });

    window.__pbkAdminInit = false;
    initAdmin();
    initAdmin();

    (document.querySelector('[data-pbk-media-pick]') as HTMLElement).click();
    await flush();
    expect(fetchMock).toHaveBeenCalledWith('/library', expect.anything());

    (document.querySelector('[data-pbk-image-upload-btn]') as HTMLElement).click();
    await flush();
    expect(clickSpy).toHaveBeenCalledOnce();
    expect(fetchMock).toHaveBeenCalledWith('/upload', expect.anything());
    expect(document.querySelectorAll('input[type="file"]')).toHaveLength(0);

    clickSpy.mockImplementation(function (this: HTMLInputElement) {
      Object.defineProperty(this, 'files', { value: [] });
      this.dispatchEvent(new Event('change'));
    });
    (document.querySelector('[data-pbk-image-upload-btn]') as HTMLElement).click();
    expect(fetchMock).toHaveBeenCalledTimes(2);

    const upload = document.querySelector('[data-pbk-image-upload-btn]') as HTMLElement;
    upload.setAttribute('data-target', '#missing');
    upload.click();
    upload.removeAttribute('data-target');
    upload.click();
    upload.setAttribute('data-target', '#field-hero');
    upload.removeAttribute('data-upload-url');
    upload.click();
    expect(clickSpy).toHaveBeenCalledTimes(2);

    document.body.click();
    document.dispatchEvent(new Event('click'));
    expect(fetchMock).toHaveBeenCalledTimes(2);
  });
});
