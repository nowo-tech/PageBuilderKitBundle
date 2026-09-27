<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Widget;

/**
 * Groups one or more classic widget types shipped by a Composer package or host module.
 *
 * Tag the implementing service with `nowo_page_builder_kit.widget_pack`.
 * Each returned {@see WidgetTypeInterface} should also be tagged
 * `nowo_page_builder_kit.widget_type` (or rely on the pack compiler pass).
 */
interface WidgetPackInterface
{
    /** Stable pack id, e.g. `acme/marketing-widgets`. */
    public function getName(): string;

    /** Semver string for integrator diagnostics. */
    public function getVersion(): string;

    /**
     * @return list<WidgetTypeInterface>
     */
    public function getWidgetTypes(): array;

    /**
     * Optional capability flags (e.g. `allows_html`, `requires_editor`).
     *
     * @return list<string>
     */
    public function getCapabilities(): array;
}
