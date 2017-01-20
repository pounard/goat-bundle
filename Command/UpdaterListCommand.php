<?php

declare(strict_types=1);

namespace Goat\Bundle\Command;

use Goat\Bundle\Installer\InstallManager;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * List all updaters and their status
 */
class UpdaterListCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('updater:list')
            ->setDescription('List existing updaters and their current version and status')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var \Goat\Bundle\Installer\InstallManager $installer */
        $installer = $this->getContainer()->get('goat.installer');

        $table = new Table($output);
        $table->setHeaders(["Updater", "Status", "Version", "Missing"]);
        $table->setColumnStyle(1, (new TableStyle())->setPadType(STR_PAD_LEFT));
        $table->setStyle('compact');

        $info = $installer->getCurrentStatus();
        if (empty($info)) {
            $output->writeln('<info>There is no updater registered.</info>');
            return;
        }

        foreach ($info as $name => $status) {
            list($currentVersion, $missingCount) = $status;

            if (InstallManager::SCHEMA_UNINSTALLED === $currentVersion) {
                $statusText = "<warning>not installed<warning>";
            } else if ($missingCount) {
                $statusText = "<warning>updates pending<warning>";
            } else {
                $statusText = "<info>up to date</info>";
            }

            $table->addRow([$name, $statusText, $currentVersion, $missingCount]);
        }

        $table->render();
    }
}
