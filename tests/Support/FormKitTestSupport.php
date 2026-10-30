<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Support;

use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\PageBuilderKitBundle\DependencyInjection\NowoPageBuilderKitExtension;
use Nowo\PageBuilderKitBundle\Form\BuilderPageCreateType;
use Nowo\PageBuilderKitBundle\Form\BuilderPageSeoType;
use Nowo\PageBuilderKitBundle\Form\ContentSchemaAddType;
use Nowo\PageBuilderKitBundle\Form\ContentSchemaRemoveType;
use Nowo\PageBuilderKitBundle\Form\ContentValuesType;
use Nowo\PageBuilderKitBundle\Form\CsrfPostType;
use Nowo\PageBuilderKitBundle\Form\InlineFieldModalType;
use Nowo\PageBuilderKitBundle\Form\PageDuplicateType;
use Nowo\PageBuilderKitBundle\Form\RevisionCreateType;
use Nowo\PageBuilderKitBundle\Form\SectionsEditType;
use Nowo\PageBuilderKitBundle\Form\TemplateApplyType;
use Nowo\PageBuilderKitBundle\Form\TemplateImportType;
use Nowo\PageBuilderKitBundle\Form\TemplateSaveType;
use Symfony\Component\Form\PreloadedExtension;

/**
 * FormKit wiring for form factories built outside the container (unit tests, page contract fixtures): every page form
 * type with a {@see FormOptionsMerger} on the bundle profile, as the FormKit compiler pass does in an application.
 */
final class FormKitTestSupport
{
    /** @var list<class-string> */
    public const array TYPES = [
        BuilderPageCreateType::class,
        BuilderPageSeoType::class,
        ContentSchemaAddType::class,
        ContentSchemaRemoveType::class,
        ContentValuesType::class,
        CsrfPostType::class,
        InlineFieldModalType::class,
        PageDuplicateType::class,
        RevisionCreateType::class,
        SectionsEditType::class,
        TemplateApplyType::class,
        TemplateImportType::class,
        TemplateSaveType::class,
    ];

    public static function merger(): FormOptionsMerger
    {
        $profile = NowoPageBuilderKitExtension::FORM_KIT_PROFILE_CONFIG;
        $profile['defaults'] += ['row_attr' => [], 'help_attr' => []];
        $profile['by_form'] = [];

        return new FormOptionsMerger(
            [NowoPageBuilderKitExtension::FORM_KIT_PROFILE => $profile],
            NowoPageBuilderKitExtension::FORM_KIT_PROFILE,
            new ConstraintDefinitionFactory(),
        );
    }

    public static function extension(): PreloadedExtension
    {
        $merger = self::merger();
        $types  = [
            new BuilderPageCreateType(),
            new BuilderPageSeoType(),
            new ContentSchemaAddType(),
            new ContentSchemaRemoveType(),
            new ContentValuesType(),
            new CsrfPostType(),
            new InlineFieldModalType(),
            new PageDuplicateType(),
            new RevisionCreateType(),
            new SectionsEditType(),
            new TemplateApplyType(),
            new TemplateImportType(),
            new TemplateSaveType(),
        ];
        foreach ($types as $type) {
            $type->setFormOptionsMerger($merger);
        }

        return new PreloadedExtension($types, []);
    }
}
