<?php

declare(strict_types=1);

namespace Goat\Bundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdaterStatusCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('updater:status')
            ->setDescription('Display pending updates')
        ;
    }

    /**app
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var \Goat\Bundle\Installer\InstallManager $installer */
        $installer = $this->getContainer()->get('goat.installer');

        $table = new Table($output);
        $table->setHeaders(["Updater", "Pending", "Description"]);
        $table->setColumnStyle(1, (new TableStyle())->setPadType(STR_PAD_LEFT));
        $table->setStyle('compact');

        $pending = $installer->getPendingUpdates();
        if (empty($pending)) {
            $output->writeln('<info>There is update pending.</info>');
            return;
        }

        foreach ($pending as $name => $updates) {
            $first = true;
            foreach ($updates as $version => $description) {
                if ($first) {
                    $table->addRow([$name, $version, $description]);
                    $first = false;
                } else {
                    $table->addRow(['', $version, $description]);
                }
            }
        }

        $table->render();
    }
}
