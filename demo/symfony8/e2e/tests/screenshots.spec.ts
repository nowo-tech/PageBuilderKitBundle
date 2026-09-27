import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * REQ-DEMO-013 — full viewport context: demo chrome + page content + Symfony Web Profiler toolbar.
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

/** Wait until the Web Debug Toolbar is visible and finished loading (status blocks, not “Loading…”). */
async function waitForWebProfiler(page: import('@playwright/test').Page) {
  await page.waitForSelector('.sf-toolbar, .sf-minitoolbar', {
    state: 'visible',
    timeout: 20000,
  });
  // Expand mini toolbar if collapsed
  const mini = page.locator('.sf-minitoolbar .sf-toolbar-icon, .sf-minitoolbar a').first();
  if (await mini.isVisible().catch(() => false)) {
    await mini.click();
  }
  await page.waitForFunction(
    () => {
      const bar = document.querySelector('.sf-toolbar') as HTMLElement | null;
      if (!bar || bar.offsetParent === null) {
        return false;
      }
      const text = (bar.textContent || '').replace(/\s+/g, ' ');
      if (/Loading/i.test(text) && !/\b\d{3}\b/.test(text)) {
        return false;
      }
      return !!document.querySelector('.sf-toolbar-block, .sf-toolbar-status');
    },
    { timeout: 25000 },
  );
  // Let AJAX toolbar HTML settle before capture
  await new Promise((r) => setTimeout(r, 400));
}

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.describe('PageBuilderKit screenshots (context + Web Profiler)', () => {
  test.use({ viewport: { width: 1440, height: 900 } });

  test('overview — public pricing compounds with profiler toolbar', async ({ page }) => {
    await page.goto('/pricing');
    await expect(page.locator('.pbk-pricing-grid').first()).toBeVisible({ timeout: 15000 });
    await waitForWebProfiler(page);
    await page.screenshot({
      path: resolve(outDir, 'overview.png'),
      fullPage: false,
    });
  });

  test('interaction — GrapesJS admin canvas with profiler toolbar', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/page-builder/pages/pricing/canvas');
    await expect(page.locator('#page-builder-canvas').first()).toBeVisible({ timeout: 15000 });
    await expect(page.locator('#gjs').first()).toBeVisible({ timeout: 30000 });
    await page.waitForSelector('#gjs .gjs-cv-canvas, #gjs .gjs-frame, .gjs-pn-views', {
      timeout: 45000,
    });
    await waitForWebProfiler(page);
    await page.screenshot({
      path: resolve(outDir, 'interaction.png'),
      fullPage: false,
    });
  });
});
