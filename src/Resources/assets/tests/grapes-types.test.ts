import { describe, expect, it } from 'vitest';
import { emptyLocalePayload, ensureLocaleContent, parseJsonAttr, type GrapesDocumentStructure } from '../src/grapes-types';

describe('grapes-types', () => {
  it('builds an empty locale payload', () => {
    expect(emptyLocalePayload()).toEqual({ html: '', css: '', grapes: {} });
  });

  it('parses JSON attributes with a fallback', () => {
    const el = document.createElement('div');
    expect(parseJsonAttr(el, 'data-x', { a: 1 })).toEqual({ a: 1 });
    el.setAttribute('data-x', '{"b":2}');
    expect(parseJsonAttr(el, 'data-x', {})).toEqual({ b: 2 });
    el.setAttribute('data-x', '{broken');
    expect(parseJsonAttr(el, 'data-x', 'fallback')).toBe('fallback');
  });

  it('seeds the default locale from the root document and empties the others', () => {
    const structure = { version: 2, engine: 'grapesjs', html: '<p>x</p>', css: 'p{}', grapes: {}, localeContent: null } as unknown as GrapesDocumentStructure;
    const out = ensureLocaleContent(structure, ['en', 'es'], 'en');
    expect(out.localeContent.en).toEqual({ html: '<p>x</p>', css: 'p{}', grapes: {} });
    expect(out.localeContent.es).toEqual(emptyLocalePayload());
  });

  it('keeps existing locale content and seeds from grapes data only', () => {
    const structure: GrapesDocumentStructure = {
      version: 2, engine: 'grapesjs', html: '', css: '', grapes: { pages: [] }, localeContent: { es: { html: 'es', css: '', grapes: {} } },
    };
    const out = ensureLocaleContent(structure, ['en', 'es'], 'en');
    expect(out.localeContent.es.html).toBe('es');
    expect(out.localeContent.en.grapes).toEqual({ pages: [] });
    const blank: GrapesDocumentStructure = { version: 2, engine: 'grapesjs', html: '', css: '', grapes: undefined as unknown as Record<string, unknown>, localeContent: {} };
    expect(ensureLocaleContent(blank, ['en'], 'en').localeContent.en).toEqual(emptyLocalePayload());
    const htmlOnly = { version: 2, engine: 'grapesjs', html: undefined, css: 'a{}', grapes: undefined, localeContent: {} } as unknown as GrapesDocumentStructure;
    expect(ensureLocaleContent(htmlOnly, ['en'], 'en').localeContent.en).toEqual({ html: '', css: 'a{}', grapes: {} });
  });
});
