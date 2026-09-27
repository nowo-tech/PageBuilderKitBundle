import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * REQ-DEMO-013 — crop to page-builder functionality (public render + admin canvas).
 */
const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

async function loginAsAdmin(page: import('@playwright/test').Page) {
  await page.goto('/login');
  await page.fill('#username', 'admin');
  await page.fill('#password', 'admin');
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/\/login$/);
}

/** Hide Symfony Web Profiler so crops stay product-only (REQ-DEMO-013). */
async function hideDemoChrome(page: import('@playwright/test').Page) {
  await page.addStyleTag({
    content:
      '.sf-toolbar, .sf-minitoolbar, [id^="sfwdt"] { display: none !important; visibility: hidden !important; }',
  });
}

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.describe('PageBuilderKit screenshots (functionality only)', () => {
  test('overview — public pricing compounds', async ({ page }) => {
    await page.goto('/pricing');
    const feature = page.locator('.pbk-pricing-grid').first();
    await expect(feature).toBeVisible({ timeout: 15000 });
    await hideDemoChrome(page);
    await feature.screenshot({ path: resolve(outDir, 'overview.png') });
  });

  test('interaction — GrapesJS admin canvas', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/page-builder/pages/pricing/canvas');
    const canvas = page.locator('#page-builder-canvas').first();
    await expect(canvas).toBeVisible({ timeout: 15000 });
    await expect(page.locator('#gjs').first()).toBeVisible({ timeout: 30000 });
    // Wait for GrapesJS chrome to mount
    await page.waitForSelector('#gjs .gjs-cv-canvas, #gjs .gjs-frame, .gjs-pn-views', {
      timeout: 45000,
    });
    await hideDemoChrome(page);
    // Crop to GrapesJS shell (editor + blocks), not locale hint / status line
    const gjs = page.locator('#gjs').first();
    await gjs.screenshot({ path: resolve(outDir, 'interaction.png') });
  });
});
