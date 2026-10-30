/**
 * Page Builder Kit admin helpers for the content-fields page (CSP-friendly: no inline script, no inline handlers).
 * Bundled by the IIFE entry page-builder-admin.ts into `src/Resources/public/js/page-builder-admin.js`.
 *
 * - `[data-pbk-image-upload-btn]`: picks an image file and POSTs it to `data-upload-url` (header `X-CSRF-TOKEN` =
 *   `data-csrf`); the returned URL fills the field `data-target` and its preview.
 * - `[data-pbk-media-pick]`: also opens the UiKit `<dialog data-pbk-media-dialog>` (data-nowo-ui-dialog-open); loads
 *   `data-library-url` (JSON `{data: [{src, name}]}`) into `[data-pbk-media-grid]`; a click on an image fills the
 *   field and closes the dialog. Only http(s) or root-relative URLs are used; names are text (never HTML).
 *
 * Destructive actions use the UiKit confirm `<dialog>` (REQ-UI-003); this script no longer handles `data-pbk-confirm`.
 */
declare global {
  interface Window {
    __pbkAdminInit?: boolean;
  }
}

export type LibraryAsset = { src?: unknown; name?: unknown };

/** A URL the admin may put in an image field / preview: http(s) or root-relative (never javascript:, data:, //host). */
export function safeAssetUrl(value: unknown): string | null {
  if (typeof value !== 'string') return null;
  const url = value.trim();
  if (url === '') return null;
  // Browsers treat `\` as `/` in http(s) URLs: `/\host` is protocol-relative, so no backslash is accepted.
  if (url.includes('\\')) return null;
  if (/^https?:\/\//i.test(url)) return url;
  if (url.startsWith('/') && !url.startsWith('//')) return url;
  return null;
}

/** First uploaded URL of the upload endpoint response (GrapesJS asset manager shapes). */
export function uploadedUrl(payload: unknown): string | null {
  if (!payload || typeof payload !== 'object') return null;
  const json = payload as { data?: unknown; files?: unknown };
  if (Array.isArray(json.data) && json.data[0] && typeof json.data[0] === 'object') {
    const first = json.data[0] as { src?: unknown; url?: unknown };
    return safeAssetUrl(first.src) ?? safeAssetUrl(first.url);
  }
  if (Array.isArray(json.files)) return safeAssetUrl(json.files[0]);
  if (Array.isArray(payload)) {
    const first = (payload as unknown[])[0];
    return first && typeof first === 'object' ? safeAssetUrl((first as { src?: unknown }).src) : safeAssetUrl(first);
  }
  return null;
}

function target(btn: Element): HTMLInputElement | null {
  const selector = btn.getAttribute('data-target');
  if (!selector) return null;
  const el = document.querySelector(selector);
  return el instanceof HTMLInputElement ? el : null;
}

export function setFieldUrl(field: HTMLInputElement | null, url: string | null): void {
  if (!field || !url) return;
  field.value = url;
  field.dispatchEvent(new Event('change', { bubbles: true }));
  const preview = document.querySelector<HTMLImageElement>(`[data-pbk-image-preview="#${field.id}"]`);
  if (preview) {
    preview.src = url;
    preview.hidden = false;
  }
}

export function upload(file: File, btn: HTMLElement, field: HTMLInputElement): Promise<void> {
  const data = new FormData();
  data.append('files[]', file);
  const errorLabel = btn.getAttribute('data-error-label') || 'Upload failed';

  return fetch(btn.getAttribute('data-upload-url') || '', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': btn.getAttribute('data-csrf') || '', Accept: 'application/json' },
    credentials: 'same-origin',
    body: data,
  })
    .then((res) => res.json().then((json: unknown) => ({ ok: res.ok, json })))
    .then(({ ok, json }) => {
      const url = uploadedUrl(json);
      if (ok && url) {
        setFieldUrl(field, url);
        btn.removeAttribute('data-pbk-error');
        return;
      }
      const error = json && typeof json === 'object' && typeof (json as { error?: unknown }).error === 'string'
        ? (json as { error: string }).error
        : errorLabel;
      btn.setAttribute('data-pbk-error', error);
      btn.title = error;
    })
    .catch(() => {
      btn.setAttribute('data-pbk-error', errorLabel);
      btn.title = errorLabel;
    });
}

function openPicker(btn: HTMLElement): void {
  const field = target(btn);
  if (!field || !btn.getAttribute('data-upload-url')) return;
  const picker = document.createElement('input');
  picker.type = 'file';
  picker.accept = 'image/*';
  picker.hidden = true;
  picker.addEventListener('change', () => {
    const file = picker.files && picker.files[0];
    if (file) void upload(file, btn, field);
  });
  document.body.appendChild(picker);
  picker.click();
  picker.remove();
}

export function renderLibrary(dialog: HTMLDialogElement, assets: LibraryAsset[], field: HTMLInputElement | null): void {
  const grid = dialog.querySelector<HTMLElement>('[data-pbk-media-grid]');
  const empty = dialog.querySelector<HTMLElement>('[data-pbk-media-empty]');
  if (!grid) return;
  grid.replaceChildren();
  let count = 0;
  for (const asset of assets) {
    const src = safeAssetUrl(asset && asset.src);
    if (!src) continue;
    const item = document.createElement('button');
    item.type = 'button';
    item.className = 'pbk-media-grid__item';
    const img = document.createElement('img');
    img.src = src;
    img.alt = '';
    const name = document.createElement('span');
    name.className = 'pbk-media-grid__name';
    name.textContent = typeof asset.name === 'string' && asset.name !== '' ? asset.name : src;
    item.append(img, name);
    item.addEventListener('click', () => {
      setFieldUrl(field, src);
      dialog.close();
    });
    grid.appendChild(item);
    count++;
  }
  if (empty) empty.hidden = count > 0;
}

export function openLibrary(btn: HTMLElement): Promise<void> {
  const dialog = document.querySelector<HTMLDialogElement>('dialog[data-pbk-media-dialog]');
  const url = btn.getAttribute('data-library-url');
  if (!dialog || !url) return Promise.resolve();
  const field = target(btn);
  const error = dialog.querySelector<HTMLElement>('[data-pbk-media-error]');
  if (error) error.hidden = true;
  renderLibrary(dialog, [], field);
  // UiKit's nowo-ui-dialog.js opens the dialog on data-nowo-ui-dialog-open; open it here when that script is absent.
  queueMicrotask(() => {
    if (!dialog.open && typeof dialog.showModal === 'function') dialog.showModal();
  });

  return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    .then((res) => res.json())
    .then((json: unknown) => {
      const data = json && typeof json === 'object' ? (json as { data?: unknown }).data : null;
      renderLibrary(dialog, Array.isArray(data) ? (data as LibraryAsset[]) : [], field);
    })
    .catch(() => {
      if (error) error.hidden = false;
    });
}

export function initAdmin(): void {
  if (window.__pbkAdminInit) return;
  window.__pbkAdminInit = true;

  document.addEventListener('click', (ev) => {
    const el = ev.target instanceof Element ? ev.target : null;
    const uploadBtn = el?.closest<HTMLElement>('[data-pbk-image-upload-btn]');
    if (uploadBtn) {
      openPicker(uploadBtn);
      return;
    }
    const pickBtn = el?.closest<HTMLElement>('[data-pbk-media-pick]');
    if (pickBtn) void openLibrary(pickBtn);
  });
}

