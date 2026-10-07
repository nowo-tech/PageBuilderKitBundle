<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Twig;

use Nowo\PageBuilderKitBundle\Enum\PageBuilderCapability;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessCheckerInterface;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Service\InlineContentFieldRendererInterface;
use Nowo\PageBuilderKitBundle\Service\PageRenderProviderInterface;
use Nowo\PageBuilderKitBundle\Service\PublicBindHydratorInterface;
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
        self::assertCount(11, $extension->getFunctions());
        self::assertSame('<p>x</p>', $extension->hydrateBinds('<p>x</p>', 'home'));
        self::assertTrue($extension->can('layout'));
        self::assertSame('<span>field</span>', $extension->field('home', 'hero_title', ['type' => 'string']));

        self::assertSame(
            ['pageKey' => 'home', 'sections' => [], 'context' => ['x' => 1]],
            $extension->renderPage('home', 'es', ['x' => 1]),
        );
    }

    #[Test]
    public function hydrateBindsDelegatesToHydrator(): void
    {
        $hydrator = $this->createMock(PublicBindHydratorInterface::class);
        $hydrator->expects(self::once())->method('hydrate')->with('<span data-pbk-bind="a">x</span>', 'home')->willReturn('HYDRATED');

        $extension = new PageBuilderKitExtension(
            'layout.html.twig',
            'tailwind',
            $this->createStub(PageRenderProviderInterface::class),
            new WidgetTypeRegistry([]),
            $this->createStub(PageBuilderKitAccessCheckerInterface::class),
            $this->createStub(InlineContentFieldRendererInterface::class),
            null,
            $hydrator,
        );

        self::assertSame('HYDRATED', $extension->hydrateBinds('<span data-pbk-bind="a">x</span>', 'home'));

        $names = array_map(static fn ($f): string => $f->getName(), $extension->getFunctions());
        self::assertContains('nowo_page_builder_hydrate_binds', $names);
        self::assertContains('nowo_page_builder_sanitize_section', $names);
        self::assertContains('nowo_page_builder_filter_schema_by_section', $names);
    }
}
