<?php

declare(strict_types=1);

namespace Goat\Bundle\DependencyInjection\Compiler;

use Goat\Bundle\Installer\Updater;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RegisterUpdaterCompilerPass implements CompilerPassInterface
{
    use RegisterCompilerPassTrait;

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('goat.installer')) {
            return;
        }

        $classIndex = [];
        $serviceIndex = [];

        $idList = $this->collectTaggedServices($container, 'goat.updater', Updater::class);
        foreach ($idList as $id) {
            $definition = $container->getDefinition($id);
            $class = $container->getParameterBag()->resolveValue($definition->getClass());

            $serviceIndex[] = $id;
            $classIndex[$class] = $id;
        }

        $container
            ->getDefinition('goat.installer')
            ->replaceArgument(3, $serviceIndex)
            ->replaceArgument(4, $classIndex)
        ;
    }
}
