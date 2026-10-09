<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Twig;

use Nowo\PageBuilderKitBundle\Html\PublicHtmlHardener;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessCheckerInterface;
use Nowo\PageBuilderKitBundle\Service\InlineContentFieldRendererInterface;
use Nowo\PageBuilderKitBundle\Service\PageRenderProviderInterface;
use Nowo\PageBuilderKitBundle\Service\PageRevisionStore;
use Nowo\PageBuilderKitBundle\Service\PublicBindHydratorInterface;
use Nowo\PageBuilderKitBundle\Util\ContentSectionFilter;
use Nowo\PageBuilderKitBundle\Widget\WidgetTypeInterface;
use Nowo\PageBuilderKitBundle\Widget\WidgetTypeRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class PageBuilderKitExtension extends AbstractExtension
{
    public function __construct(
        private readonly string $layoutTemplate,
        private readonly string $cssFramework,
        private readonly PageRenderProviderInterface $pageRenderProvider,
        private readonly WidgetTypeRegistry $widgetTypeRegistry,
        private readonly PageBuilderKitAccessCheckerInterface $accessChecker,
        private readonly InlineContentFieldRendererInterface $inlineContentFieldRenderer,
        private readonly ?PageRevisionStore $pageRevisionStore = null,
        private readonly ?PublicBindHydratorInterface $publicBindHydrator = null,
        private readonly PublicHtmlHardener $publicHtmlHardener = new PublicHtmlHardener(),
    ) {
    }

    public function getFilters(): array
    {
        return [
            // Editor HTML printed with |raw (Grapes pages, /p/{pageKey}, classic text/html widgets): HTML5 final gate.
            new TwigFilter('pbk_harden_html', $this->publicHtmlHardener->harden(...), ['is_safe' => ['html']]),
            // GrapesJS CSS printed inside <style>: escape `<` so it can never close the element.
            new TwigFilter('pbk_harden_css', $this->publicHtmlHardener->hardenCss(...), ['is_safe' => ['html']]),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nowo_page_builder_layout_template', $this->layoutTemplate(...)),
            new TwigFunction('nowo_page_builder_css_framework', $this->cssFramework(...)),
            new TwigFunction('nowo_page_builder_render', $this->renderPage(...)),
            new TwigFunction('nowo_page_builder_widget_types', $this->widgetTypes(...)),
            new TwigFunction('nowo_page_builder_can_edit', $this->canEdit(...)),
            new TwigFunction('nowo_page_builder_can', $this->can(...)),
            new TwigFunction('nowo_page_builder_revisions_enabled', $this->revisionsEnabled(...)),
            new TwigFunction('nowo_page_builder_field', $this->field(...), ['is_safe' => ['html']]),
            new TwigFunction('nowo_page_builder_hydrate_binds', $this->hydrateBinds(...), ['is_safe' => ['html']]),
            new TwigFunction('nowo_page_builder_sanitize_section', ContentSectionFilter::sanitize(...)),
            new TwigFunction('nowo_page_builder_filter_schema_by_section', ContentSectionFilter::filterSchema(...)),
        ];
    }

    public function layoutTemplate(): string
    {
        return $this->layoutTemplate;
    }

    public function cssFramework(): string
    {
        return $this->cssFramework;
    }

    /**
     * Whether the current request may open the page builder admin (pencil).
     * Prefer this function over storing the flag in Twig globals (FrankenPHP workers).
     */
    public function canEdit(): bool
    {
        return $this->accessChecker->canAccess();
    }

    /**
     * Fine-grained capability: layout | content | publish | templates.
     */
    public function can(string $capability): bool
    {
        return $this->accessChecker->can($capability);
    }

    public function revisionsEnabled(): bool
    {
        return $this->pageRevisionStore?->isEnabled() === true;
    }

    /**
     * Inline multilingual field: shows stored value; with content capability, pencil + modal.
     *
     * @param array<string, mixed> $options type, label, labels, locale, default, tag, class, options, editable
     */
    public function field(string $pageKey, string $fieldKey, array $options = []): string
    {
        return $this->inlineContentFieldRenderer->render($pageKey, $fieldKey, $options);
    }

    /**
     * Wrap `data-pbk-bind` slots in public Grapes HTML with inline-edit controls for editors.
     * Returns the HTML unchanged when no hydrator is wired.
     */
    public function hydrateBinds(string $html, string $pageKey): string
    {
        return $this->publicBindHydrator?->hydrate($html, $pageKey) ?? $html;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function renderPage(string $pageKey, ?string $locale = null, array $context = []): array
    {
        return $this->pageRenderProvider->getRenderedTree($pageKey, $locale, $context);
    }

    /**
     * @return list<array{type: string, label_key: string}>
     */
    public function widgetTypes(): array
    {
        return array_map(
            static fn (WidgetTypeInterface $type): array => [
                'type'            => $type->getType(),
                'label_key'       => $type->getLabelKey(),
                'allows_children' => $type->allowsChildren(),
            ],
            $this->widgetTypeRegistry->all(),
        );
    }
}
