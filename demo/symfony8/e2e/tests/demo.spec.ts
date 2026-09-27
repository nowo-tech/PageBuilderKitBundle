import { test, expect } from '@playwright/test';

async function loginAsAdmin(page: import('@playwright/test').Page) {
  await page.goto('/login');
  await page.fill('#username', 'admin');
  await page.fill('#password', 'admin');
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/\/login$/);
}

test.describe('PageBuilderKit demo', () => {
  test('public pricing page renders compounds', async ({ page }) => {
    const response = await page.goto('/pricing');
    expect(response?.ok()).toBeTruthy();
    await expect(page.locator('.pbk-pricing-grid, [data-pbk-compound="pricing"]').first()).toBeVisible();
  });

  test('admin canvas loads GrapesJS after login', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/page-builder/pages/pricing/canvas');
    await expect(page.locator('#page-builder-canvas, #gjs').first()).toBeVisible({ timeout: 15000 });
    await expect(page.locator('#gjs .gjs-cv-canvas, #gjs .gjs-editor, #gjs').first()).toBeVisible({
      timeout: 30000,
    });
  });
});
