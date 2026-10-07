<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Content;

use Nowo\PageBuilderKitBundle\Enum\ContentFieldType;
use Nowo\PageBuilderKitBundle\Service\PublicBindHydrator;

/**
 * Host-provided catalogue of content fields per page key.
 *
 * Used by {@see PublicBindHydrator} to resolve the field type and
 * the label (translation id) of Grapes `data-pbk-bind` slots without querying the page document.
 *
 * Override the default (empty) provider by aliasing this interface to a host service.
 *
 * @phpstan-type ContentFieldDefinition array{key: string, type: string, label: string}
 */
interface ContentFieldDefinitionProviderInterface
{
    /**
     * @return list<array{key: string, type: string, label: string}> `type` is a {@see ContentFieldType} value;
     *                                                               `label` is a translation id (or plain text)
     */
    public function definitions(string $pageKey): array;
}
