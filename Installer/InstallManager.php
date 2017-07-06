<?php

declare(strict_types=1);

namespace Goat\Bundle\Installer;

use Goat\Error\GoatError;
use Goat\Error\NotImplementedError;
use Goat\Runner\RunnerInterface;
use Goat\Runner\Transaction;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
     * @var RunnerInterface
     */
    private $runner;

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
     * @param RunnerInterface $runner
     * @param ContainerInterface $container
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(
        RunnerInterface $runner,
        ContainerInterface $container,
        EventDispatcherInterface $eventDispatcher,
        array $updaterIndex,
        array $classIndex
    ) {
        $this->runner = $runner;
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
                $this->runner->query("select 1 from goat_schema");
            } catch (GoatError $e) {
                $this->doRunInstall($this->selfUpdater);
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
     * Update version number in database
     *
     * @param Updater $updater
     * @param int $version
     */
    private function updateVersionNumber(Updater $updater, int $version)
    {
        $name = get_class($updater);

        // @todo need a merge query here
        if (!$this->runner->query("select 1 from goat_schema where name = $*", [$name])->fetchField()) {
            $this->runner->query("insert into goat_schema (version, name) values ($*, $*)", [$version, $name]);
        } else {
            $this->runner->query("update goat_schema set version = $* where name = $*", [$version, $name]);
        }
    }

    /**
     * Run update
     *
     * @param Updater $updater
     * @param int $version
     * @param bool $save
     */
    private function doRunUpdate(Updater $updater, int $version, bool $save = true)
    {
        $transaction = $this->runner->startTransaction(Transaction::SERIALIZABLE);

        try {
            call_user_func($updater->getUpdateCallback($version), $this->runner, $transaction);

            if ($save) {
                $this->updateVersionNumber($updater, $version);
            }

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
        $transaction = $this->runner->startTransaction(Transaction::SERIALIZABLE);
        $transaction->start();

        try {
            $updater->preInstallSchema($this->runner, $transaction);
            $updater->installSchema($this->runner, $transaction);
            $updater->postInstallSchema($this->runner, $transaction);
            $this->updateVersionNumber($updater, 0);

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
            $currentVersion = $this->runner->query("select version from goat_schema where name = $*", [$name])->fetchField();

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
            $currentVersion = $this->runner->query("select version from goat_schema where name = $*", [$name])->fetchField();

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
     * @param bool $save
     */
    public function runSingleUpdate(string $className, int $version, bool $save = true)
    {
        $this->doRunUpdate($this->getUpdater($className), $version, $save);
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
