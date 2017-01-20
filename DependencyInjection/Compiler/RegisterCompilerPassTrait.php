<?php

namespace Goat\Bundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Collection of useful method for services registration based on tags
 */
trait RegisterCompilerPassTrait /* implements CompilerPassInterface */
{
    /**
     * Collect all services with the given tag, in reverse order using the
     * 'priority' tag attribute, also check it implements the given interface
     *
     * @param ContainerBuilder $container
     * @param string $tag
     * @param string $interface
     *
     * @return string[][]
     *   Priority-sorted tags, whose keys are service identifiers
     */
    private function collectTaggedServicesTags(ContainerBuilder $container, $tag, $interface)
    {
        $ret = [];

        foreach ($container->findTaggedServiceIds($tag) as $id => $attributes) {
            $def = $container->getDefinition($id);

            $attributes = $attributes[0];

            if (isset($attributes['priority'])) {
                if (!is_numeric($attributes['priority'])) {
                    throw new \InvalidArgumentException(sprintf('Service "%s" defines the "priority" attribute on "%s" tags, it must be a valid integer.', $id, $tag));
                }
            } else {
                $attributes['priority'] = 0;
            }

            // We must assume that the class value has been correctly filled, even if the service is created by a factory
            $class = $container->getParameterBag()->resolveValue($def->getClass());

            if (!is_subclass_of($class, $interface)) {
                if (!class_exists($class, false)) {
                    throw new \InvalidArgumentException(sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
                }

                throw new \InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $id, $interface));
            }

            $ret[$id] = $attributes;
        }

        uasort($ret, function ($a, $b) {
            return $b['priority'] - $a['priority'];
        });

        return $ret;
    }

    /**
     * Collect all services with the given tag, in reverse order using the
     * 'priority' tag attribute, also check it implements the given interface
     *
     * @param ContainerBuilder $container
     * @param string $tag
     * @param string $interface
     *
     * @return string[]
     *   Priority-sorted services identifiers or aliases
     */
    private function collectTaggedServices(ContainerBuilder $container, $tag, $interface)
    {
        return array_keys($this->collectTaggedServicesTags($container, $tag, $interface));
    }

    /**
     * Unregister the given service
     *
     * @param ContainerBuilder $container
     * @param string $id
     *   Service identifier or alias
     */
    private function unregister(ContainerBuilder $container, $id)
    {
        if ($container->hasDefinition($id)) {
            $container->removeDefinition($id);
        }
        if ($container->hasAlias($id)) {
            $container->removeAlias($id);
        }
    }

    /**
     * Map the given string array to reference instances
     *
     * @param string[] $idList
     *
     * @return Reference[]
     */
    private function mapIdToReference($idList)
    {
        return array_map(
            function ($id) {
                return new Reference($id);
            },
            $idList
        );
    }
}
