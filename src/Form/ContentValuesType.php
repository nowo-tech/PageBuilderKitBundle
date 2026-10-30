<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Nowo\UiKitBundle\Twig\TranslatableText;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_is_list;
use function count;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;

/**
 * Dynamic content values editor for one locale.
 *
 * @extends AbstractType<array{action: string, fieldLabels: array<string, string>, fieldValues: array<string, mixed>}>
 */
#[FormKitConfig('page_builder_kit')]
final class ContentValuesType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<array<string, mixed>> $schema */
        $schema = $options['schema'];
        /** @var string $locale */
        $locale = $options['locale'];
        /** @var array<string, mixed> $values */
        $values = $options['values'];
        /** @var list<string> $referencePages */
        $referencePages = $options['reference_pages'];

        $this->addWithDefaults($builder, 'action', HiddenType::class, [
            'data' => 'save_values',
        ]);

        $labelsForm = $builder->create('fieldLabels', FormType::class, [
            'compound' => true,
            'label'    => false,
        ]);
        $localeLabels = $builder->create($locale, FormType::class, [
            'compound' => true,
            'label'    => false,
        ]);

        $valuesForm = $builder->create('fieldValues', FormType::class, [
            'compound' => true,
            'label'    => false,
        ]);
        $localeValues = $builder->create($locale, FormType::class, [
            'compound' => true,
            'label'    => false,
        ]);

        foreach ($schema as $field) {
            $key = is_string($field['key'] ?? null) ? $field['key'] : '';
            if ($key === '') {
                continue;
            }
            $type         = is_string($field['type'] ?? null) ? $field['type'] : 'string';
            $currentLabel = '';
            if (is_array($field['labels'] ?? null) && is_string($field['labels'][$locale] ?? null)) {
                $currentLabel = $field['labels'][$locale];
            } elseif (is_string($field['display_label'] ?? null)) {
                $currentLabel = $field['display_label'];
            } elseif (is_string($field['label'] ?? null)) {
                $currentLabel = $field['label'];
            }

            $this->addWithDefaults($localeLabels, $key, TextType::class, [
                'label'    => 'admin.content.field_label_locale',
                'required' => false,
                'data'     => $currentLabel,
                'attr'     => ['placeholder' => new TranslatableText($key)],
            ]);

            $current = $values[$key] ?? ($field['default'] ?? ($type === 'bool' ? false : ''));
            $this->addValueField($localeValues, $key, $type, $field, $current, $referencePages);
        }

        $labelsForm->add($localeLabels);
        $valuesForm->add($localeValues);
        $builder->add($labelsForm);
        $builder->add($valuesForm);
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed> $field
     * @param list<string> $referencePages
     */
    private function addValueField(
        FormBuilderInterface $builder,
        string $key,
        string $type,
        array $field,
        mixed $current,
        array $referencePages,
    ): void {
        // Editor-defined label: printed as given (never looked up in the bundle catalogue).
        $label = new TranslatableText(is_string($field['display_label'] ?? null)
            ? $field['display_label']
            : (is_string($field['label'] ?? null) ? $field['label'] : $key));

        if ($type === 'bool') {
            $this->addWithDefaults($builder, $key, CheckboxType::class, [
                'label'    => $label,
                'required' => false,
                'data'     => (bool) $current,
            ]);

            return;
        }

        if ($type === 'select') {
            /** @var list<string> $opts */
            $opts = is_array($field['options'] ?? null) ? array_values(array_filter(
                $field['options'],
                is_string(...),
            )) : [];
            $this->addWithDefaults($builder, $key, ChoiceType::class, [
                'label'                     => $label,
                'choices'                   => array_combine($opts, $opts),
                'choice_translation_domain' => false,
                'data'                      => is_string($current) ? $current : null,
            ]);

            return;
        }

        if ($type === 'reference') {
            $choices = array_combine($referencePages, $referencePages);
            $this->addWithDefaults($builder, $key, ChoiceType::class, [
                'label'                     => $label,
                'choices'                   => $choices,
                'choice_translation_domain' => false,
                'required'                  => false,
                'placeholder'               => 'admin.content.reference_none',
                'data'                      => is_string($current) && $current !== '' ? $current : null,
            ]);

            return;
        }

        if ($type === 'group') {
            $group = $builder->create($key, FormType::class, [
                'compound' => true,
                'label'    => $label,
            ]);
            $subCurrent = is_array($current) ? $current : [];
            foreach (is_array($field['fields'] ?? null) ? $field['fields'] : [] as $sub) {
                if (!is_array($sub) || !is_string($sub['key'] ?? null)) {
                    continue;
                }
                $this->addValueField(
                    $group,
                    $sub['key'],
                    is_string($sub['type'] ?? null) ? $sub['type'] : 'string',
                    $sub,
                    $subCurrent[$sub['key']] ?? '',
                    $referencePages,
                );
            }
            $builder->add($group);

            return;
        }

        if ($type === 'repeater') {
            $rows = [];
            if (is_array($current) && array_is_list($current)) {
                $rows = $current;
            }
            $slots    = count($rows) + 1;
            $repeater = $builder->create($key, FormType::class, [
                'compound' => true,
                'label'    => $label,
            ]);
            for ($i = 0; $i < $slots; ++$i) {
                $row     = is_array($rows[$i] ?? null) ? $rows[$i] : [];
                $rowForm = $repeater->create((string) $i, FormType::class, [
                    'compound' => true,
                    'label'    => false,
                ]);
                foreach (is_array($field['fields'] ?? null) ? $field['fields'] : [] as $sub) {
                    if (!is_array($sub) || !is_string($sub['key'] ?? null)) {
                        continue;
                    }
                    $this->addValueField(
                        $rowForm,
                        $sub['key'],
                        is_string($sub['type'] ?? null) ? $sub['type'] : 'string',
                        $sub,
                        $row[$sub['key']] ?? ($sub['type'] === 'group' ? [] : ''),
                        $referencePages,
                    );
                }
                $repeater->add($rowForm);
            }
            $builder->add($repeater);

            return;
        }

        if (in_array($type, ['text', 'richtext', 'html', 'raw'], true)) {
            $this->addWithDefaults($builder, $key, TextareaType::class, [
                'label'    => $label,
                'required' => false,
                'data'     => is_string($current) ? $current : (string) $current,
                'attr'     => ['rows' => 4],
            ]);

            return;
        }

        if ($type === 'url' || $type === 'image') {
            $this->addWithDefaults($builder, $key, UrlType::class, [
                'label'    => $label,
                'required' => false,
                'data'     => is_string($current) ? $current : '',
                'attr'     => [
                    'id'          => 'field-' . $key,
                    'placeholder' => new TranslatableText($type === 'image' ? 'https://…' : ''),
                ],
                'default_protocol' => 'https',
            ]);

            return;
        }

        if ($type === 'number') {
            $this->addWithDefaults($builder, $key, NumberType::class, [
                'label'    => $label,
                'required' => false,
                'data'     => is_numeric($current) ? $current + 0 : null,
            ]);

            return;
        }

        $this->addWithDefaults($builder, $key, TextType::class, [
            'label'    => $label,
            'required' => false,
            'data'     => is_string($current) ? $current : (string) $current,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection'    => true,
            'csrf_field_name'    => '_csrf_token',
            'csrf_token_id'      => 'page_builder_content',
            'translation_domain' => 'NowoPageBuilderKitBundle',
            'schema'             => [],
            'locale'             => 'en',
            'values'             => [],
            'reference_pages'    => [],
        ]);
        $resolver->setAllowedTypes('schema', 'array');
        $resolver->setAllowedTypes('locale', 'string');
        $resolver->setAllowedTypes('values', 'array');
        $resolver->setAllowedTypes('reference_pages', 'array');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
