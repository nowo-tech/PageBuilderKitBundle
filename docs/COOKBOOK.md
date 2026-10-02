# Cookbook

Short recipes for extending **Page Builder Kit** without forking the bundle.

Primary path: **GrapesJS schema v2** + **content fields**. Classic Sections (schema v1) remains supported as **legacy**.

## Table of contents

- [Ship a Grapes block pack](#ship-a-grapes-block-pack)
- [Share templates across projects](#share-templates-across-projects)
- [Prefer Grapes over classic Sections](#prefer-grapes-over-classic-sections)
- [Related docs](#related-docs)

## Ship a Grapes block pack

1. Create a Composer package (or host module) that depends on `nowo-tech/page-builder-kit-bundle:^1.4`.
2. Implement `GrapesBlockPackInterface` and tag the service `nowo_page_builder_kit.grapes_block_pack`.
3. Include a small SVG `media` icon so blocks are recognizable in the BlockManager.
4. Clear the host cache and open any Grapes canvas — packs appear under their `category`.

```php
use Nowo\PageBuilderKitBundle\Grapes\GrapesBlockPackInterface;

final class AcmeMarketingBlockPack implements GrapesBlockPackInterface
{
    public function getName(): string
    {
        return 'acme/marketing-blocks';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getCapabilities(): array
    {
        return ['marketing'];
    }

    public function getBlocks(): array
    {
        return [[
            'id'       => 'acme-promo',
            'label'    => 'Promo banner',
            'category' => 'Acme',
            'media'    => '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2" fill="currentColor"/></svg>',
            'content'  => '<section class="acme-promo"><h2>Promo</h2><p>Short pitch</p></section>',
        ]];
    }
}
```

```yaml
# config/services.yaml
services:
    Acme\AcmeMarketingBlockPack:
        tags: ['nowo_page_builder_kit.grapes_block_pack']
```

**Governance tips**

- Keep `id` stable across pack versions (editors may embed component types).
- Prefer HTML `content` that references content fields (`{{ fields.hero_title }}` or `[[fields.hero_title]]`) over hard-coded copy.
- Demo reference: `App\Demo\DemoGrapesBlockPack` in `demo/symfony8`.
- Copy-ready Composer package: [`examples/acme-grapes-block-pack/`](../examples/acme-grapes-block-pack/) (path-require + service tag recipe in its README).

Full API notes: [WIDGET_AUTHORS.md § GrapesJS block packs](WIDGET_AUTHORS.md#grapesjs-block-packs).

## Share templates across projects

Templates are portable JSON (`formatVersion: 1`). SEO meta is stored as `structure.templateSeoByLocale` and only applied when `include_seo` is true.

### Admin UI

1. Open **Templates** (`/admin/page-builder/templates`).
2. **Export** one template or **Export all**.
3. In the target project: **Import** the JSON (overwrite optional).
4. **Apply** with checkboxes:
   - `include_field_schema` (default on)
   - `include_field_values` (default off)
   - `include_seo` (default off)

### Programmatic

```php
use Nowo\PageBuilderKitBundle\Service\PageTemplateService;

// Source project
$payload = $templates->export('pricing-tpl');
// or: $templates->exportAll();

// Target project
$templates->import($payload, overwrite: true);
$page = $templates->createPageFromTemplate(
    'pricing-tpl',
    'pricing-eu',
    'Pricing EU',
    'en',
    [
        'include_field_schema' => true,
        'include_field_values' => false,
        'include_seo'          => false,
    ],
);
```

**Governance tips**

- Do not commit customer-specific field values into shared packs; ship schema + layout only.
- Review `include_seo` before enabling — meta/canonical may be environment-specific.
- Classic (v1) templates still import, but new shareable packs should be Grapes + fields.

See [USAGE.md § Template sharing](USAGE.md#template-sharing).

## Prefer Grapes over classic Sections

| | Grapes + content fields | Classic Sections (legacy) |
| --- | --- | --- |
| Schema | v2 `engine: grapesjs` | v1 `sections` / columns / widgets |
| Editor | Canvas | Sections UI (warning banner) |
| i18n content | `fieldValues[locale]` (+ optional `localeContent`) | `widgetPropsByLocale` |
| Extensibility | Grapes block packs | Classic widget packs |

New pages created by the bundle default to Grapes. Keep classic only for existing documents until migrated.

## Related docs

- [USAGE.md](USAGE.md) — routes, CSRF, Twig, collector
- [WIDGET_AUTHORS.md](WIDGET_AUTHORS.md) — classic widgets + Grapes packs
- [UPGRADING.md](UPGRADING.md#140) — host steps for 1.4.0
- [BUILDER-MANUAL.md](BUILDER-MANUAL.md) — editor screenshots
