import { test, expect, type Page } from '@playwright/test';

/** REQ-UI-003 — the admin pages render on the UiKit scaffolds inside the demo host layout. */
async function loginAsAdmin(page: Page) {
  await page.goto('/login');
  await page.fill('input[name="_username"]', 'admin');
  await page.fill('input[name="_password"]', 'admin');
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/\/login$/);
}

test.describe('PageBuilderKit admin (UiKit page contract)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('pages list is a UiKit list with row actions and a create card', async ({ page }) => {
    await page.goto('/admin/page-builder/pages');
    const list = page.locator('[data-nowo-ui-page="list"][data-testid="pbk-pages"]');
    await expect(list).toBeVisible();
    await expect(list.locator('table')).toBeVisible();
    await expect(list.locator('tbody tr').first()).toContainText(/\w/);
    await expect(page.locator('[data-testid="pbk-edit"]').first()).toBeVisible();
    await expect(page.locator('[data-testid="pbk-create-page"] form')).toBeVisible();
    await expect(page.locator('.modal.fade')).toHaveCount(0);
    await expect(page.locator('script:not([src])').filter({ hasText: 'confirm(' })).toHaveCount(0);
  });

  test('duplicate asks the new key in the UiKit confirm dialog', async ({ page }) => {
    await page.goto('/admin/page-builder/pages');
    const key = `e2e-${Date.now()}`;
    const row = page.locator('tbody tr', { has: page.locator('code', { hasText: /^home$/ }) });
    await row.locator('button.nowo-ui-action--copy').click();
    const dialog = page.locator('dialog[open]');
    await expect(dialog).toBeVisible();
    await dialog.locator('input[name="pageKey"]').fill(key);
    await dialog.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/canvas$|\/pages/);
    await page.goto('/admin/page-builder/pages?page=1');
    await expect(page.locator('body')).toContainText(key);
  });

  test('content fields page uses the settings scaffold and the media dialog', async ({ page }) => {
    await page.goto('/admin/page-builder/pages/fields/content');
    await expect(page.locator('[data-nowo-ui-page="settings"][data-testid="pbk-content"]')).toBeVisible();
    await expect(page.locator('#nowo-ui-settings-schema table')).toBeVisible();
    await expect(page.locator('#nowo-ui-settings-values form')).toBeVisible();
    const picker = page.locator('[data-pbk-media-pick]').first();
    if (await picker.count()) {
      await picker.click();
      await expect(page.locator('dialog#pbk-media-library[open]')).toBeVisible();
      await page.keyboard.press('Escape');
    }
  });

  test('SEO, versions and templates render on scaffolds', async ({ page }) => {
    await page.goto('/admin/page-builder/pages/home/seo');
    await expect(page.locator('[data-nowo-ui-page="form"][data-testid="pbk-seo"] form')).toBeVisible();

    await page.goto('/admin/page-builder/pages/home/revisions');
    await expect(page.locator('[data-testid="pbk-revisions"]')).toBeVisible();
    await page.locator('[data-testid="pbk-revision-create"] button[type="submit"]').click();
    await expect(page.locator('[data-testid="pbk-revisions"] tbody tr').first()).toBeVisible();
    await page.locator('[data-testid="pbk-revisions"] a.nowo-ui-action--view').first().click();
    await expect(page.locator('[data-nowo-ui-page="show"][data-testid="pbk-revision-diff"]')).toBeVisible();

    await page.goto('/admin/page-builder/templates');
    await expect(page.locator('[data-testid="pbk-templates"]')).toBeVisible();
    await expect(page.locator('[data-testid="pbk-template-import"] textarea')).toBeVisible();
  });

  test('canvas toolbar keeps the UiKit slot classes when publishing state changes', async ({ page }) => {
    await page.goto('/admin/page-builder/pages/pricing/canvas');
    await expect(page.locator('[data-testid="pbk-canvas-page"]')).toBeVisible();
    await expect(page.locator('[data-pbk-status-badge]')).toHaveClass(/nowo-ui-badge/);
    await expect(page.locator('link[href*="grapes.min.css"]')).toHaveCount(1);
    await expect(page.locator('link[href*="unpkg.com"]')).toHaveCount(0);
  });
});
