<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Security\Html;

use DOMNode;
use DOMXPath;

use function in_array;
use function strtolower;

use const XML_CDATA_SECTION_NODE;
use const XML_TEXT_NODE;

/**
 * Removes comments, processing instructions and CDATA sections from a libxml (HTML4) tree. libxml serialises them
 * verbatim, while an HTML5 parser ends a comment at `<!-->` / `<!--->`: their text could become live markup in the
 * browser or in a later string pass. Stateless.
 */
final class HtmlNodePurger
{
    public static function purgeNonElementMarkup(DOMXPath $xpath): void
    {
        $nodes = [];
        foreach ($xpath->query('//comment() | //processing-instruction() | //text()') ?: [] as $node) {
            if (!$node instanceof DOMNode || $node->nodeType === XML_TEXT_NODE) {
                continue;
            }
            // libxml stores the raw text of <script> / <style> as CDATA: that content is handled by the callers.
            if ($node->nodeType === XML_CDATA_SECTION_NODE && in_array(strtolower((string) $node->parentNode?->nodeName), ['script', 'style'], true)) {
                continue;
            }
            $nodes[] = $node;
        }
        foreach ($nodes as $node) {
            $node->parentNode?->removeChild($node);
        }
    }
}
