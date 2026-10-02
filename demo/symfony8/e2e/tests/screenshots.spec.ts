import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * REQ-DEMO-013 — screenshots for BUILDER-MANUAL.md + overview captures.
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

/** Wait until the Web Debug Toolbar is visible and finished loading. */
async function waitForWebProfiler(page: import('@playwright/test').Page) {
  await page.waitForSelector('.sf-toolbar, .sf-minitoolbar', {
    state: 'visible',
    timeout: 20000,
  }).catch(() => undefined);
  const mini = page.locator('.sf-minitoolbar .sf-toolbar-icon, .sf-minitoolbar a').first();
  if (await mini.isVisible().catch(() => false)) {
    await mini.click();
  }
  await page.waitForFunction(
    () => {
      const bar = document.querySelector('.sf-toolbar') as HTMLElement | null;
      if (!bar || bar.offsetParent === null) {
        return true; // profiler optional in some envs
      }
      const text = (bar.textContent || '').replace(/\s+/g, ' ');
      if (/Loading/i.test(text) && !/\b\d{3}\b/.test(text)) {
        return false;
      }
      return !!document.querySelector('.sf-toolbar-block, .sf-toolbar-status');
    },
    { timeout: 25000 },
  ).catch(() => undefined);
  await new Promise((r) => setTimeout(r, 400));
}

async function shot(page: import('@playwright/test').Page, name: string) {
  await waitForWebProfiler(page);
  await page.screenshot({
    path: resolve(outDir, name),
    fullPage: false,
  });
}

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

async function waitForGrapesCanvas(page: import('@playwright/test').Page) {
  await expect(page.locator('#page-builder-canvas, #gjs').first()).toBeVisible({ timeout: 15000 });
  // GrapesJS loads plugins from CDN/esm.sh — wait for editor chrome, not just empty #gjs.
  await expect(page.locator('#gjs .gjs-editor, #gjs .gjs-pn-panels').first()).toBeVisible({
    timeout: 60000,
  });
  await expect(page.locator('#gjs .gjs-cv-canvas, #gjs iframe').first()).toBeVisible({
    timeout: 30000,
  });
  await page.waitForFunction(
    () => {
      const status = document.querySelector('[data-pbk-status]')?.textContent || '';
      if (/Loading GrapesJS|Failed to load/i.test(status)) {
        return false;
      }
      const iframe = document.querySelector('#gjs iframe') as HTMLIFrameElement | null;
      const doc = iframe?.contentDocument;
      const html = doc?.body?.innerHTML || '';
      return html.length > 80;
    },
    { timeout: 60000 },
  );
  // Let layout/paint settle (blocks panel + canvas iframe).
  await new Promise((r) => setTimeout(r, 800));
}

test.describe('PageBuilderKit screenshots (builder manual)', () => {
  test.use({ viewport: { width: 1440, height: 900 } });

  test('overview — public pricing compounds', async ({ page }) => {
    await page.goto('/pricing');
    await expect(page.locator('.pbk-pricing-grid').first()).toBeVisible({ timeout: 15000 });
    await shot(page, 'overview.png');
  });

  test('builder-06 — showcase matrix', async ({ page }) => {
    await page.goto('/showcase');
    await expect(page.getByText(/use cases|casos de uso|showcase/i).first()).toBeVisible({ timeout: 15000 });
    await shot(page, 'builder-06-showcase.png');
  });

  test('builder-04 — public content fields page', async ({ page }) => {
    await page.goto('/fields');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible({ timeout: 15000 });
    await shot(page, 'builder-04-fields-public.png');
  });

  test('builder-01 — admin page list', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/page-builder/pages');
    await expect(page.getByText(/home|pages|páginas/i).first()).toBeVisible({ timeout: 15000 });
    await shot(page, 'builder-01-page-list.png');
  });

  test('builder-02 / interaction — GrapesJS canvas', async ({ page }) => {
    await loginAsAdmin(page);
    // Pricing has dense compound content — clearer manual screenshot than an empty-looking boot.
    await page.goto('/admin/page-builder/pages/pricing/canvas');
    await waitForGrapesCanvas(page);
    await shot(page, 'builder-02-canvas.png');
    await shot(page, 'interaction.png');
  });

  test('builder-03 — content fields admin', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/page-builder/pages/fields/content');
    await expect(page.getByText(/hero_title|faqs|schema|esquema|valores|values/i).first()).toBeVisible({
      timeout: 15000,
    });
    await shot(page, 'builder-03-content.png');
  });

  test('builder-05 — templates admin', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/page-builder/templates');
    await expect(page.getByText(/template|plantilla/i).first()).toBeVisible({ timeout: 15000 });
    await shot(page, 'builder-05-templates.png');
  });
});
