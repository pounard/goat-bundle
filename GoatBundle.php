<?php

namespace Goat\Bundle;

use Goat\Bundle\Command\GenerateEntityCommand;
use Goat\Bundle\Command\UpdaterListCommand;
use Goat\Bundle\Command\UpdaterRunCommand;
use Goat\Bundle\Command\UpdaterRunSingleCommand;
use Goat\Bundle\Command\UpdaterStatusCommand;
use Goat\Bundle\DependencyInjection\Compiler\RegisterMapperCompilerPass;
use Goat\Bundle\DependencyInjection\Compiler\RegisterUpdaterCompilerPass;
use Symfony\Component\Console\Application;
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
        $container->addCompilerPass(new RegisterUpdaterCompilerPass());
    }


    /**
     * {@inheritdoc}
     */
    public function registerCommands(Application $application)
    {
        $application->add(new GenerateEntityCommand());
        $application->add(new UpdaterListCommand());
        $application->add(new UpdaterRunCommand());
        $application->add(new UpdaterRunSingleCommand());
        $application->add(new UpdaterStatusCommand());
    }
}
