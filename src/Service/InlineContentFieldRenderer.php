<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Enum\ContentFieldType;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepositoryInterface;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessCheckerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

use function array_key_exists;
use function is_array;
use function is_string;
use function preg_match;
use function rtrim;

/**
 * Renders a multilingual content field for host Twig: value for everyone, pencil+modal when canContent().
 */
final readonly class InlineContentFieldRenderer implements InlineContentFieldRendererInterface
{
    public function __construct(
        private Environment $twig,
        private BuilderPageRepositoryInterface $pageRepository,
        private DocumentNormalizer $documentNormalizer,
        private ContentFieldsNormalizer $contentFieldsNormalizer,
        private BuilderLocales $builderLocales,
        private PageBuilderKitAccessCheckerInterface $accessChecker,
        private UrlGeneratorInterface $urlGenerator,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private RequestStack $requestStack,
        private string $assetBasePath = '/bundles/nowopagebuilderkit',
    ) {
    }

    /**
     * @param array{
     *     type?: string,
     *     label?: string,
     *     labels?: array<string, string>,
     *     locale?: string,
     *     default?: mixed,
     *     tag?: string,
     *     class?: string,
     *     options?: list<string>,
     *     required?: bool,
     *     editable?: bool
     * } $options
     */
    public function render(string $pageKey, string $fieldKey, array $options = []): string
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $fieldKey)) {
            return '';
        }

        $fallbackLocale = $this->builderLocales->getDefault();
        $locale         = is_string($options['locale'] ?? null) && $options['locale'] !== ''
            ? $options['locale']
            : $fallbackLocale;

        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if (!$page instanceof BuilderPage) {
            $labels = is_array($options['labels'] ?? null) ? $options['labels'] : [];
            $label  = $this->contentFieldsNormalizer->resolveLabel(
                [
                    'key'    => $fieldKey,
                    'label'  => is_string($options['label'] ?? null) ? $options['label'] : $fieldKey,
                    'labels' => $labels,
                ],
                $locale,
                $fallbackLocale,
            );

            return $this->twig->render('@NowoPageBuilderKitBundle/public/_editable_field.html.twig', $this->viewModel(
                pageKey: $pageKey,
                fieldKey: $fieldKey,
                type: ContentFieldType::tryFrom((string) ($options['type'] ?? 'string')) ?? ContentFieldType::String,
                label: $label,
                labels: $labels,
                locale: $locale,
                value: $options['default'] ?? '',
                options: $options,
                fieldOptions: [],
                editable: false,
                saveUrl: '',
            ));
        }

        $structure = $this->documentNormalizer->normalize($page->getDocument()?->getStructure() ?? []);
        $schema    = $this->contentFieldsNormalizer->normalizeSchema($structure['fields'] ?? []);
        $fieldDef  = [
            'key'    => $fieldKey,
            'label'  => '',
            'labels' => [],
        ];
        foreach ($schema as $field) {
            if ($field['key'] === $fieldKey) {
                $fieldDef = $field;
                break;
            }
        }

        $mergeDef = [];
        if (is_string($options['label'] ?? null)) {
            $mergeDef['label'] = $options['label'];
        }
        if (is_array($options['labels'] ?? null)) {
            /** @var array<string, string> $optLabels */
            $optLabels          = $options['labels'];
            $mergeDef['labels'] = $optLabels;
        }

        $fieldDef = $this->contentFieldsNormalizer->mergeLabels($fieldDef + [
            'type'     => ContentFieldType::String->value,
            'required' => false,
            'options'  => [],
            'default'  => null,
        ], $mergeDef !== [] ? $mergeDef : null, $locale);

        $type = ContentFieldType::tryFrom(
            is_string($options['type'] ?? null)
                ? $options['type']
                : ($fieldDef['type'] ?? ContentFieldType::String->value),
        ) ?? ContentFieldType::String;

        $label  = $this->contentFieldsNormalizer->resolveLabel($fieldDef, $locale, $fallbackLocale);
        $labels = is_array($fieldDef['labels'] ?? null) ? $fieldDef['labels'] : [];

        $resolved = $this->contentFieldsNormalizer->resolveForLocale($structure, $locale, $fallbackLocale);
        if (array_key_exists($fieldKey, $resolved)) {
            $value = $resolved[$fieldKey];
        } elseif (array_key_exists('default', $options)) {
            $value = $options['default'];
        } else {
            $value = $type === ContentFieldType::Bool ? false : '';
        }

        $editable = ($options['editable'] ?? true) !== false && $this->accessChecker->canContent();
        $saveUrl  = $editable
            ? $this->urlGenerator->generate('admin_page_builder_field_save', [
                'pageKey'  => $pageKey,
                'fieldKey' => $fieldKey,
            ])
            : '';

        return $this->twig->render('@NowoPageBuilderKitBundle/public/_editable_field.html.twig', $this->viewModel(
            pageKey: $pageKey,
            fieldKey: $fieldKey,
            type: $type,
            label: $label,
            labels: $labels,
            locale: $locale,
            value: $value,
            options: $options,
            fieldOptions: is_array($options['options'] ?? null)
                ? $options['options']
                : ($fieldDef['options'] ?? []),
            editable: $editable,
            saveUrl: $saveUrl,
        ));
    }

    /**
     * @param array<string, mixed> $options
     * @param list<string> $fieldOptions
     * @param array<string, string> $labels
     *
     * @return array<string, mixed>
     */
    private function viewModel(
        string $pageKey,
        string $fieldKey,
        ContentFieldType $type,
        string $label,
        array $labels,
        string $locale,
        mixed $value,
        array $options,
        array $fieldOptions,
        bool $editable,
        string $saveUrl,
    ): array {
        return [
            'page_key'       => $pageKey,
            'field_key'      => $fieldKey,
            'type'           => $type->value,
            'label'          => $label,
            'labels'         => $labels,
            'locale'         => $locale,
            'value'          => $value,
            'tag'            => is_string($options['tag'] ?? null) && $options['tag'] !== '' ? $options['tag'] : 'span',
            'attr_class'     => is_string($options['class'] ?? null) ? $options['class'] : '',
            'options'        => $fieldOptions,
            'required'       => (bool) ($options['required'] ?? false),
            'editable'       => $editable,
            'save_url'       => $saveUrl,
            'csrf_token'     => $editable ? $this->csrfTokenManager->getToken('page_builder_content')->getValue() : '',
            'include_assets' => $editable && $this->markAssetsOnce(),
            'asset_base'     => rtrim($this->assetBasePath, '/'),
            'is_html_output' => $type->isHtmlOutput(),
        ];
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
