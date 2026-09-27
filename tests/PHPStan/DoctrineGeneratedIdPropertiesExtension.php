<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\PHPStan;

use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use PHPStan\Reflection\ExtendedPropertyReflection;
use PHPStan\Rules\Properties\ReadWritePropertiesExtension;

/**
 * Tells PHPStan that Doctrine writes `#[ORM\Id] #[ORM\GeneratedValue]` properties on flush.
 */
final class DoctrineGeneratedIdPropertiesExtension implements ReadWritePropertiesExtension
{
    public function isAlwaysRead(ExtendedPropertyReflection $property, string $propertyName): bool
    {
        return false;
    }

    public function isAlwaysWritten(ExtendedPropertyReflection $property, string $propertyName): bool
    {
        return $this->isGeneratedId($property, $propertyName);
    }

    public function isInitialized(ExtendedPropertyReflection $property, string $propertyName): bool
    {
        return false;
    }

    private function isGeneratedId(ExtendedPropertyReflection $property, string $propertyName): bool
    {
        $nativeClass = $property->getDeclaringClass()->getNativeReflection();
        if (!$nativeClass->hasProperty($propertyName)) {
            return false;
        }

        $nativeProperty = $nativeClass->getProperty($propertyName);

        return $nativeProperty->getAttributes(Id::class) !== []
            && $nativeProperty->getAttributes(GeneratedValue::class) !== [];
    }
}
