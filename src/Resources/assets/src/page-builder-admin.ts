/**
 * Page Builder Kit admin entry (IIFE → `src/Resources/public/js/page-builder-admin.js`): image upload and media
 * library picker of the content-fields page. Logic and docs: admin-media.ts.
 */
import { initAdmin } from './admin-media';
import { createBundleLogger } from './logger';

declare const __PAGE_BUILDER_KIT_BUILD_TIME__: string;

createBundleLogger('page-builder-kit/admin', {
  buildTime: typeof __PAGE_BUILDER_KIT_BUILD_TIME__ !== 'undefined' ? __PAGE_BUILDER_KIT_BUILD_TIME__ : undefined,
}).scriptLoaded();

initAdmin();
