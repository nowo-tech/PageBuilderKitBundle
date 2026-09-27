<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Enum;

enum HtmlSanitizeStrategy: string
{
    case None      = 'none';
    case Strip     = 'strip';
    case Allowlist = 'allowlist';
    case Service   = 'service';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
