<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Twig;

use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Service\PageRenderProviderInterface;
use Nowo\PageBuilderKitBundle\Twig\PageBuilderKitExtension;
use Nowo\PageBuilderKitBundle\Widget\WidgetTypeInterface;
use Nowo\PageBuilderKitBundle\Widget\WidgetTypeRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageBuilderKitExtension::class)]
final class PageBuilderKitExtensionTest extends TestCase
{
    #[Test]
    public function exposesLayoutCssAndWidgetTypes(): void
    {
        $heading = new class implements WidgetTypeInterface {
            public function getType(): string
            {
                return 'heading';
            }

            public function getLabelKey(): string
            {
                return 'widget.heading.label';
            }

            public function defaultProps(): array
            {
                return [];
            }

            public function sanitizeProps(array $props, PageBuilderProtection $protection): array
            {
                return $props;
            }

            public function getPublicTemplate(): string
            {
                return '@NowoPageBuilderKitBundle/widgets/heading.html.twig';
            }

            public function allowsChildren(): bool
            {
                return false;
            }
        };

        $registry       = new WidgetTypeRegistry([$heading]);
        $renderProvider = new class implements PageRenderProviderInterface {
            public function getRenderedTree(string $pageKey, ?string $locale = null, array $context = []): array
            {
                return ['pageKey' => $pageKey, 'sections' => [], 'context' => $context];
            }
        };

        $extension = new PageBuilderKitExtension(
            '@NowoPageBuilderKitBundle/admin/layout.html.twig',
            'tailwind',
            $renderProvider,
            $registry,
        );

        self::assertSame(
            '@NowoPageBuilderKitBundle/admin/layout.html.twig',
            $extension->layoutTemplate(),
        );
        self::assertSame('tailwind', $extension->cssFramework());
        self::assertSame(
            [
                [
                    'type'            => 'heading',
                    'label_key'       => 'widget.heading.label',
                    'allows_children' => false,
                ],
            ],
            $extension->widgetTypes(),
        );
        self::assertCount(4, $extension->getFunctions());

        self::assertSame(
            ['pageKey' => 'home', 'sections' => [], 'context' => ['x' => 1]],
            $extension->renderPage('home', 'es', ['x' => 1]),
        );
    }
}
