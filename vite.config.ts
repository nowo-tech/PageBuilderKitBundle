/**
 * Vite builds for Page Builder Kit public assets → `src/Resources/public/js/*.js`.
 * Controlled by VITE_ENTRY. Run all: `pnpm run build`.
 */
import { defineConfig } from 'vite';

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
          entryFileNames: 'js/page-builder-admin.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
};

const effectiveEntry: Entry = entry && configs[entry] ? entry : 'canvas';

export default defineConfig(configs[effectiveEntry]);
