/**
 * Vite builds for Page Builder Kit public assets → `src/Resources/public/js/*.js`.
 * Controlled by VITE_ENTRY. Run all: `pnpm run build`.
 */
import { createHash } from 'node:crypto';
import { readFileSync, readdirSync, statSync, writeFileSync } from 'node:fs';
import { defineConfig } from 'vite';

/**
 * Deterministic source hash: a hash of the TypeScript/CSS sources and pnpm-lock.yaml. It only changes when the code or
 * the dependencies change; it is the change detector of buildTimeLabel() below.
 */
function sourceBuildId(dir: string): string {
  const root = new URL(`${dir}/`, import.meta.url);
  const hash = createHash('sha256');
  const files = (readdirSync(root, { recursive: true }) as string[])
    .filter((file) => !file.includes('.test.') && statSync(new URL(file, root)).isFile())
    .sort();
  for (const file of files) {
    hash.update(file).update(readFileSync(new URL(file, root)));
  }
  hash.update(readFileSync(new URL('pnpm-lock.yaml', import.meta.url)));

  return hash.digest('hex').slice(0, 12);
}

/**
 * Readable, reproducible build time for the "script loaded" console line ("2026-11-23 14:05 UTC").
 * The committed `build-info.json` ({ sourceHash, builtAt }) keeps it stable: when the source hash equals the stored
 * one, the stored `builtAt` is reused (a rebuild of unchanged sources never rewrites the committed assets); when it
 * differs, the new hash is stored with the current UTC minute. Commit `build-info.json` with the rebuilt assets: a CI
 * dist-freshness rebuild of a clean checkout then reproduces the same bytes, and fails visibly if it was forgotten.
 */
function buildTimeLabel(dir: string): string {
  const sourceHash = sourceBuildId(dir);
  const file = new URL('build-info.json', import.meta.url);
  let info: { sourceHash?: string; builtAt?: string } = {};
  try {
    info = JSON.parse(readFileSync(file, 'utf8')) as typeof info;
  } catch {
    // Missing or unreadable: written below.
  }
  if (info.sourceHash !== sourceHash || typeof info.builtAt !== 'string') {
    info = { sourceHash, builtAt: `${new Date().toISOString().slice(0, 16)}Z` };
    writeFileSync(file, `${JSON.stringify(info, null, 2)}\n`);
    console.warn(`build-info.json updated (${info.builtAt}): commit it together with the rebuilt assets.`);
  }

  return (info.builtAt as string).replace('T', ' ').replace('Z', ' UTC');
}

type Entry = 'canvas' | 'inline-edit' | 'admin';

const entry = process.env.VITE_ENTRY as Entry | undefined;

const configs: Record<Entry, { build: object }> = {
  canvas: {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/page-builder-canvas.ts',
        output: {
          format: 'es' as const,
          entryFileNames: 'js/page-builder-canvas.js',
          inlineDynamicImports: true,
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  'inline-edit': {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/page-builder-inline-edit.ts',
        output: {
          format: 'iife' as const,
          strict: true,
          entryFileNames: 'js/page-builder-inline-edit.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  admin: {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/page-builder-admin.ts',
        output: {
          format: 'iife' as const,
          strict: true,
          entryFileNames: 'js/page-builder-admin.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
};

const effectiveEntry: Entry = entry && configs[entry] ? entry : 'canvas';

export default defineConfig({
  // One build id for all three scripts: a hash of the sources and pnpm-lock.yaml (no wall-clock date).
  define: {
    __PAGE_BUILDER_KIT_BUILD_TIME__: JSON.stringify(buildTimeLabel('src/Resources/assets/src')),
  },
  ...configs[effectiveEntry],
});
