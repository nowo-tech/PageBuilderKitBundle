<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use Nowo\PageBuilderKitBundle\Content\ContentFieldDefinitionProviderInterface;
use Nowo\PageBuilderKitBundle\Enum\ContentFieldType;
use Nowo\PageBuilderKitBundle\Form\InlineFieldModalType;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessCheckerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

use function is_string;
use function preg_replace_callback;
use function rtrim;
use function str_contains;

/**
 * Public Grapes bind slots: keep already-resolved HTML for visitors and wrap the inner HTML
 * with the inline-edit pencil/modal when the user holds the `content` capability.
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
    ) {
    }

    public function hydrate(string $html, string $pageKey): string
    {
        if ($html === '' || !str_contains($html, 'data-pbk-bind=')) {
            return $html;
        }

        if (!$this->accessChecker->canContent()) {
            return $html;
        }

        $types  = [];
        $labels = [];
        foreach ($this->definitionProvider->definitions($pageKey) as $field) {
            $types[$field['key']]  = $field['type'];
            $labels[$field['key']] = $field['label'];
        }

        $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? $this->builderLocales->getDefault();

        $replaced = preg_replace_callback(
            '/<span\s+data-pbk-bind="([a-zA-Z0-9_]+)"[^>]*>(.*?)<\/span>/is',
            function (array $matches) use ($pageKey, $types, $labels, $locale): string {
                $key       = $matches[1];
                $type      = ContentFieldType::tryFrom($types[$key] ?? 'string') ?? ContentFieldType::String;
                $catalogue = $labels[$key] ?? $key;
                $label     = $this->translator->trans($catalogue);
                if ($label === '') {
                    $label = $key;
                }

                $assets = $this->markAssetsOnce();

                return $this->twig->render('@NowoPageBuilderKitBundle/public/_editable_field.html.twig', [
                    'page_key'   => $pageKey,
                    'field_key'  => $key,
                    'type'       => $type->value,
                    'label'      => $label,
                    'labels'     => [],
                    'locale'     => $locale,
                    'value'      => $matches[2],
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
            },
            $html,
        );

        return is_string($replaced) ? $replaced : $html;
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
