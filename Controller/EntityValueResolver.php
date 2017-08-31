<?php

declare(strict_types=1);

namespace Goat\Bundle\Controller;

use Goat\Bundle\Mapper\MapperRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class EntityValueResolver implements ArgumentValueResolverInterface
{
    /**
     * @var MapperRegistry
     */
    private $mapperRegistry;

    /**
     * Default constructor
     *
     * @param MapperRegistry $mapperRegistry
     */
    public function __construct(MapperRegistry $mapperRegistry)
    {
        $this->mapperRegistry = $mapperRegistry;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        $mapper = $this->mapperRegistry->getMapper($argument->getType());

        if ($mapper->getPrimaryKeyCount()) {
            yield $mapper->findOne($request->get($argument->getName()));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request, ArgumentMetadata $argument)
    {
        return ($type = $argument->getType()) && $this->mapperRegistry->isEntityClassSupported($type);
    }
}
