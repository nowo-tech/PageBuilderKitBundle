<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function is_array;
use function is_string;
use function strlen;

/**
 * Classic sections editor: nested widgets[widgetId][field] + CSRF `_token`.
 *
 * @extends AbstractType<array{widgets?: array<string, array<string, string>>}>
 */
final class SectionsEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<array{id: string, columns: list<array{widgets: list<array{id: string, fields: list<string>, props: array<string, mixed>}>}>}> $tree */
        $tree = $options['sections_tree'];

        $widgets = $builder->create('widgets', FormType::class, [
            'compound' => true,
            'label'    => false,
        ]);

        foreach ($tree as $section) {
            if (!is_array($section['columns'] ?? null)) {
                continue;
            }
            foreach ($section['columns'] as $column) {
                if (!is_array($column['widgets'] ?? null)) {
                    continue;
                }
                foreach ($column['widgets'] as $widget) {
                    $widgetId = is_string($widget['id'] ?? null) ? $widget['id'] : '';
                    if ($widgetId === '') {
                        continue;
                    }
                    $fields = is_array($widget['fields'] ?? null) ? $widget['fields'] : [];
                    $props  = is_array($widget['props'] ?? null) ? $widget['props'] : [];

                    $widgetForm = $builder->create($widgetId, FormType::class, [
                        'compound' => true,
                        'label'    => false,
                    ]);

                    foreach ($fields as $field) {
                        if (!is_string($field) || $field === '') {
                            continue;
                        }
                        $value = $props[$field] ?? '';
                        $str   = is_string($value) ? $value : (string) $value;
                        $type  = ($field === 'html' || $field === 'alt' || strlen($str) > 80)
                            ? TextareaType::class
                            : TextType::class;
                        $widgetForm->add($field, $type, [
                            'label'    => $field,
                            'required' => false,
                            'data'     => $str,
                            'attr'     => $type === TextareaType::class
                                ? ['class' => 'form-control form-control-sm', 'rows' => 3]
                                : ['class' => 'form-control form-control-sm'],
                        ]);
                    }

                    $widgets->add($widgetForm);
                }
            }
        }

        $builder->add($widgets);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id'   => 'page_builder_sections',
            'sections_tree'   => [],
        ]);
        $resolver->setAllowedTypes('sections_tree', 'array');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
