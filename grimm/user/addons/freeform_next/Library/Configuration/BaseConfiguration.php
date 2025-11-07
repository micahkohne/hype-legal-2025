<?php

namespace Solspace\Addons\FreeformNext\Library\Configuration;

use Stringable;
use ReflectionException;
use ReflectionClass;
use ReflectionProperty;
use Solspace\Addons\FreeformNext\Library\Exceptions\FreeformException;

abstract class BaseConfiguration implements Stringable
{
    /**
     * BaseConfiguration constructor.
     * Passing an array config populates all of the configuration values for a given configuration
     *
     *
     * @throws FreeformException
     * @throws ReflectionException
     */
    public function __construct(?array $config = null)
    {
        if (null === $config) {
            return;
        }

        foreach ($config as $key => $value) {
            if (property_exists(static::class, $key)) {
                $this->$key = $value;
            } else {
                $reflection = new ReflectionClass($this);
                $properties = $reflection->getProperties(
                    ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED
                );

                $availableProperties = [];
                foreach ($properties as $property) {
                    $availableProperties[] = $property->getName();
                }

                throw new FreeformException(
                    sprintf(
                        'Configuration property "%s" does not exist. Available properties are: "%s"',
                        $key,
                        implode(', ', $availableProperties)
                    )
                );
            }
        }
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->getConfigHash();
    }

    /**
     * Returns the SHA1 hash of the serialized object
     *
     * @return string
     */
    public function getConfigHash()
    {
        return sha1(serialize($this));
    }

    /**
     * @param bool  $nullable
     * @return int|null
     */
    protected function castToInt(mixed $value, $nullable = true)
    {
        if (null === $value && $nullable) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param bool  $nullable
     * @return string|null
     */
    protected function castToString(mixed $value, $nullable = true)
    {
        if (null === $value && $nullable) {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param bool  $nullable
     * @return bool|null
     */
    protected function castToBool(mixed $value, $nullable = true)
    {
        if (null === $value && $nullable) {
            return null;
        }

        return (bool) $value;
    }

    /**
     * @param bool  $nullable
     * @return array|null
     */
    protected function castToArray(mixed $value, $nullable = true)
    {
        if (null === $value) {
            return $nullable ? null : [];
        }

        if (!\is_array($value)) {
            return '' === $value ? [] : [$value];
        }

        return $value;
    }
}
