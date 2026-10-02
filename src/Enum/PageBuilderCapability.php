<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Enum;

/**
 * Fine-grained admin capabilities (REQ-UI / Phase 5C).
 *
 * Hosts map Symfony roles via security.layout_roles / content_roles / … or implement a custom access_checker.
 */
enum PageBuilderCapability: string
{
    case Layout    = 'layout';
    case Content   = 'content';
    case Publish   = 'publish';
    case Templates = 'templates';

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return [
            self::Layout,
            self::Content,
            self::Publish,
            self::Templates,
        ];
    }
}
