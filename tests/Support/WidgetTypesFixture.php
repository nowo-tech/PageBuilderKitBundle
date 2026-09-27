<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Support;

use Nowo\PageBuilderKitBundle\Widget\Type\ButtonWidgetType;
use Nowo\PageBuilderKitBundle\Widget\Type\ContainerWidgetType;
use Nowo\PageBuilderKitBundle\Widget\Type\HeadingWidgetType;
use Nowo\PageBuilderKitBundle\Widget\Type\HtmlWidgetType;
use Nowo\PageBuilderKitBundle\Widget\Type\ImageWidgetType;
use Nowo\PageBuilderKitBundle\Widget\Type\SpacerWidgetType;
use Nowo\PageBuilderKitBundle\Widget\Type\TextWidgetType;
use Nowo\PageBuilderKitBundle\Widget\WidgetTypeRegistry;

final class WidgetTypesFixture
{
    public static function registry(): WidgetTypeRegistry
    {
        return new WidgetTypeRegistry([
            new HeadingWidgetType(),
            new ContainerWidgetType(),
            new ButtonWidgetType(),
            new TextWidgetType(),
            new ImageWidgetType(),
            new SpacerWidgetType(),
            new HtmlWidgetType(),
        ]);
    }
}
