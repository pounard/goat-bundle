<?php

declare(strict_types=1);

namespace Goat\Bundle\Mapper;

use Goat\Mapper\MapperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Container mapper registry
 */
final class MapperRegistry
{
    /**
     * Default/none namespace
     */
    const NAMESPACE_DEFAULT = 'default';

    /**
     * Service container
     *
     * @var ContainerInterface
     */
    private $container;

    /**
     * By name index, keys are "namespace:name" formated names
     *
     * @var string[]
     */
    private $serviceIndex;

    /**
     * By entity class index, keys are class names
     *
     * @var string[]
     */
    private $classIndex;

    /**
     * Default constructor
     */
    public function __construct(ContainerInterface $container, array $serviceIndex, array $classIndex)
    {
        $this->container = $container;
        $this->serviceIndex = $serviceIndex;
        $this->classIndex = $classIndex;
    }

    /**
     * Find mapper
     *
     * @param string $name
     *   Either a mapper name
     *
     * @throws MapperNotFoundError
     *   If the mapper does not exists
     *
     * @return MapperInterface
     */
    public function getMapper(string $name) : MapperInterface
    {
        if (!$this->container) {
            throw new MapperNotFoundError("container is not defined");
        }

        $pos = strpos($name, ':');

        // Can't start with namespace separator
        if (0 === $pos) {
            throw new MapperNotFoundError(sprintf("invalid name, must be of the form 'NAMESPACE:ENTITY' or 'ENTITY': '%s' given", $name));
        }

        if (false !== $pos) {
            // Can't have more than one namespace separator
            if (strrpos($name, ':') !== $pos) {
                throw new MapperNotFoundError(sprintf("invalid name, must be of the form 'NAMESPACE:ENTITY' or 'ENTITY': '%s' given", $name));
            }
        } else {
            $name = sprintf("%s:%s", self::NAMESPACE_DEFAULT, $name);
        }

        $serviceId = $this->serviceIndex[$name] ?? $this->classIndex[$name] ?? null;

        if (!$serviceId) {
            throw new MapperNotFoundError(sprintf("mapper does not exists: '%s'", $name));
        }

        return $this->container->get($serviceId);
    }
}
