/**
 * Typed Grapes canvas config surfaces (shared by page-builder-canvas.ts).
 */

export type PluginFlags = Record<string, boolean | undefined>;

export type GrapesTwigConfig = {
  enabled?: boolean;
  canvasHelpers?: boolean;
  variables?: Record<string, unknown>;
};

export type GrapesBlockPackBlock = {
  id: string;
  label: string;
  category: string;
  content: string | Record<string, unknown>;
  media?: string;
  attributes?: Record<string, unknown>;
};

export type GrapesBlockPack = {
  name: string;
  version?: string;
  capabilities?: string[];
  blocks: GrapesBlockPackBlock[];
};

export type GrapesFrontendConfig = {
  pluginCdnBase?: string;
  cdnVersion?: string;
  height?: string;
  allowScripts?: boolean;
  allowCustomCode?: boolean;
  compoundExamples?: boolean;
  a11yHelpers?: boolean;
  showDevices?: boolean;
  noticeOnUnload?: boolean;
  cssFramework?: string;
  canvasStyles?: string[];
  plugins?: PluginFlags;
  assets?: unknown[];
  assetEmbedAsBase64?: boolean;
  assetsUploadEnabled?: boolean;
  assetsUploadUrl?: string;
  assetsLibraryUrl?: string;
  uploadUrl?: string;
  assetCsrf?: string;
  contentFields?: Array<Record<string, unknown>>;
  twig?: GrapesTwigConfig;
  blockPacks?: GrapesBlockPack[];
};

export type LocalePayload = {
  html: string;
  css: string;
  grapes: Record<string, unknown>;
};

export type GrapesDocumentStructure = {
  version: number;
  engine: string;
  html: string;
  css: string;
  grapes: Record<string, unknown>;
  localeContent: Record<string, LocalePayload>;
  fields?: unknown[];
  fieldValues?: Record<string, unknown>;
  sections?: unknown[];
};

export function emptyLocalePayload(): LocalePayload {
  return { html: '', css: '', grapes: {} };
}

export function parseJsonAttr<T>(el: Element, name: string, fallback: T): T {
  const raw = el.getAttribute(name);
  if (!raw) return fallback;
  try {
    return JSON.parse(raw) as T;
  } catch {
    return fallback;
  }
}

export function ensureLocaleContent(
  structure: GrapesDocumentStructure,
  locales: string[],
  defaultLocale: string,
): GrapesDocumentStructure {
  if (!structure.localeContent || typeof structure.localeContent !== 'object') {
    structure.localeContent = {};
  }
  for (const locale of locales) {
    if (!structure.localeContent[locale]) {
      if (
        locale === defaultLocale
        && (structure.html || structure.css || (structure.grapes && Object.keys(structure.grapes).length))
      ) {
        structure.localeContent[locale] = {
          html: structure.html || '',
          css: structure.css || '',
          grapes: structure.grapes || {},
        };
      } else {
        structure.localeContent[locale] = emptyLocalePayload();
      }
    }
  }
  return structure;
}
