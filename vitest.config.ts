import { defineConfig } from 'vitest/config';

/**
 * Vitest configuration of the Page Builder Kit Bundle TypeScript sources (REQ-TEST-003 / REQ-TEST-009).
 * Runs every `*.test.ts` under src/Resources/assets with jsdom and enforces the coverage gate (non-zero exit below it).
 */
export default defineConfig({
  test: {
    environment: 'jsdom',
    globals: true,
    include: ['src/Resources/assets/**/*.test.ts'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'text-summary', 'html'],
      reportsDirectory: './coverage-ts',
      include: ['src/Resources/assets/src/**/*.ts'],
      exclude: ['src/Resources/assets/**/*.test.ts', '**/node_modules/**'],
      thresholds: {
        lines: 99,
        functions: 98,
        branches: 96,
        statements: 99,
      },
    },
  },
});
