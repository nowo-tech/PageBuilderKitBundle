import { defineConfig } from 'vite';
import symfonyPlugin from 'vite-plugin-symfony';

/**
 * Pentatrion Vite (vite-plugin-symfony): Twig helpers vite_entry_* and manifest/entrypoints.
 */
export default defineConfig({
  plugins: [symfonyPlugin()],
  build: {
    rollupOptions: {
      input: {
        app: './assets/app.ts',
      },
    },
  },
});
