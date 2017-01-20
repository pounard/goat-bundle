<?php

namespace Goat\Bundle\DependencyInjection\Compiler;

use Goat\Mapper\MapperInterface;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RegisterMapperCompilerPass implements CompilerPassInterface
{
    use RegisterCompilerPassTrait;

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $classIndex = [];
        $serviceIndex = [];

        $idList = $this->collectTaggedServicesTags($container, 'goat.mapper', MapperInterface::class);
        foreach ($idList as $id => $attributes) {
            if (!isset($attributes['alias'])) {
                throw new \LogicException(sprintf("tag '%s' on service '%s' must have the 'alias' attribute", 'goat.mapper', $id));
            }
            $serviceIndex[$attributes['alias']] = $id;
            if (isset($attributes['class'])) {
                $classIndex[$attributes['class']] = $id;
            }
        }

        if ($classIndex && $container->hasParameter('goat.mapper.class_index')) {
            $index = $container->getParameter('goat.mapper.class_index');
            $index = array_merge($classIndex, $index);
            $container->setParameter('goat.mapper.class_index', $index);
        }
        if ($serviceIndex && $container->hasParameter('goat.mapper.service_index')) {
            $index = $container->getParameter('goat.mapper.service_index');
            $index = array_merge($serviceIndex, $index);
            $container->setParameter('goat.mapper.service_index', $index);
        }
    }
}
