<?php

declare(strict_types=1);

namespace Goat\Bundle\Command;

use Goat\Bundle\Installer\InstallManager;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Run a single update
 */
class UpdaterRunSingleCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('updater:run-single')
            ->setDescription('Run a single update method (do not update internal schema version)')
            ->addArgument('class', InputArgument::REQUIRED, "Updater class name")
            ->addArgument('version', InputArgument::REQUIRED, "Update version to run")
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var \Goat\Bundle\Installer\InstallManager $installer */
        $installer = $this->getContainer()->get('goat.installer');

        $className = $input->getArgument('class');

        $version = $input->getArgument('version');
        if (!ctype_digit($version)) {
            throw new \InvalidArgumentException(sprintf("version must be a valid integer, '%s' given", $version));
        }
        $version = (int)$version;

        $installer->runSingleUpdate($className, $version, false);

        $output->writeln('<info>' . sprintf("Update %s, %d has run", $className, $version) . '</info>');
    }
}
