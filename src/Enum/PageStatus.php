<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Enum;

enum PageStatus: string
{
    case Draft     = 'draft';
    case Published = 'published';
}
