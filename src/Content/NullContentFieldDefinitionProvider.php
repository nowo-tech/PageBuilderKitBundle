<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Content;

/**
 * Default provider: no known fields (bind slots fall back to type `string` and the field key as label).
 */
final class NullContentFieldDefinitionProvider implements ContentFieldDefinitionProviderInterface
{
    public function definitions(string $pageKey): array
    {
        return [];
    }
}
