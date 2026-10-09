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

        // The wrapper is identified by reference: an `id="pbk-root"` in the input must not be
        // mistaken for it (it would skip sanitizing, e.g. `<script id="pbk-root">`).
        $root = null;
        foreach ($document->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $root = $child;
                break;
            }
        }
        if (!$root instanceof DOMElement) {
            return '';
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
            if ($node === $root || $tag === 'html' || $tag === 'body') {
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
            if (($name === 'href' || $name === 'src') && !self::isSafeUrl($attr->value, $name === 'src')) {
                $remove[] = $attr->name;
            }
        }

        foreach ($remove as $name) {
            $element->removeAttribute($name);
        }
    }

    /**
     * Browsers ignore ASCII whitespace / control characters inside a URL scheme
     * (`java\tscript:`), so they are removed before the scheme is checked.
     */
    private static function isSafeUrl(string $value, bool $imageSource): bool
    {
        $normalized = strtolower((string) preg_replace('/[\x00-\x20\x7f]+/', '', $value));
        if (str_starts_with($normalized, 'javascript:') || str_starts_with($normalized, 'vbscript:')) {
            return false;
        }
        if (str_starts_with($normalized, 'data:')) {
            return $imageSource && preg_match('#^data:image/(png|jpe?g|gif|webp|avif);#', $normalized) === 1;
        }

        return true;
    }
}
