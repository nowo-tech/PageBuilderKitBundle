<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Widget;

use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Widget\WidgetTypeInterface;
use Nowo\PageBuilderKitBundle\Widget\WidgetTypeRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WidgetTypeRegistry::class)]
final class WidgetTypeRegistryTest extends TestCase
{
    #[Test]
    public function registerTypesViaConstructorIterableGetHasAndAll(): void
    {
        $heading = $this->createWidgetType('heading', 'widget.heading.label');
        $text    = $this->createWidgetType('text', 'widget.text.label');

        $registry = new WidgetTypeRegistry([$heading, $text]);

        self::assertTrue($registry->has('heading'));
        self::assertTrue($registry->has('text'));
        self::assertFalse($registry->has('unknown'));

        self::assertSame($heading, $registry->get('heading'));
        self::assertSame($text, $registry->get('text'));

        self::assertSame(['heading', 'text'], $registry->types());
        self::assertCount(2, $registry->all());
    }

    #[Test]
    public function getThrowsForUnknownType(): void
    {
        $registry = new WidgetTypeRegistry([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown widget type "missing".');

        $registry->get('missing');
    }

    private function createWidgetType(string $type, string $labelKey): WidgetTypeInterface
    {
        return new class($type, $labelKey) implements WidgetTypeInterface {
            public function __construct(
                private readonly string $type,
                private readonly string $labelKey,
            ) {
            }

            public function getType(): string
            {
                return $this->type;
            }

            public function getLabelKey(): string
            {
                return $this->labelKey;
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
                return '@NowoPageBuilderKitBundle/widgets/' . $this->type . '.html.twig';
            }

            public function allowsChildren(): bool
            {
                return false;
            }
        };
    }
}
