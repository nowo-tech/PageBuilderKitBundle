# Widget authors guide (Phase 4)

How to ship **classic schema v1** widget types as a Composer package or host module without forking Page Builder Kit Bundle.

Spec Kit: [`specs/004-phase4-external-widgets/spec.md`](../specs/004-phase4-external-widgets/spec.md).

## Table of contents

- [Quick start](#quick-start)
- [WidgetPackInterface](#widgetpackinterface)
- [Capabilities](#capabilities)
- [Templates](#templates)
- [Sanitization](#sanitization)
- [GrapesJS block packs](#grapesjs-block-packs)

## Quick start

1. Implement `Nowo\PageBuilderKitBundle\Widget\WidgetTypeInterface` (or extend `AbstractWidgetType`).
2. Tag the service with `nowo_page_builder_kit.widget_type`.
3. Optionally group types in a `WidgetPackInterface` service tagged `nowo_page_builder_kit.widget_pack`.

```php
use Nowo\PageBuilderKitBundle\Widget\AbstractWidgetType;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;

final class PromoBannerWidgetType extends AbstractWidgetType
{
    public function getType(): string
    {
        return 'acme_promo_banner';
    }

    public function getLabelKey(): string
    {
        return 'widget.acme_promo_banner.label';
    }

    public function defaultProps(): array
    {
        return ['headline' => '', 'ctaUrl' => ''];
    }

    protected function sanitizeMergedProps(array $props, PageBuilderProtection $protection): array
    {
        return [
            'headline' => is_string($props['headline'] ?? null) ? $props['headline'] : '',
            'ctaUrl'   => is_string($props['ctaUrl'] ?? null) ? $props['ctaUrl'] : '',
        ];
    }
}
```

```yaml
# config/services.yaml (host or pack)
services:
    Acme\PromoBannerWidgetType:
        tags: ['nowo_page_builder_kit.widget_type']
```

## WidgetPackInterface

```php
use Nowo\PageBuilderKitBundle\Widget\WidgetPackInterface;
use Nowo\PageBuilderKitBundle\Widget\WidgetTypeInterface;

final class AcmeMarketingWidgetPack implements WidgetPackInterface
{
    public function __construct(
        private readonly PromoBannerWidgetType $promo,
    ) {
    }

    public function getName(): string
    {
        return 'acme/marketing-widgets';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    /** @return list<WidgetTypeInterface> */
    public function getWidgetTypes(): array
    {
        return [$this->promo];
    }

    public function getCapabilities(): array
    {
        return ['classic_v1'];
    }
}
```

Tag with `nowo_page_builder_kit.widget_pack`. Autoconfiguration is enabled for the interface.

Optional tag attribute `types` lists service ids to merge into `WidgetTypeRegistry` when types are not separately tagged:

```yaml
services:
    Acme\AcmeMarketingWidgetPack:
        tags:
            - { name: nowo_page_builder_kit.widget_pack, types: ['Acme\\PromoBannerWidgetType'] }
```

`WidgetPackRegistry::summarize()` exposes packs for diagnostics (and the Web Profiler when the collector is on).

## Capabilities

Use free-form capability strings. Suggested conventions:

| Flag | Meaning |
| --- | --- |
| `classic_v1` | Works with section/column documents |
| `allows_html` | Props may contain HTML (host should prefer `html.sanitize.strategy: allowlist`) |
| `requires_editor` | Needs TipTap/CKEditor in the classic Sections UI |

## Templates

Default public template path from `AbstractWidgetType`:

`@NowoPageBuilderKitBundle/widgets/{type}.html.twig`

Override `getPublicTemplate()` or place a host override under:

`templates/bundles/NowoPageBuilderKitBundle/widgets/{type}.html.twig`

## Sanitization

Always sanitize user-controlled HTML through `PageBuilderProtection` in `sanitizeProps` / `sanitizeMergedProps`. Never trust raw props on public render.

## GrapesJS block packs

Free-form GrapesJS pages (schema v2) do **not** use classic widget types. Ship reusable BlockManager entries with `GrapesBlockPackInterface` (tag `nowo_page_builder_kit.grapes_block_pack`). Packs are serialized into the canvas config as `blockPacks` and registered client-side.

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
            'content'  => '<div class="acme-promo">Promo</div>',
        ]];
    }
}
```

Classic widgets remain the extension point for schema v1 Sections pages and nested containers.
