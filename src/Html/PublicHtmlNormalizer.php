<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Html;

use function is_string;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Fixes HTML artifacts that fail the Nu Html Checker after Page Builder sanitizer round-trips.
 *
 * DOMDocument often emits {@code </source>} wrapping an {@code <img>} inside {@code <picture>}.
 * Skeleton lazy images may lack a {@code src} until a client controller runs — validators
 * require {@code src} or {@code srcset}.
 *
 * Optional WebP picture upgrades are configured by the host (clinic-specific paths stay out of the kit).
 * Each upgrade row is `{png, webp, classContains?}`: replace a PNG img with a picture when the PNG path
 * is present and the WebP path is not. Optional classContains limits the match to that class token.
 */
final class PublicHtmlNormalizer
{
    private const string PLACEHOLDER_SRC = 'src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"';

    /**
     * @param list<array{png: string, webp: string, classContains?: string}> $webpPictureUpgrades
     */
    public function __construct(
        private readonly string $skeletonImgClass = 'site-skeleton__img',
        private readonly array $webpPictureUpgrades = [],
    ) {
    }

    public function normalize(string $html): string
    {
        if ($html === '') {
            return $html;
        }

        $html = (string) preg_replace(
            '#(<picture>\s*<source\b[^>]*>)\s*(<img\b[^>]*>)\s*</source>\s*(</picture>)#i',
            '$1$2$3',
            $html,
        );
        $html = str_replace('</source>', '', $html);

        $class = preg_quote($this->skeletonImgClass, '#');
        $html  = (string) preg_replace(
            '#<img\b(?![^>]*(?<![\w-])src=)([^>]*\b' . $class . '[^>]*)>#i',
            '<img ' . self::PLACEHOLDER_SRC . '$1>',
            $html,
        );

        foreach ($this->webpPictureUpgrades as $upgrade) {
            $png  = $upgrade['png'] ?? '';
            $webp = $upgrade['webp'] ?? '';
            if (!is_string($png) || $png === '' || !is_string($webp) || $webp === '') {
                continue;
            }
            if (!str_contains($html, $png) || str_contains($html, $webp)) {
                continue;
            }

            $pngQuoted  = preg_quote($png, '#');
            $webpQuoted = htmlspecialchars($webp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $classToken = $upgrade['classContains'] ?? null;
            if (is_string($classToken) && $classToken !== '') {
                $classQuoted = preg_quote($classToken, '#');
                $html        = (string) preg_replace(
                    '#<img\b([^>]*\b' . $classQuoted . '[^>]*)\ssrc="' . $pngQuoted . '"([^>]*)>#i',
                    '<picture><source srcset="' . $webpQuoted . '" type="image/webp"><img$1 src="' . $png . '"$2></picture>',
                    $html,
                );
            } else {
                $html = (string) preg_replace(
                    '#<img\b([^>]*)\ssrc="' . $pngQuoted . '"([^>]*)>#i',
                    '<picture><source srcset="' . $webpQuoted . '" type="image/webp"><img$1 src="' . $png . '"$2></picture>',
                    $html,
                );
            }
        }

        return $html;
    }
}
