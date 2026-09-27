<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

use function html_entity_decode;
use function in_array;
use function is_array;
use function is_string;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function preg_replace;
use function preg_replace_callback;
use function rawurldecode;
use function str_ireplace;
use function trim;

use const ENT_HTML5;
use const ENT_QUOTES;
use const LIBXML_HTML_NODEFDTD;
use const LIBXML_HTML_NOIMPLIED;

/**
 * Sanitizes GrapesJS-exported HTML/CSS for public render.
 */
final readonly class GrapesDocumentSanitizer
{
    public function __construct(
        private bool $allowScripts = false,
    ) {
    }

    public function sanitizeHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $wrapped  = '<div id="pbk-root">' . $html . '</div>';
        $previous = libxml_use_internal_errors(true);
        $dom      = new DOMDocument('1.0', 'UTF-8');
        $loaded   = $dom->loadHTML('<?xml encoding="UTF-8">' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            // @codeCoverageIgnoreStart
            return strip_tags($html, '<div><span><p><a><img><h1><h2><h3><h4><h5><h6><ul><ol><li><section><article><header><footer><nav><main><aside><br><strong><em><b><i><u><table><thead><tbody><tr><th><td><figure><figcaption><blockquote><hr><video><source>');
            // @codeCoverageIgnoreEnd
        }

        $xpath = new DOMXPath($dom);
        foreach (['script', 'iframe', 'object', 'embed', 'form', 'link', 'meta', 'base'] as $tag) {
            if ($tag === 'script' && $this->allowScripts) {
                continue;
            }
            foreach ($xpath->query('//' . $tag) ?: [] as $node) {
                if ($node instanceof DOMNode) {
                    $node->parentNode?->removeChild($node);
                }
            }
        }

        foreach ($xpath->query('//@*') ?: [] as $attrNode) {
            if (!$attrNode instanceof DOMNode) {
                continue; // @codeCoverageIgnore
            }
            $name = strtolower($attrNode->nodeName);
            if (str_starts_with($name, 'on') || $name === 'formaction' || $name === 'xlink:href') {
                if ($attrNode->parentNode instanceof DOMElement) {
                    $attrNode->parentNode->removeAttribute($attrNode->nodeName);
                }
            }
            if (in_array($name, ['href', 'src', 'xlink:href'], true)) {
                $value = strtolower(trim($attrNode->nodeValue ?? ''));
                if (str_starts_with($value, 'javascript:')) {
                    if ($attrNode->parentNode instanceof DOMElement) {
                        $attrNode->parentNode->removeAttribute($attrNode->nodeName);
                    }
                }
            }
        }

        if (!$this->allowScripts) {
            // @codeCoverageIgnoreStart — scripts already removed in the tag loop above when allowScripts=false
            foreach ($xpath->query('//script') ?: [] as $node) {
                if ($node instanceof DOMNode) {
                    $node->parentNode?->removeChild($node);
                }
            }
            // @codeCoverageIgnoreEnd
        }

        $root = $dom->getElementById('pbk-root');
        if (!$root instanceof DOMElement) {
            return ''; // @codeCoverageIgnore
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child) ?: '';
        }

        // DOMDocument URL-encodes spaces inside attribute values ("{{ p.url }}" → "{{%20p.url%20}}"),
        // which breaks Twig lexing. Restore Twig delimiters after serialization.
        return $this->restoreTwigDelimiters($out);
    }

    /**
     * Decode HTML/URL encoding that libxml applies inside {{ }}, {% %} and {# #} tokens.
     *
     * Attribute values may be fully percent-encoded (`{{` → `%7B%7B`), so restore
     * delimiter bytes before matching Twig tokens and decoding spaces.
     */
    private function restoreTwigDelimiters(string $html): string
    {
        $html = str_ireplace(
            ['%7B%7B', '%7D%7D', '%7B%25', '%25%7D', '%7B%23', '%23%7D'],
            ['{{', '}}', '{%', '%}', '{#', '#}'],
            $html,
        );

        $restored = preg_replace_callback(
            '/\{\{.*?\}\}|\{%.*?%\}|\{#.*?#\}/s',
            static function (array $matches): string {
                $token = html_entity_decode($matches[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return rawurldecode($token);
            },
            $html,
        );

        return is_string($restored) ? $restored : $html;
    }

    public function sanitizeCss(string $css): string
    {
        $css = trim($css);
        if ($css === '') {
            return '';
        }

        $lower = strtolower($css);
        if (
            str_contains($lower, 'expression(')
            || str_contains($lower, 'javascript:')
            || str_contains($lower, 'behavior:')
            || str_contains($lower, '-moz-binding')
            || str_contains($lower, '@import')
        ) {
            return '';
        }

        // Drop closing style/script breakouts.
        $css = preg_replace('/<\/(style|script)/i', '', $css) ?? '';

        return $css;
    }

    /**
     * @param array<string, mixed> $structure
     *
     * @return array<string, mixed>
     */
    public function sanitizeStructure(array $structure): array
    {
        if (is_string($structure['html'] ?? null)) {
            $structure['html'] = $this->sanitizeHtml($structure['html']);
        }
        if (is_string($structure['css'] ?? null)) {
            $structure['css'] = $this->sanitizeCss($structure['css']);
        }
        if (!is_array($structure['grapes'] ?? null)) {
            $structure['grapes'] = [];
        }

        return $structure;
    }
}
