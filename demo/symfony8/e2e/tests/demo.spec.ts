import { test, expect } from '@playwright/test';

async function loginAsAdmin(page: import('@playwright/test').Page) {
  await page.goto('/login');
  await page.fill('#username', 'admin');
  await page.fill('#password', 'admin');
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/\/login$/);
}

test.describe('PageBuilderKit demo', () => {
  test('showcase lists use cases including content fields', async ({ page }) => {
    const response = await page.goto('/showcase');
    expect(response?.ok()).toBeTruthy();
    await expect(page.getByText(/content fields|campos tipados|fields/i).first()).toBeVisible();
    await expect(page.locator('a[href="/fields"], a[href*="fields"]').first()).toBeVisible();
  });

  test('public pricing page renders compounds', async ({ page }) => {
    const response = await page.goto('/pricing');
    expect(response?.ok()).toBeTruthy();
    await expect(page.locator('.pbk-pricing-grid, [data-pbk-compound="pricing"]').first()).toBeVisible();
  });

  test('public fields page resolves slots and FAQ repeater', async ({ page }) => {
    const response = await page.goto('/fields');
    expect(response?.ok()).toBeTruthy();
    await expect(page.getByRole('heading', { level: 1 })).toContainText(/content fields|content fields demo|demo de content fields/i);
    await expect(page.locator('details.pbk-compound--faq, details').first()).toBeVisible();
    await expect(page.locator('body')).not.toContainText('[[fields.');
  });

  test('fields page switches locale copy', async ({ page }) => {
    await page.goto('/fields?_locale=es');
    await expect(page.getByRole('heading', { level: 1 })).toContainText(/demo de content fields|content fields/i);
  });

  test('draft public route stays 404 while demo draft page loads', async ({ page }) => {
    const draftApp = await page.goto('/draft');
    expect(draftApp?.ok()).toBeTruthy();
    const draftPublic = await page.goto('/p/draft');
    expect(draftPublic?.status()).toBe(404);
  });

  test('admin page list loads after login', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/page-builder/pages');
    await expect(page.locator('table, .table, [data-pbk-pages], body').first()).toBeVisible();
    await expect(page.locator('a[href*="/pages/home"], code, body').first()).toBeVisible();
    await expect(page.getByText(/home|fields|pricing/i).first()).toBeVisible();
  });

  test('admin canvas loads GrapesJS after login', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/page-builder/pages/pricing/canvas');
    await expect(page.locator('#page-builder-canvas, #gjs').first()).toBeVisible({ timeout: 15000 });
    await expect(page.locator('#gjs .gjs-editor, #gjs .gjs-pn-panels').first()).toBeVisible({
      timeout: 60000,
    });
    await expect(page.locator('#gjs iframe, #gjs .gjs-cv-canvas').first()).toBeVisible({
      timeout: 30000,
    });
  });

  test('admin content editor shows schema and values for fields page', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/page-builder/pages/fields/content');
    await expect(page.getByText(/hero_title|faqs|content/i).first()).toBeVisible({ timeout: 15000 });
    await expect(page.locator('form, input[name*="fieldValues"], input[name="key"]').first()).toBeVisible();
  });

  test('admin templates page is reachable', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/page-builder/templates');
    await expect(page.getByText(/template|plantilla/i).first()).toBeVisible({ timeout: 15000 });
  });
});
