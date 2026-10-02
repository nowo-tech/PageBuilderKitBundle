# Example Grapes block pack

Minimal **Composer package** you can copy to publish reusable GrapesJS blocks for [Page Builder Kit](https://github.com/nowo-tech/PageBuilderKitBundle).

## Install in a host app (path repo)

```bash
# composer.json (host)
{
  "repositories": [{
    "type": "path",
    "url": "vendor/nowo-tech/page-builder-kit-bundle/examples/acme-grapes-block-pack"
  }],
  "require": {
    "acme/grapes-block-pack": "*"
  }
}
```

Or copy this folder into your monorepo and require it via a `path` / private VCS repository.

## Register the pack

```yaml
# config/services.yaml
services:
    Acme\GrapesBlockPack\AcmeMarketingBlockPack:
        tags: ['nowo_page_builder_kit.grapes_block_pack']
```

Clear the cache and open any Grapes canvas — blocks appear under category **Acme**.

## Publish your own pack

1. Rename `acme/*` namespaces and the Composer package name.
2. Keep block `id` values stable across versions.
3. Prefer content-field slots (`{{ fields.* }}` / `[[fields.*]]`) over hard-coded copy.
4. `composer publish` / Packagist as usual; depend on `nowo-tech/page-builder-kit-bundle:^1.4`.

See the bundle [Cookbook](../../docs/COOKBOOK.md#ship-a-grapes-block-pack) and `App\Demo\DemoGrapesBlockPack` in `demo/symfony8`.
