<?php

namespace Tests\Concerns;

trait AccessesProtectedProperties
{
    protected function getProtectedProperty(object $object, string $property)
    {
        $reflection = new \ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }
}
