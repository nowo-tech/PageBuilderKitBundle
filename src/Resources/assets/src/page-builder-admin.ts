/**
 * Page Builder Kit admin helpers (CSP-friendly: no inline event handlers).
 *
 * - `form[data-pbk-confirm]`: asks `window.confirm(<attribute value>)` before submitting
 *   (template delete, revision restore). Replaces the former inline `onsubmit="return confirm(…)"`.
 */
declare global {
  interface Window {
    __pbkAdminInit?: boolean;
  }
}

function initAdmin(): void {
  if (window.__pbkAdminInit) {
    return;
  }
  window.__pbkAdminInit = true;

  document.addEventListener(
    'submit',
    (ev) => {
      const form = ev.target;
      if (!(form instanceof HTMLFormElement)) return;
      const message = form.getAttribute('data-pbk-confirm');
      if (message === null || message === '') return;
      if (!window.confirm(message)) {
        ev.preventDefault();
        ev.stopImmediatePropagation();
      }
    },
    true,
  );
}

initAdmin();

export {};
