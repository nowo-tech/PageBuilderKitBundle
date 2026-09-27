<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use Nowo\PageBuilderKitBundle\Entity\BuilderPageTranslation;

use function is_string;
use function rtrim;
use function trim;

/**
 * Builds page-level SEO / Open Graph payload for public render.
 */
final class PageSeoBuilder
{
    public function __construct(
        private readonly string $siteName = '',
        private readonly string $defaultOgImage = '',
        private readonly string $canonicalBaseUrl = '',
        private readonly string $defaultRobots = 'index,follow',
    ) {
    }

    /**
     * @return array{
     *     title: string,
     *     description: string|null,
     *     canonical: string|null,
     *     robots: string|null,
     *     og: array{
     *         title: string,
     *         description: string|null,
     *         image: string|null,
     *         type: string,
     *         site_name: string|null,
     *         locale: string
     *     }
     * }
     */
    public function build(?BuilderPageTranslation $translation, string $pageKey, string $locale, string $fallbackTitle): array
    {
        $title     = $fallbackTitle;
        $metaTitle = $translation?->getMetaTitle();
        if (is_string($metaTitle) && trim($metaTitle) !== '') {
            $title = trim($metaTitle);
        }

        $description = $translation?->getMetaDescription();
        $description = is_string($description) && trim($description) !== '' ? trim($description) : null;

        $ogTitle = $translation?->getOgTitle();
        $ogTitle = is_string($ogTitle) && trim($ogTitle) !== '' ? trim($ogTitle) : $title;

        $ogDescription = $translation?->getOgDescription();
        $ogDescription = is_string($ogDescription) && trim($ogDescription) !== ''
            ? trim($ogDescription)
            : $description;

        $ogImage = $translation?->getOgImage();
        $ogImage = is_string($ogImage) && trim($ogImage) !== ''
            ? trim($ogImage)
            : ($this->defaultOgImage !== '' ? $this->defaultOgImage : null);

        $canonical = $translation?->getCanonicalUrl();
        if (!is_string($canonical) || trim($canonical) === '') {
            $canonical = $this->buildDefaultCanonical($translation?->getSlug() ?? $pageKey);
        } else {
            $canonical = trim($canonical);
        }

        $robots = $translation?->getRobots();
        $robots = is_string($robots) && trim($robots) !== ''
            ? trim($robots)
            : ($this->defaultRobots !== '' ? $this->defaultRobots : null);

        $siteName = $this->siteName !== '' ? $this->siteName : null;

        return [
            'title'       => $title,
            'description' => $description,
            'canonical'   => $canonical,
            'robots'      => $robots,
            'og'          => [
                'title'       => $ogTitle,
                'description' => $ogDescription,
                'image'       => $ogImage,
                'type'        => 'website',
                'site_name'   => $siteName,
                'locale'      => $locale,
            ],
        ];
    }

    private function buildDefaultCanonical(string $slug): ?string
    {
        $base = trim($this->canonicalBaseUrl);
        if ($base === '') {
            return null;
        }

        return rtrim($base, '/') . '/p/' . ltrim($slug, '/');
    }
}
