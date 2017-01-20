<?php

declare(strict_types=1);

namespace Goat\Bundle\Installer;

use Goat\Core\Client\ConnectionInterface;
use Goat\Core\Error\DriverError;
use Goat\Core\Transaction\Transaction;

/**
 * Self installer.
 */
class SelfUpdater extends Updater
{
    /**
     * {@inheritdoc}
     */
    public function installSchema(ConnectionInterface $connection, Transaction $transaction)
    {
        // Very specific use case, you never should do this
        try {
            $connection->query("create table goat_schema(name varchar(255) unique not null, version integer not null default -1)");
        } catch (DriverError $e) {
            // Table has already been implicitely installed by the
            // ManagerInstaller that needs to have to compare versions.
        }
    }

    /**
     * The very first update that will ever be run.
     */
    public function update1(ConnectionInterface $connection, Transaction $transaction)
    {
    }

    /**
     * Another update function with a very long description.
     *
     * This is valid, please note that empty lines are not kept, and text will be
     * rendered in a very compact way.
     *
     * Use me as an example class to write your updates!
     */
    public function update2(ConnectionInterface $connection, Transaction $transaction)
    {
    }
}
