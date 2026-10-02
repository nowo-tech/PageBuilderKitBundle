<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use Throwable;
use Twig\Environment;
use Twig\Extension\SandboxExtension;
use Twig\Loader\ArrayLoader;
use Twig\Sandbox\SecurityPolicy;
use Twig\Sandbox\SecurityPolicyInterface;

use function str_contains;

/**
 * Renders GrapesJS HTML as a sandboxed Twig template so editors can use {{ variables }}.
 */
final readonly class GrapesTwigRenderer
{
    public function __construct(
        private bool $enabled = true,
        private bool $strictVariables = false,
        private GrapesDocumentSanitizer $sanitizer = new GrapesDocumentSanitizer(),
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array{html: string, twigApplied: bool, twigError: string|null}
     */
    public function render(string $html, array $context): array
    {
        $html = $this->sanitizer->sanitizeHtml($html);

        if (!$this->enabled || $html === '' || !$this->looksLikeTwig($html)) {
            return [
                'html'        => $html,
                'twigApplied' => false,
                'twigError'   => null,
            ];
        }

        try {
            $twig = new Environment(new ArrayLoader(), [
                'autoescape'       => 'html',
                'strict_variables' => $this->strictVariables,
                'cache'            => false,
            ]);
            $twig->addExtension(new SandboxExtension($this->securityPolicy(), true));
            $template = $twig->createTemplate($html, 'pbk_grapes');
            $rendered = $template->render($context);

            return [
                'html'        => $this->sanitizer->sanitizeHtml($rendered),
                'twigApplied' => true,
                'twigError'   => null,
            ];
        } catch (Throwable $exception) {
            return [
                'html'        => $html,
                'twigApplied' => false,
                'twigError'   => $exception->getMessage(),
            ];
        }
    }

    /**
     * Variable names advertised in the GrapesJS Block Manager.
     *
     * @return list<array{name: string, sample: string, label: string}>
     */
    public function catalogVariables(): array
    {
        return [
            ['name' => 'title', 'sample' => '{{ title }}', 'label' => 'Page title'],
            ['name' => 'slug', 'sample' => '{{ slug }}', 'label' => 'Page slug'],
            ['name' => 'pageKey', 'sample' => '{{ pageKey }}', 'label' => 'Page key'],
            ['name' => 'locale', 'sample' => '{{ locale }}', 'label' => 'Locale'],
            ['name' => 'status', 'sample' => '{{ status }}', 'label' => 'Status'],
            ['name' => 'page.title', 'sample' => '{{ page.title }}', 'label' => 'page.title'],
            ['name' => 'page.locale', 'sample' => '{{ page.locale }}', 'label' => 'page.locale'],
            ['name' => 'fields.slot', 'sample' => '[[fields.hero_title]]', 'label' => 'Field slot (no Twig)'],
            ['name' => 'fields.twig', 'sample' => '{{ fields.hero_title }}', 'label' => 'Field (Twig)'],
            ['name' => 'products.loop', 'sample' => '{% for p in products %}<li>{{ p.name }} — {{ p.price }}</li>{% endfor %}', 'label' => 'for products'],
            ['name' => 'highlights.loop', 'sample' => '{% for item in highlights %}<span>{{ item }}</span>{% endfor %}', 'label' => 'for highlights'],
        ];
    }

    private function looksLikeTwig(string $html): bool
    {
        return str_contains($html, '{{')
            || str_contains($html, '{%')
            || str_contains($html, '{#');
    }

    private function securityPolicy(): SecurityPolicyInterface
    {
        return new SecurityPolicy(
            ['if', 'for', 'set'],
            [
                'escape', 'e', 'length', 'default', 'abs', 'capitalize', 'title', 'upper', 'lower',
                'trim', 'nl2br', 'join', 'split', 'first', 'last', 'slice', 'keys',
                'date', 'format', 'replace', 'merge', 'striptags', 'number_format',
            ],
            [],
            [],
            ['min', 'max', 'range'],
        );
    }
}
