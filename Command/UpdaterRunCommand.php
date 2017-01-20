<?php

declare(strict_types=1);

namespace Goat\Bundle\Command;

use Goat\Bundle\Installer\InstallManager;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Run a single update
 */
class UpdaterRunCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('updater:run')
            ->setDescription('Run all pending updates')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var \Goat\Bundle\Installer\InstallManager $installer */
        $installer = $this->getContainer()->get('goat.installer');

        $pending = $installer->getPendingUpdates();

        foreach ($pending as $name => $updates) {
            foreach ($updates as $version => $description) {
                $version = (int)$version;

                if (InstallManager::SCHEMA_UNINSTALLED === $version) {
                    $output->writeln(sprintf('Installing %s', $name));

                    $installer->install($name);

                } else {
                    $output->writeln(sprintf('Running %s, %d - %s', $name, $version, $description));

                    // @todo reentrency and schema save
                    $installer->runSingleUpdate($name, $version, true);
                }
            }
        }
    }
}
