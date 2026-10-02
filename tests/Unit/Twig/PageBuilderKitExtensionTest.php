<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Twig;

use Nowo\PageBuilderKitBundle\Enum\PageBuilderCapability;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessCheckerInterface;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Service\InlineContentFieldRendererInterface;
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
    public function exposesLayoutCssWidgetTypesAndCanEdit(): void
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
            public function getRenderedTree(
                string $pageKey,
                ?string $locale = null,
                array $context = [],
                bool $draftPreview = false,
            ): array {
                unset($draftPreview);

                return ['pageKey' => $pageKey, 'sections' => [], 'context' => $context];
            }
        };
        $accessChecker = new class implements PageBuilderKitAccessCheckerInterface {
            public function canAccess(): bool
            {
                return true;
            }

            public function canLayout(): bool
            {
                return true;
            }

            public function canContent(): bool
            {
                return true;
            }

            public function canPublish(): bool
            {
                return true;
            }

            public function canTemplates(): bool
            {
                return true;
            }

            public function can(PageBuilderCapability|string $capability): bool
            {
                return true;
            }
        };

        $inline = $this->createStub(InlineContentFieldRendererInterface::class);
        $inline->method('render')->willReturn('<span>field</span>');

        $extension = new PageBuilderKitExtension(
            '@NowoPageBuilderKitBundle/admin/layout.html.twig',
            'tailwind',
            $renderProvider,
            $registry,
            $accessChecker,
            $inline,
        );

        self::assertSame(
            '@NowoPageBuilderKitBundle/admin/layout.html.twig',
            $extension->layoutTemplate(),
        );
        self::assertSame('tailwind', $extension->cssFramework());
        self::assertTrue($extension->canEdit());
        self::assertFalse($extension->revisionsEnabled());
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
        self::assertCount(8, $extension->getFunctions());
        self::assertTrue($extension->can('layout'));
        self::assertSame('<span>field</span>', $extension->field('home', 'hero_title', ['type' => 'string']));

        self::assertSame(
            ['pageKey' => 'home', 'sections' => [], 'context' => ['x' => 1]],
            $extension->renderPage('home', 'es', ['x' => 1]),
        );
    }
}
