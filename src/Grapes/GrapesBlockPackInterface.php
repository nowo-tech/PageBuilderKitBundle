<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Grapes;

/**
 * Groups one or more GrapesJS BlockManager entries shipped by a Composer package or host module.
 *
 * Tag the implementing service with `nowo_page_builder_kit.grapes_block_pack`.
 * Blocks are serialized into the admin canvas config (`blockPacks`) and registered client-side.
 */
interface GrapesBlockPackInterface
{
    /** Stable pack id, e.g. `acme/marketing-blocks`. */
    public function getName(): string;

    /** Semver string for integrator diagnostics. */
    public function getVersion(): string;

    /**
     * Optional capability flags (e.g. `marketing`, `email`).
     *
     * @return list<string>
     */
    public function getCapabilities(): array;

    /**
     * GrapesJS BlockManager definitions.
     *
     * Each block requires `id`, `label`, `category`, and `content` (HTML string or component object).
     *
     * @return list<array{
     *     id: string,
     *     label: string,
     *     category: string,
     *     content: array<string, mixed>|string,
     *     media?: string,
     *     attributes?: array<string, mixed>
     * }>
     */
    public function getBlocks(): array;
}
