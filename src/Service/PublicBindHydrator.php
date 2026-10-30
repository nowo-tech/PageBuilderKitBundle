<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use Dom\Element;
use Dom\HTMLDocument;
use Nowo\PageBuilderKitBundle\Content\ContentFieldDefinitionProviderInterface;
use Nowo\PageBuilderKitBundle\Enum\ContentFieldType;
use Nowo\PageBuilderKitBundle\Form\InlineFieldModalType;
use Nowo\PageBuilderKitBundle\Html\PublicHtmlHardener;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessCheckerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

use function bin2hex;
use function count;
use function preg_match;
use function random_bytes;
use function rtrim;
use function sprintf;
use function str_contains;
use function strtr;

use const LIBXML_NOERROR;

/**
 * Public Grapes bind slots: keep already-resolved HTML for visitors and wrap the slot content
 * with the inline-edit pencil/modal when the user holds the `content` capability. The input is always passed
 * through {@see PublicHtmlHardener} and the slots are found on the HTML5 tree (never by regex), so the output is
 * safe to print unescaped.
 *
 * No page SELECT: the value is the slot inner HTML; types/labels come from
 * {@see ContentFieldDefinitionProviderInterface}.
 */
final readonly class PublicBindHydrator implements PublicBindHydratorInterface
{
    public function __construct(
        private Environment $twig,
        private PageBuilderKitAccessCheckerInterface $accessChecker,
        private UrlGeneratorInterface $urlGenerator,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private RequestStack $requestStack,
        private FormFactoryInterface $formFactory,
        private TranslatorInterface $translator,
        private ContentFieldDefinitionProviderInterface $definitionProvider,
        private BuilderLocales $builderLocales,
        private string $assetBasePath = '/bundles/nowopagebuilderkit',
        private PublicHtmlHardener $hardener = new PublicHtmlHardener(),
    ) {
    }

    public function hydrate(string $html, string $pageKey): string
    {
        // The function output is printed without escaping (`is_safe`): always pass it through the HTML5 gate, so it is
        // safe whatever the caller hands in (idempotent for page_tree.html, which is already hardened).
        $html = $this->hardener->harden($html);
        if ($html === '' || !str_contains($html, 'data-pbk-bind') || !$this->accessChecker->canContent()) {
            return $html;
        }

        $document = HTMLDocument::createFromString(
            '<!DOCTYPE html><html><head></head><body>' . $html . '</body></html>',
            LIBXML_NOERROR,
            'UTF-8',
        );
        $body = $document->body;
        if (!$body instanceof Element) {
            return $html; // @codeCoverageIgnore
        }

        // Never regex the markup (a `</span>` inside an attribute or a comment would end the slot early): every bind
        // slot is an element of the HTML5 tree; it is replaced by an unguessable text marker, the tree is serialised,
        // then each marker becomes the rendered editable field.
        $nonce = bin2hex(random_bytes(12));
        $slots = [];
        foreach ($body->querySelectorAll('span[data-pbk-bind]') as $element) {
            $key = (string) $element->getAttribute('data-pbk-bind');
            if (preg_match('/^[a-zA-Z0-9_]+$/', $key) !== 1 || $element->parentElement?->closest('span[data-pbk-bind]') instanceof Element) {
                continue;
            }
            $marker         = sprintf('pbk-bind-%s-%d', $nonce, count($slots));
            $slots[$marker] = ['key' => $key, 'html' => $element->innerHTML, 'text' => $element->textContent ?? ''];
            $element->replaceWith($document->createTextNode($marker));
        }

        if ($slots === []) {
            return $html;
        }

        $types  = [];
        $labels = [];
        foreach ($this->definitionProvider->definitions($pageKey) as $field) {
            $types[$field['key']]  = $field['type'];
            $labels[$field['key']] = $field['label'];
        }
        $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? $this->builderLocales->getDefault();

        $replacements = [];
        foreach ($slots as $marker => $slot) {
            $replacements[$marker] = $this->renderField($pageKey, $slot['key'], $slot['html'], $slot['text'], $types, $labels, $locale);
        }

        return strtr($body->innerHTML, $replacements);
    }

    /**
     * @param array<string, string> $types
     * @param array<string, string> $labels
     */
    private function renderField(string $pageKey, string $key, string $innerHtml, string $text, array $types, array $labels, string $locale): string
    {
        $type      = ContentFieldType::tryFrom($types[$key] ?? 'string') ?? ContentFieldType::String;
        $catalogue = $labels[$key] ?? $key;
        $label     = $this->translator->trans($catalogue);
        if ($label === '') {
            $label = $key;
        }

        $assets = $this->markAssetsOnce();

        return $this->twig->render('@NowoPageBuilderKitBundle/public/_editable_field.html.twig', [
            'page_key'  => $pageKey,
            'field_key' => $key,
            'type'      => $type->value,
            'label'     => $label,
            'labels'    => [],
            'locale'    => $locale,
            // HTML fields keep the (hardened) slot markup, re-hardened by the partial; others take the text.
            'value'      => $type->isHtmlOutput() ? $innerHtml : $text,
            'tag'        => 'span',
            'attr_class' => '',
            'options'    => [],
            'required'   => false,
            'editable'   => true,
            'save_url'   => $this->urlGenerator->generate('admin_page_builder_field_save', [
                'pageKey'  => $pageKey,
                'fieldKey' => $key,
            ]),
            'csrf_token'     => $this->csrfTokenManager->getToken('page_builder_content')->getValue(),
            'include_assets' => $assets,
            'asset_base'     => rtrim($this->assetBasePath, '/'),
            'is_html_output' => $type->isHtmlOutput(),
            'modal_form'     => $assets
                ? $this->formFactory->create(InlineFieldModalType::class)->createView()
                : null,
        ]);
    }

    private function markAssetsOnce(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return true;
        }

        if ($request->attributes->getBoolean('_pbk_inline_edit_assets')) {
            return false;
        }

        // @igor-ignore - Request attributes bag is request-scoped (not worker-shared service state).
        $request->attributes->set('_pbk_inline_edit_assets', true);

        return true;
    }
}
