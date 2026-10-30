import { describe, expect, it, vi } from 'vitest';

describe('page-builder-admin entry', () => {
  it('initialises the admin helpers on load', async () => {
    window.__pbkAdminInit = false;
    const spy = vi.spyOn(document, 'addEventListener');
    await import('../src/page-builder-admin');
    expect(window.__pbkAdminInit).toBe(true);
    expect(spy).toHaveBeenCalledWith('click', expect.any(Function));
  });
});
