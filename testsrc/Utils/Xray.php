<?php

namespace Prezly\KubernetesClient\Tests\Utils;

use ReflectionClass;

final class Xray
{
    /**
     * Get a private or protected property from an instance.
     *
     * @param object $instance
     * @param string $name
     * @return mixed
     * @throws \ReflectionException
     */
    public static function property(object $instance, string $name)
    {
        if (strpos($name, '.') !== false) {
            foreach (explode('.', $name) as $part) {
                $instance = self::property($instance, $part);
            }
            return $instance;
        }

        $class = new ReflectionClass($instance);
        $property = $class->getProperty($name);

        $property->setAccessible(true);

        $value = $property->getValue($instance);

        $property->setAccessible(false);

        return $value;
    }
}
