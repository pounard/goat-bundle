<?php

declare(strict_types=1);

namespace Goat\Bundle\Installer;

use Goat\Core\Client\ConnectionInterface;
use Goat\Core\Transaction\Transaction;

/**
 * Base implementation for updates
 *
 * You may override any of those methods, and register your updater as a
 * container service; you may inject any other service or parameters into
 * this object.
 *
 * Only restriction is that you need to be sure that this object can live
 * in a degraged environment, where database has not been fully initialized
 * yet, since it's the role of this object to do so.
 */
class Updater
{
    /**
     * @var int[]
     */
    private $updateIndex;

    /**
     * @var string[]
     */
    private $updateDescriptions;

    /**
     * This is run before schema is installed
     *
     * @param ConnectionInterface $connection
     * @param Transaction $transaction
     */
    public function preInstallSchema(ConnectionInterface $connection, Transaction $transaction)
    {
    }

    /**
     * Install schema
     *
     * @param ConnectionInterface $connection
     * @param Transaction $transaction
     */
    public function installSchema(ConnectionInterface $connection, Transaction $transaction)
    {
    }

    /**
     * This is run after schema is installed
     *
     * @param ConnectionInterface $connection
     * @param Transaction $transaction
     */
    public function postInstallSchema(ConnectionInterface $connection, Transaction $transaction)
    {
    }

    /**
     * This is run before schema is uninstalled
     *
     * @param ConnectionInterface $connection
     * @param Transaction $transaction
     */
    public function preUninstallSchema(ConnectionInterface $connection, Transaction $transaction)
    {
    }

    /**
     * Uninstall schema
     *
     * @param ConnectionInterface $connection
     * @param Transaction $transaction
     */
    public function uninstallSchema(ConnectionInterface $connection, Transaction $transaction)
    {
    }

    /**
     * This is run after the schema is uninstalled
     *
     * @param ConnectionInterface $connection
     * @param Transaction $transaction
     */
    public function postUninstallSchema(ConnectionInterface $connection, Transaction $transaction)
    {
    }

    /**
     * Internal methods lookup for findUpdateMethods()
     */
    private function lookupUpdateMethods()
    {
        $this->updateIndex = [];
        $this->updateDescriptions = [];
        $reflectionClass = new \ReflectionClass(get_class($this));

        /** @var \ReflectionMethod $method */
        foreach ($reflectionClass->getMethods() as $method) {
            if (0 === strpos($method->name, 'update')) {
                $version = substr($method->name, 6);

                if (ctype_digit($version)) {
                    $this->updateIndex[(int)$version] = [$this, $method->name];

                    // Attempt to find description too
                    $description = $method->getDocComment();
                    if ($description) {
                        // Quite ugly, but working really well.
                        $description = preg_replace('/\n[\s\*]+/m', "\n", $description);
                        $description = trim($description, "/# *\n");
                    }
                    $this->updateDescriptions[(int)$version] = $description ?? '[undocumented update]';
                }
            }
        }
    }

    /**
     * Does update exists
     *
     * @param int $version
     *
     * @return bool
     */
    public function updateExists(int $version) : bool
    {
        if (null === $this->updateIndex) {
            $this->lookupUpdateMethods();
        }

        return isset($this->updateIndex[$version]);
    }

    /**
     * Get update callback
     *
     * @param $version
     *
     * @return callable
     */
    public function getUpdateCallback(int $version) : callable
    {
        if (!$this->updateExists($version)) {
            throw new \InvalidArgumentException(sprintf("%s::update%d() method does not exists", get_class($this), $version));
        }

        return [$this, 'update' . $version];
    }

    /**
     * Get update description
     *
     * @param int $version
     *
     * @return string
     */
    public function getUpdateDescription(int $version) : string
    {
        if (!$this->updateExists($version)) {
            throw new \InvalidArgumentException(sprintf("%s::update%d() method does not exists", get_class($this), $version));
        }
    }

    /**
     * Get missing update list since
     *
     * @return string[]
     */
    public function getMissingUpdateSince(int $version) : array
    {
        // Force preload, but version does not need to exists to be able to
        // run all methods above
        $this->updateExists($version);

        return array_filter(
            $this->updateDescriptions,
            function ($key) use ($version) {
                return $version < $key;
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Find all update methods this object carries
     *
     * Just like PHPUnit will lookup for test methods, prefixed with "test"
     * updates methods will be prefixed using "update" followed by a number.
     *
     * If numbering does not please you, you can override this method and
     * provide manually a set of method names or closures. But you also will
     * need to implement the getUpdateCallback() too.
     *
     * @return int[]
     *   Update procedure identifiers
     */
    public function findUpdateMethods() : array
    {
        if (null === $this->updateIndex) {
            $this->lookupUpdateMethods();
        }

        return $this->updateIndex;
    }
}
