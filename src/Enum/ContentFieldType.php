<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Enum;

/**
 * Typed CMS content fields (Phase 5 + Phase 6 composites).
 *
 * Twig: {{ nowo_page_builder_field(pageKey, 'hero_title', { type: 'html' }) }}
 * Grapes: {{ fields.hero_title }} / {% for row in fields.faqs %}…
 */
enum ContentFieldType: string
{
    case String    = 'string';
    case Text      = 'text';
    case Richtext  = 'richtext';
    case Html      = 'html';
    case Raw       = 'raw';
    case Number    = 'number';
    case Url       = 'url';
    case Image     = 'image';
    case Icon      = 'icon';
    case Bool      = 'bool';
    case Select    = 'select';
    case Repeater  = 'repeater';
    case Group     = 'group';
    case Reference = 'reference';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases(),
        );
    }

    public function isHtmlOutput(): bool
    {
        return match ($this) {
            self::Html, self::Richtext, self::Raw => true,
            default                               => false,
        };
    }

    public function sanitizeOnPersist(): bool
    {
        return match ($this) {
            self::Html, self::Richtext => true,
            self::Raw                  => false,
            default                    => false,
        };
    }

    public function isComposite(): bool
    {
        return match ($this) {
            self::Repeater, self::Group => true,
            default                     => false,
        };
    }

    /**
     * Types allowed inside repeater/group rows (including nested composites).
     */
    public function allowedAsNested(): bool
    {
        return true;
    }
}
