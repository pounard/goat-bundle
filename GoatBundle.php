<?php

namespace Goat\Bundle;

use Goat\Bundle\DependencyInjection\Compiler\RegisterMapperCompilerPass;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * The one and only Goat bunde!
 */
class GoatBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new RegisterMapperCompilerPass());
    }
}
