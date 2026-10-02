/**
 * Inline content-field editor (modal) for public / admin editable fields.
 * Built as IIFE into `src/Resources/public/js/page-builder-inline-edit.js`.
 */

export {};

declare global {
  interface Window {
    __pbkInlineEditInit?: boolean;
  }
}

type FieldInput = HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement;

type SaveResponse = {
  ok?: boolean;
  error?: string;
  value?: unknown;
};

function initInlineEdit(): void {
  if (window.__pbkInlineEditInit) {
    return;
  }
  window.__pbkInlineEditInit = true;

  const modalEl = document.querySelector<HTMLElement>('[data-pbk-field-modal]');
  if (!modalEl) {
    return;
  }
  const modal = modalEl;

  const titleEl = modal.querySelector<HTMLElement>('[data-pbk-modal-title]');
  const bodyEl = modal.querySelector<HTMLElement>('[data-pbk-modal-body]');
  const errorEl = modal.querySelector<HTMLElement>('[data-pbk-modal-error]');
  const saveBtn = modal.querySelector<HTMLButtonElement>('[data-pbk-modal-save]');
  let activeRoot: HTMLElement | null = null;

  function showError(msg: string): void {
    if (!errorEl) return;
    errorEl.hidden = !msg;
    errorEl.textContent = msg || '';
  }

  function buildInput(type: string, value: unknown, options: string[]): FieldInput {
    if (type === 'bool') {
      const input = document.createElement('input');
      input.type = 'checkbox';
      input.checked = value === true || value === '1' || value === 'true';
      input.id = 'pbk-field-input';
      return input;
    }
    if (type === 'select') {
      const input = document.createElement('select');
      input.id = 'pbk-field-input';
      for (const opt of options) {
        const o = document.createElement('option');
        o.value = opt;
        o.textContent = opt;
        if (String(value) === String(opt)) o.selected = true;
        input.appendChild(o);
      }
      return input;
    }
    if (type === 'text' || type === 'richtext' || type === 'html' || type === 'raw') {
      const input = document.createElement('textarea');
      input.rows = type === 'text' ? 4 : 8;
      input.value = value == null ? '' : String(value);
      input.id = 'pbk-field-input';
      input.className = 'pbk-field-modal__control';
      return input;
    }
    const input = document.createElement('input');
    input.id = 'pbk-field-input';
    input.className = 'pbk-field-modal__control';
    if (type === 'number') input.type = 'number';
    else if (type === 'url' || type === 'image') input.type = 'url';
    else input.type = 'text';
    if (type === 'icon') input.placeholder = 'bi bi-star';
    input.value = value == null ? '' : String(value);
    return input;
  }

  function readInput(type: string, input: FieldInput): string | boolean {
    if (type === 'bool' && input instanceof HTMLInputElement) {
      return input.checked;
    }
    return input.value;
  }

  function openEditor(root: HTMLElement): void {
    if (!titleEl || !bodyEl) return;
    activeRoot = root;
    showError('');
    const type = root.getAttribute('data-pbk-type') || 'string';
    const label = root.getAttribute('data-pbk-label') || root.getAttribute('data-pbk-key') || '';
    let options: string[] = [];
    try {
      const parsed: unknown = JSON.parse(root.getAttribute('data-pbk-options') || '[]');
      options = Array.isArray(parsed) ? parsed.map(String) : [];
    } catch {
      options = [];
    }
    const valueNode = root.querySelector<HTMLElement>('[data-pbk-value]');
    let current: unknown = root.getAttribute('data-pbk-raw-value');
    if (current === null) {
      if (type === 'bool') {
        current = Boolean(
          valueNode && /^(yes|sí|oui|ja|sim)$/i.test((valueNode.textContent || '').trim()),
        );
      } else if (type === 'html' || type === 'richtext' || type === 'raw') {
        current = valueNode ? valueNode.innerHTML : '';
      } else if (type === 'image') {
        const img = valueNode?.querySelector('img');
        current = img ? img.getAttribute('src') : '';
      } else if (type === 'icon') {
        const icon = valueNode?.querySelector('i');
        current = icon ? icon.className : (valueNode?.textContent?.trim() ?? '');
      } else if (type === 'url') {
        const a = valueNode?.querySelector('a');
        current = a ? a.getAttribute('href') : (valueNode?.textContent?.trim() ?? '');
      } else {
        current = valueNode?.textContent?.trim() ?? '';
      }
    }

    titleEl.textContent = label;
    bodyEl.innerHTML = '';
    const labelEl = document.createElement('label');
    labelEl.className = 'pbk-field-modal__label';
    labelEl.setAttribute('for', 'pbk-field-input');
    labelEl.textContent = label;
    const input = buildInput(type, current, options);
    bodyEl.appendChild(labelEl);
    bodyEl.appendChild(input);
    modal.hidden = false;
    const dialog = modal as HTMLDialogElement;
    if (typeof dialog.showModal === 'function') {
      dialog.showModal();
    }
    input.focus();
  }

  function applyValueToDom(root: HTMLElement, type: string, value: unknown): void {
    const valueNode = root.querySelector<HTMLElement>('[data-pbk-value]');
    if (!valueNode) return;
    root.setAttribute(
      'data-pbk-raw-value',
      typeof value === 'boolean' ? (value ? '1' : '0') : String(value),
    );
    if (type === 'bool') {
      valueNode.textContent = value ? 'Yes' : 'No';
    } else if (type === 'image') {
      valueNode.innerHTML = value
        ? '<img src="' + escapeAttr(String(value)) + '" alt="" class="pbk-field__img">'
        : '';
    } else if (type === 'icon') {
      valueNode.innerHTML = value
        ? '<i class="' + escapeAttr(String(value)) + '" aria-hidden="true"></i>'
        : '';
    } else if (type === 'url') {
      valueNode.innerHTML = value
        ? '<a href="' + escapeAttr(String(value)) + '">' + escapeHtml(String(value)) + '</a>'
        : '';
    } else if (type === 'html' || type === 'richtext' || type === 'raw') {
      valueNode.innerHTML = value == null ? '' : String(value);
    } else {
      valueNode.textContent = value == null ? '' : String(value);
    }
  }

  function escapeAttr(s: string): string {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;');
  }

  function escapeHtml(s: string): string {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function save(): void {
    if (!activeRoot || !bodyEl || !saveBtn) return;
    const input = bodyEl.querySelector<FieldInput>('#pbk-field-input');
    if (!input) return;
    const type = activeRoot.getAttribute('data-pbk-type') || 'string';
    const value = readInput(type, input);
    const url = activeRoot.getAttribute('data-pbk-save-url');
    const csrf = activeRoot.getAttribute('data-pbk-csrf');
    const locale = activeRoot.getAttribute('data-pbk-locale');
    const label = activeRoot.getAttribute('data-pbk-label');
    let labels: Record<string, string> = {};
    try {
      const parsed: unknown = JSON.parse(activeRoot.getAttribute('data-pbk-labels') || '{}');
      labels =
        parsed !== null && typeof parsed === 'object' && !Array.isArray(parsed)
          ? Object.fromEntries(
              Object.entries(parsed as Record<string, unknown>).map(([k, v]) => [k, String(v)]),
            )
          : {};
    } catch {
      labels = {};
    }
    if (label && locale && !labels[locale]) {
      labels[locale] = label;
    }
    const required = activeRoot.getAttribute('data-pbk-required') === '1';
    let options: string[] = [];
    try {
      const parsed: unknown = JSON.parse(activeRoot.getAttribute('data-pbk-options') || '[]');
      options = Array.isArray(parsed) ? parsed.map(String) : [];
    } catch {
      options = [];
    }

    if (!url) {
      showError('Missing save URL');
      return;
    }

    saveBtn.disabled = true;
    showError('');

    fetch(url, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf ?? '',
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        locale,
        value,
        type,
        label,
        labels,
        required,
        options,
      }),
    })
      .then(async (res) => {
        const data = (await res.json()) as SaveResponse;
        return { ok: res.ok, status: res.status, data };
      })
      .then((result) => {
        saveBtn.disabled = false;
        if (!result.ok) {
          showError(result.data.error || 'Save failed');
          return;
        }
        if (activeRoot) {
          applyValueToDom(activeRoot, type, result.data.value);
        }
        const dialog = modal as HTMLDialogElement;
        if (typeof dialog.close === 'function') dialog.close();
        modal.hidden = true;
        activeRoot = null;
      })
      .catch(() => {
        saveBtn.disabled = false;
        showError('Network error');
      });
  }

  document.addEventListener('click', (ev) => {
    const target = ev.target;
    if (!(target instanceof Element)) return;
    const btn = target.closest('[data-pbk-edit]');
    if (!btn) return;
    const root = btn.closest<HTMLElement>('[data-pbk-field]');
    if (!root) return;
    ev.preventDefault();
    openEditor(root);
  });

  saveBtn?.addEventListener('click', (ev) => {
    ev.preventDefault();
    save();
  });

  modal.querySelectorAll('[data-pbk-modal-close]').forEach((btn) => {
    btn.addEventListener('click', (ev) => {
      ev.preventDefault();
      const dialog = modal as HTMLDialogElement;
      if (typeof dialog.close === 'function') {
        dialog.close();
      }
      modal.hidden = true;
      activeRoot = null;
    });
  });

  modal.addEventListener('close', () => {
    activeRoot = null;
    showError('');
  });
}

initInlineEdit();
