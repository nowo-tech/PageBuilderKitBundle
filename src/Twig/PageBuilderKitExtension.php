<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Twig;

use Nowo\PageBuilderKitBundle\Service\PageRenderProviderInterface;
use Nowo\PageBuilderKitBundle\Widget\WidgetTypeInterface;
use Nowo\PageBuilderKitBundle\Widget\WidgetTypeRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PageBuilderKitExtension extends AbstractExtension
{
    public function __construct(
        private readonly string $layoutTemplate,
        private readonly string $cssFramework,
        private readonly PageRenderProviderInterface $pageRenderProvider,
        private readonly WidgetTypeRegistry $widgetTypeRegistry,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nowo_page_builder_layout_template', $this->layoutTemplate(...)),
            new TwigFunction('nowo_page_builder_css_framework', $this->cssFramework(...)),
            new TwigFunction('nowo_page_builder_render', $this->renderPage(...)),
            new TwigFunction('nowo_page_builder_widget_types', $this->widgetTypes(...)),
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
