<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Nowo\PageBuilderKitBundle\Security\Html\SafeUrl;

use function array_is_list;
use function array_values;
use function html_entity_decode;
use function in_array;
use function is_array;
use function is_string;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function preg_replace;
use function preg_replace_callback;
use function rawurldecode;
use function str_contains;
use function str_ireplace;
use function str_starts_with;
use function strtolower;
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
    /** Elements removed from HTML and from the GrapesJS component tree (`script` only when scripts are off). */
    private const array BLOCKED_TAGS = ['script', 'iframe', 'object', 'embed', 'form', 'link', 'meta', 'base'];

    /** Attributes whose value is a URL; script schemes (and non-image `data:`) are dropped. */
    private const array URL_ATTRIBUTES = ['href', 'src', 'action', 'poster', 'background', 'data'];

    public function __construct(
        private bool $allowScripts = false,
    ) {
    }

    /**
     * @param bool $restoreTwigDelimiters When true (default), HTML/URL encoding inside `{{ }}`, `{% %}` and
     *                                    `{# #}` is decoded so the result can be compiled as a Twig template.
     *                                    The decoded output is Twig *source*, never safe HTML: pass false when the
     *                                    result is printed to a browser (Twig output, Twig error fallback).
     */
    public function sanitizeHtml(string $html, bool $restoreTwigDelimiters = true): string
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
        foreach (self::BLOCKED_TAGS as $tag) {
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

        if (!$restoreTwigDelimiters) {
            return $out;
        }

        // DOMDocument URL-encodes spaces inside attribute values ("{{ p.url }}" → "{{%20p.url%20}}"),
        // which breaks Twig lexing. Restore Twig delimiters after serialization.
        // SECURITY: entity-decoding turns `{{ &lt;script&gt; }}` into `{{ <script> }}` — only feed this to Twig.
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
        $structure['grapes'] = is_array($structure['grapes'] ?? null) ? $this->sanitizeGrapes($structure['grapes']) : [];

        return $this->sanitizeLocaleContent($structure);
    }

    /**
     * @param array<string, mixed> $structure
     *
     * @return array<string, mixed>
     */
    public function sanitizeLocaleContent(array $structure): array
    {
        $localeContent = $structure['localeContent'] ?? [];
        if (!is_array($localeContent)) {
            $structure['localeContent'] = [];

            return $structure;
        }

        $sanitized = [];
        foreach ($localeContent as $locale => $content) {
            if (!is_string($locale) || !is_array($content)) {
                continue;
            }
            $sanitized[$locale] = [
                'html'   => $this->sanitizeHtml(is_string($content['html'] ?? null) ? $content['html'] : ''),
                'css'    => $this->sanitizeCss(is_string($content['css'] ?? null) ? $content['css'] : ''),
                'grapes' => is_array($content['grapes'] ?? null) ? $this->sanitizeGrapes($content['grapes']) : [],
            ];
        }
        $structure['localeContent'] = $sanitized;

        return $structure;
    }

    /**
     * Walks a GrapesJS project tree (pages → frames → components) with the same rules as {@see sanitizeHtml()}.
     *
     * The canvas reloads `grapes` through `loadProjectData()`, which renders components as-is (no HTML parsing),
     * so event handlers or script components stored there would run in the next editor's admin session.
     *
     * @param array<mixed> $grapes
     *
     * @return array<mixed>
     */
    public function sanitizeGrapes(array $grapes): array
    {
        $isList = array_is_list($grapes);
        $out    = [];
        foreach ($grapes as $key => $value) {
            if (is_string($key)) {
                $lower = strtolower($key);
                if (!$this->allowScripts && ($lower === 'script' || str_starts_with($lower, 'script-'))) {
                    continue;
                }
                if ($lower === 'attributes' && is_array($value)) {
                    $out[$key] = $this->sanitizeGrapesAttributes($value);
                    continue;
                }
                if ($lower === 'content' && is_string($value) && str_contains($value, '<')) {
                    $out[$key] = $this->sanitizeHtml($value);
                    continue;
                }
            }
            if (is_array($value)) {
                if ($this->isBlockedGrapesComponent($value)) {
                    continue;
                }
                $value = $this->sanitizeGrapes($value);
            }
            $out[$key] = $value;
        }

        return $isList ? array_values($out) : $out;
    }

    /**
     * @param array<mixed> $component
     */
    private function isBlockedGrapesComponent(array $component): bool
    {
        // `type` is a GrapesJS component type ("link" is an anchor), so only `script` is matched there.
        $tag  = is_string($component['tagName'] ?? null) ? strtolower($component['tagName']) : null;
        $type = is_string($component['type'] ?? null) ? strtolower($component['type']) : null;
        if ($this->allowScripts && ($tag === 'script' || $type === 'script')) {
            return false;
        }

        return $type === 'script' || ($tag !== null && in_array($tag, self::BLOCKED_TAGS, true));
    }

    /**
     * @param array<mixed> $attributes
     *
     * @return array<mixed>
     */
    private function sanitizeGrapesAttributes(array $attributes): array
    {
        foreach ($attributes as $name => $value) {
            $lower = strtolower(trim((string) $name));
            if (str_starts_with($lower, 'on') || in_array($lower, ['srcdoc', 'formaction', 'xlink:href'], true)) {
                unset($attributes[$name]);
                continue;
            }
            if (in_array($lower, self::URL_ATTRIBUTES, true) && is_string($value)
                && !SafeUrl::isSafe(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $lower !== 'href' && $lower !== 'action')) {
                unset($attributes[$name]);
            }
        }

        return $attributes;
    }
}
