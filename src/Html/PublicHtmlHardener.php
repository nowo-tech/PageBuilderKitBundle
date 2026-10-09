<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Html;

use Dom\Element;
use Dom\HTMLDocument;
use Nowo\PageBuilderKitBundle\Security\Html\AllowlistPageBuilderHtmlSanitizer;
use Nowo\PageBuilderKitBundle\Service\GrapesDocumentSanitizer;

use function in_array;
use function preg_match;
use function preg_replace;
use function preg_split;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function trim;

use const LIBXML_NOERROR;

/**
 * Final XSS gate for editor HTML right before it is printed with `|raw` on public pages
 * (GrapesJS pages, `/p/{pageKey}` preview, classic text/html widgets, inline HTML fields).
 *
 * The save/render sanitizers ({@see GrapesDocumentSanitizer},
 * {@see AllowlistPageBuilderHtmlSanitizer}) are libxml (HTML4)
 * based. This pass parses the final markup with the HTML5 algorithm ({@see HTMLDocument}, PHP 8.4+, the
 * same one browsers use) and always re-serializes it, so what is checked is exactly what the browser will
 * build: no parser differential (HTML5 entities such as `&colon;`, `<!-->` comments, `<xmp>` / `<noembed>`
 * raw text, `<svg/onload>` …). It drops executable elements, event handlers and script URLs.
 *
 * Idempotent: `harden(harden($x)) === harden($x)`.
 */
final readonly class PublicHtmlHardener
{
    /** Elements removed together with their content. */
    private const array DROP_ELEMENTS = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet',
        'base', 'meta', 'link', 'form', 'math', 'portal', 'noscript', 'template',
        'xmp', 'noembed', 'noframes', 'plaintext',
        // SVG animation / foreign content can set href to javascript: or host raw HTML.
        'animate', 'animatemotion', 'animatetransform', 'set', 'foreignobject', 'handler', 'listener',
    ];

    /** Attributes removed regardless of value. */
    private const array DROP_ATTRIBUTES = ['formaction', 'srcdoc', 'action'];

    /** Attributes whose value is a URL (or URL list) and must not use a script scheme. */
    private const array URL_ATTRIBUTES = [
        'href', 'src', 'xlink:href', 'poster', 'background', 'cite', 'data', 'lowsrc', 'dynsrc',
        'ping', 'srcset', 'data-src', 'data-srcset', 'data-full-src', 'values', 'from', 'to', 'by',
    ];

    /** Attributes holding a list of URLs. */
    private const array URL_LIST_ATTRIBUTES = ['srcset', 'data-srcset', 'values'];

    private const string SAFE_DATA_IMAGE = '#^data:image/(?:png|jpe?g|gif|webp|avif)[;,]#';

    /**
     * @param bool $allowScripts Mirrors `grapesjs.allow_scripts`: when true, `<script>` elements survive
     *                           (every other rule still applies). Keep false unless editors are fully trusted.
     */
    public function __construct(
        private bool $allowScripts = false,
    ) {
    }

    public function harden(?string $html): string
    {
        $html ??= '';
        if (trim($html) === '') {
            return $html;
        }

        $document = HTMLDocument::createFromString(
            '<!DOCTYPE html><html><head></head><body>' . $html . '</body></html>',
            LIBXML_NOERROR,
            'UTF-8',
        );
        $body = $document->body;
        if (!$body instanceof Element) {
            return ''; // @codeCoverageIgnore
        }

        /** @var list<Element> $drop */
        $drop = [];
        foreach ($body->querySelectorAll('*') as $element) {
            $name = strtolower($element->localName);
            if (in_array($name, self::DROP_ELEMENTS, true) && (!$this->allowScripts || $name !== 'script')) {
                $drop[] = $element;

                continue;
            }
            $this->hardenAttributes($element);
        }
        foreach ($drop as $element) {
            $element->remove();
        }

        return $body->innerHTML;
    }

    /**
     * Page Builder CSS printed inside `<style>`: a one-pass `</style` strip can be defeated by nested input
     * (`</sty</stylele>`). A literal `<` is never needed in CSS outside strings, and `\3C ` is its CSS
     * escape, so no tag can ever close or open.
     */
    public function hardenCss(?string $css): string
    {
        return str_replace('<', '\\3C ', $css ?? '');
    }

    private function hardenAttributes(Element $element): void
    {
        /** @var list<string> $remove */
        $remove = [];
        foreach ($element->attributes as $attribute) {
            $name = strtolower($attribute->name);
            if (str_starts_with($name, 'on') || in_array($name, self::DROP_ATTRIBUTES, true)) {
                $remove[] = $attribute->name;

                continue;
            }
            if (in_array($name, self::URL_ATTRIBUTES, true) && $this->isScriptUrl($attribute->value, $name)) {
                $remove[] = $attribute->name;
            }
        }

        foreach ($remove as $name) {
            $element->removeAttribute($name);
        }
    }

    /**
     * True when any URL in the attribute uses javascript:, vbscript: or a non-image data: URL.
     * Browsers ignore ASCII whitespace / control characters inside the scheme, so strip them first.
     */
    private function isScriptUrl(string $value, string $attribute): bool
    {
        $candidates = in_array($attribute, self::URL_LIST_ATTRIBUTES, true)
            ? (preg_split('/[,;]/', $value) ?: [])
            : [$value];

        foreach ($candidates as $candidate) {
            $normalized = strtolower((string) preg_replace('/[\x00-\x20]+/', '', $candidate));
            if (str_starts_with($normalized, 'javascript:') || str_starts_with($normalized, 'vbscript:')) {
                return true;
            }
            if (str_starts_with($normalized, 'data:') && preg_match(self::SAFE_DATA_IMAGE, $normalized) !== 1) {
                return true;
            }
        }

        return false;
    }
}
