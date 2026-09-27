<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Twig;

use Nowo\PageBuilderKitBundle\Service\ElementAppearanceNormalizer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use function is_array;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

final class ElementAppearanceExtension extends AbstractExtension
{
    public function __construct(
        private readonly ElementAppearanceNormalizer $appearanceNormalizer,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nowo_pbk_element_attrs', $this->elementAttrs(...), ['is_safe' => ['html']]),
            new TwigFunction('nowo_pbk_element_attr_map', $this->elementAttrMap(...)),
        ];
    }

    /**
     * @param array<string, mixed>|null $appearance
     * @param list<string> $extraClasses
     */
    public function elementAttrs(?array $appearance, array $extraClasses = []): string
    {
        $map  = $this->elementAttrMap($appearance, $extraClasses);
        $html = [];
        foreach ($map as $name => $value) {
            $html[] = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '="'
                . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '"';
        }

        return implode(' ', $html);
    }

    /**
     * @param array<string, mixed>|null $appearance
     * @param list<string> $extraClasses
     *
     * @return array<string, string>
     */
    public function elementAttrMap(?array $appearance, array $extraClasses = []): array
    {
        $normalized = $this->appearanceNormalizer->normalize(is_array($appearance) ? $appearance : []);
        $attrs      = $this->appearanceNormalizer->toHtmlAttributes($normalized);

        if ($extraClasses !== []) {
            $existing       = $attrs['class'] ?? 'pbk-element';
            $attrs['class'] = trim($existing . ' ' . implode(' ', $extraClasses));
        }

        return $attrs;
    }
}
