<?php

namespace Goat\Bundle\DependencyInjection;

use Goat\Core\Client\ConnectionInterface;
use Goat\Core\Client\Dsn;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * The one and only Goat extension!
 */
class GoatExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        $this->createConnectionDefinition($container, 'readwrite', $config['connection']['readwrite']);
        if (isset($config['connection']['readonly'])) {
            $this->createConnectionDefinition($container, 'readonly', $config['connection']['readonly']);
        }
    }

    /**
     * Create connection definition
     */
    private function createConnectionDefinition(ContainerBuilder $container, $name, $options)
    {
        $definition = new Definition();
        $dsn = new Dsn($options['host']);

        if (isset($options['driver_class'])) {
            $driverClass = $options['driver_class'];

            if (!class_exists($driverClass)) {
                throw new \LogicException("Class '%s' for connection '%s' does not exist'", $driverClass, $name);
            }
            if (!is_subclass_of($driverClass, ConnectionInterface::class)) {
                throw new \LogicException("Class '%s' for connection '%s' does not implements '%s'", $driverClass, $name, ConnectionInterface::class);
            }
        } else {
            switch ($dsn->getDriver()) {

                case 'ext_pgsql':
                    $driverClass = \Goat\Driver\PgSQL\PgSQLConnection::class;
                    break;

                case 'pdo_mysql':
                    $driverClass = \Goat\Driver\PDO\MySQLConnection::class;
                    break;

                case 'pdo_pgsql':
                    $driverClass = \Goat\Driver\PDO\PgSQLConnection::class;
                    break;
            }
        }

        // Create the DNS as a private non-shared service
        $dsnDefinition = new Definition();
        $dsnDefinition->setClass(Dsn::class);
        $dsnDefinition->setShared(false);
        $dsnDefinition->setPublic(false);
        $dsnDefinition->setArguments([
            $options['host'],
            $options['user'] ?? null,
            $options['password'] ?? null,
            $options['charset'],
        ]);

        $definition->setClass($driverClass);
        $definition->addArgument($dsnDefinition);

        if ($options['debug']) {
            $definition->addMethodCall('setDebug', [true]);
        }

        $container->addDefinitions(['goat.connection.' . $name => $definition]);
    }
}
