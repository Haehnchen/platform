<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseHelper;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class ReflectionHelper
{
    public static function getMethod(string $className, string $methodName): ReflectionMethod
    {
        $method = (new ReflectionClass($className))->getMethod($methodName);
        $method->setAccessible(true);

        return $method;
    }

    public static function getProperty(string $className, string $propertyName): ReflectionProperty
    {
        $property = (new ReflectionClass($className))->getProperty($propertyName);
        $property->setAccessible(true);

        return $property;
    }
}
