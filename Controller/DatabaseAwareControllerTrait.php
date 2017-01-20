<?php

declare(strict_types=1);

namespace Goat\Bundle\Controller;

use Goat\Core\Client\ConnectionInterface;
use Goat\Mapper\MapperInterface;

/**
 * Provide a set of helpers for manipulating database from controllers
 */
trait DatabaseAwareControllerTrait
{
    /**
     * Get service
     *
     * @param string $id
     *
     * @return object
     */
    abstract protected function get($id);

    /**
     * Find mapper
     *
     * @param string $name
     *   Either a mapper name
     *
     * @throws MapperFoundError
     *   If the mapper does not exists
     *
     * @return MapperInterface
     */
    protected function getMapper(string $name) : MapperInterface
    {
        return $this->get('goat.mapper_registry')->getMapper($name);
    }

    /**
     * Get database connection
     *
     * @return ConnectionInterface
     */
    protected function getDatabase() : ConnectionInterface
    {
        return $this->get('goat.session');
    }
}
