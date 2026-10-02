<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Security\Html;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

use function array_merge;
use function implode;
use function in_array;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function preg_match;
use function str_starts_with;
use function strtolower;
use function trim;

use const LIBXML_HTML_NODEFDTD;
use const LIBXML_HTML_NOIMPLIED;

final class AllowlistPageBuilderHtmlSanitizer implements PageBuilderHtmlSanitizerInterface
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'em', 'a', 'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'img', 'span',
    ];

    private const ALLOWED_ATTRS = [
        'a'   => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        '*'   => ['class', 'id'],
    ];

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $wrapped  = '<div id="pbk-root">' . $html . '</div>';
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $loaded   = $document->loadHTML(
            '<?xml encoding="UTF-8">' . $wrapped,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return strip_tags($html, '<' . implode('><', self::ALLOWED_TAGS) . '>');
        }

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//*');
        if ($nodes === false) {
            return '';
        }

        /** @var list<DOMElement> $toRemove */
        $toRemove = [];
        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($node->tagName);
            if ($tag === 'html' || $tag === 'body' || $node->getAttribute('id') === 'pbk-root') {
                continue;
            }
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta', 'svg'], true)) {
                    $parent = $node->parentNode;
                    if ($parent instanceof DOMNode) {
                        $parent->removeChild($node);
                    }
                } else {
                    $toRemove[] = $node;
                }
                continue;
            }
            $this->sanitizeAttributes($node, $tag);
        }

        foreach ($toRemove as $node) {
            $parent = $node->parentNode;
            if ($parent instanceof DOMNode) {
                while ($node->firstChild instanceof DOMNode) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
            }
        }

        $root = $document->getElementById('pbk-root');
        if (!$root instanceof DOMElement) {
            return '';
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $document->saveHTML($child) ?: '';
        }

        return $out;
    }

    private function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $allowed = array_merge(
            self::ALLOWED_ATTRS['*'],
            self::ALLOWED_ATTRS[$tag] ?? [],
        );

        /** @var list<string> $remove */
        $remove = [];
        foreach ($element->attributes ?? [] as $attr) {
            $name = strtolower($attr->name);
            if (str_starts_with($name, 'on') || !in_array($name, $allowed, true)) {
                $remove[] = $attr->name;
                continue;
            }
            $value = trim($attr->value);
            if (($name === 'href' || $name === 'src') && preg_match('#^\s*javascript:#i', $value) === 1) {
                $remove[] = $attr->name;
            }
        }

        foreach ($remove as $name) {
            $element->removeAttribute($name);
        }
    }
}
