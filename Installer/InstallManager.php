<?php

declare(strict_types=1);

namespace Goat\Bundle\Installer;

use Goat\Core\Client\ConnectionInterface;
use Goat\Core\Error\GoatError;
use Goat\Core\Error\NotImplementedError;
use Goat\Core\Transaction\Transaction;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Install and update manager
 *
 * @todo implement topological sort algorithm for dependency resolution
 */
class InstallManager
{
    const SCHEMA_UNINSTALLED = -1;

    /**
     * @var ConnectionInterface
     */
    private $connection;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var string[]
     */
    private $updaterIndex = [];

    /**
     * @var string[]
     */
    private $classIndex = [];

    /**
     * @var SelfUpdater
     */
    private $selfUpdater;

    /**
     * @var bool
     */
    private $selfSchemaChecked = false;

    /**
     * Default constructor
     *
     * @param ConnectionInterface $connection
     * @param ContainerInterface $container
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(
        ConnectionInterface $connection,
        ContainerInterface $container,
        EventDispatcherInterface $eventDispatcher,
        array $updaterIndex,
        array $classIndex
    ) {
        $this->connection = $connection;
        $this->container = $container;
        $this->eventDispatcher = $eventDispatcher;
        $this->updaterIndex = $updaterIndex;
        $this->classIndex = $classIndex;
        $this->selfUpdater = new SelfUpdater();
    }

    /**
     * Ensure self-schema is installed
     */
    private function ensureSchema()
    {
        if (!$this->selfSchemaChecked) {
            try {
                $this->connection->query("select 1 from goat_schema");
            } catch (GoatError $e) {
                $this->selfUpdater->installSchema($this->connection);
            }

            $this->selfSchemaChecked = true;
        }
    }

    /**
     * Get a single updater
     *
     * @param string $className
     *   Updater class name
     *
     * @return Updater
     */
    private function getUpdater(string $className) : Updater
    {
        $className = ltrim($className, '\\');

        if (!isset($this->classIndex[$className])) {
            throw new \InvalidArgumentException(sprintf("updater '%s' is not registered", $className));
        }

        $updater = $this->container->get($this->classIndex[$className]);

        if (!$updater instanceof Updater) {
            throw new \InvalidArgumentException(sprintf("updater '%s' does not exists '%s'", $className, Updater::class));
        }

        return $updater;
    }

    /**
     * Get all updater instances
     *
     * @return Updater[]
     */
    private function getAllUpdaters() : array
    {
        $ret = [];

        foreach ($this->updaterIndex as $serviceId) {
            $updater = $this->container->get($serviceId);

            if (!$updater instanceof Updater) {
                throw new \InvalidArgumentException(sprintf("service '%s' does not exists '%s'", $serviceId, Updater::class));
            }

            $ret[] = $updater;
        }

        return $ret;
    }

    /**
     * Run update
     *
     * @param Updater $updater
     * @param int $version
     */
    private function doRunUpdate(Updater $updater, int $version)
    {
        $transaction = $this->connection->startTransaction(Transaction::SERIALIZABLE);
        $transaction->start();

        try {
            call_user_func($updater->getUpdateCallback($version), $this->connection, $transaction);

            $transaction->commit();

        } catch (\Throwable $e) {
            $transaction->rollback();

            throw $e;
        }
    }

    /**
     * Run install
     *
     * @param Updater $updater
     */
    private function doRunInstall(Updater $updater)
    {
        $transaction = $this->connection->startTransaction(Transaction::SERIALIZABLE);
        $transaction->start();

        try {
            $updater->preInstallSchema($this->connection, $transaction);
            $updater->installSchema($this->connection, $transaction);
            $updater->postInstallSchema($this->connection, $transaction);

            $transaction->commit();

        } catch (\Throwable $e) {
            $transaction->rollback();

            throw $e;
        }
    }

    /**
     * Install given updater
     */
    public function install(string $className)
    {
        $this->doRunInstall($this->getUpdater($className));
    }

    /**
     * Get pending update list from all registered updaters
     *
     * @return string[][]
     *   First dimension keys are updater class names, each value is an array
     *   of pending updates, where the keys are version numbers, and values
     *   are string descriptions. Version numbers are sorted.
     *   For optimization reasons in order to avoid certain versions of PHP
     *   writing indexed arrays, all integer keys are casted to strings.
     */
    public function getPendingUpdates() : array
    {
        if (!$this->updaterIndex) {
            return [];
        }

        $this->ensureSchema();

        $ret = [];
        foreach ($this->getAllUpdaters() as $updater) {

            $name = get_class($updater);
            $currentVersion = $this->connection->query("select version from goat_schema where name = $*", [$name])->fetchField();

            if (null === $currentVersion) {
                $currentVersion = self::SCHEMA_UNINSTALLED;
            }
            if ($currentVersion === self::SCHEMA_UNINSTALLED) {
                $ret[$name][(string)$currentVersion] = "Installation";
            }

            foreach ($updater->getMissingUpdateSince($currentVersion) as $version => $description) {
                $ret[$name][(string)$version] = $description;
            }
        }

        return $ret;
    }

    /**
     * Get current update status
     *
     * @return array
     *   First dimension keys are updater class names, each value is an array
     *   that contains:
     *     - first value is the current update status
     *     - second value is the number of update pending (0 if up to date)
     */
    public function getCurrentStatus() : array
    {
        if (!$this->updaterIndex) {
            return [];
        }

        $this->ensureSchema();

        $ret = [];
        foreach ($this->getAllUpdaters() as $updater) {

            $name = get_class($updater);
            $currentVersion = $this->connection->query("select version from goat_schema where name = $*", [$name])->fetchField();

            if (null === $currentVersion) {
                $currentVersion = self::SCHEMA_UNINSTALLED;
            }
            if ($currentVersion === self::SCHEMA_UNINSTALLED) {
                $ret[$name][(string)$currentVersion] = "Installation";
            }

            $missingCount = count($updater->getMissingUpdateSince($currentVersion));
            $ret[$name] = [$currentVersion, $missingCount];
        }

        return $ret;
    }

    /**
     * Run a single update procedure
     *
     * If you call this method, schema will not be updated.
     *
     * @param string $className
     * @param int $version
     */
    public function runSingleUpdate(string $className, int $version)
    {
        $this->doRunUpdate($this->getUpdater($className), $version);
    }

    /**
     * Run all pending updates
     */
    public function runAllPendingUpdates()
    {
        throw new NotImplementedError();
    }

    /**
     * Get current version for the given updater class
     *
     * @param string $className
     */
    public function getCurrentVersion(string $className) : int
    {
        throw new NotImplementedError();
    }
}
