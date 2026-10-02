<?php

declare(strict_types=1);

namespace Acme\GrapesBlockPack;

use Nowo\PageBuilderKitBundle\Grapes\GrapesBlockPackInterface;

/**
 * Example Composer pack: host-registered GrapesJS blocks.
 *
 * Copy this directory, rename the namespace/package, then require it from your Symfony app
 * and tag the service `nowo_page_builder_kit.grapes_block_pack` (see README).
 */
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
        return ['marketing', 'grapesjs'];
    }

    public function getBlocks(): array
    {
        return [
            [
                'id'       => 'acme-promo-banner',
                'label'    => 'Promo banner',
                'category' => 'Acme',
                'media'    => '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2" fill="currentColor"/></svg>',
                'content'  => '<section class="acme-promo" style="padding:1.5rem;border-radius:.75rem;background:#0f172a;color:#f8fafc"><h2 style="margin:0 0 .5rem">{{ fields.hero_title }}</h2><p style="margin:0;opacity:.9">[[fields.hero_blurb]]</p></section>',
            ],
            [
                'id'       => 'acme-cta-row',
                'label'    => 'CTA row',
                'category' => 'Acme',
                'media'    => '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M4 12h12M12 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>',
                'content'  => '<div class="acme-cta" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap"><a href="[[fields.cta_url]]" style="display:inline-block;padding:.65rem 1.1rem;border-radius:.5rem;background:#2563eb;color:#fff;text-decoration:none;font-weight:600">[[fields.cta_label]]</a><span style="opacity:.7;font-size:.9rem">[[fields.cta_note]]</span></div>',
            ],
        ];
    }
}
