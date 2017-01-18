<?php

namespace Goat\Bundle\DependencyInjection;

use Goat\Core\Client\ConnectionInterface;
use Goat\Core\Client\Dsn;
use Goat\Core\Converter\ConverterMap;
use Goat\Core\DebuggableInterface;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;

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

        // Set debug mode on components *before* registering any more
        // dependencies, because the debug mode in most case will interact
        // at runtime during registration and set calls.
        if ($config['debug']) {
            $this->activateDebugMode($container);
        }

        $this->registerDefaultConverters($container);

        if (in_array(WebProfilerBundle::class, $container->getParameter('kernel.bundles'))) {
            $loader->load('profiler.yml');
        }
    }

    /**
     * Create connection definition
     *
     * @param ContainerBuilder $container
     * @param string $name
     *   Connection identifier: 'readonly' or 'readwrite'
     * @param array $options
     *   Options from the config.yml file
     */
    private function createConnectionDefinition(ContainerBuilder $container, string $name, array $options)
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
        $definition->addMethodCall('setConverter', [new Reference('goat.converter_map')]);
        $definition->addMethodCall('setHydratorMap', [new Reference('goat.hydrator_map')]);

        if ($options['debug']) {
            $definition->addMethodCall('setDebug', [true]);
        }

        $container->addDefinitions(['goat.connection.' . $name => $definition]);
    }

    /**
     * Set debug mode over various components
     *
     * @param ContainerBuilder $container
     */
    private function activateDebugMode(ContainerBuilder $container)
    {
        $services = [
            'goat.converter_map',
            'goat.hydrator_map',
        ];

        foreach ($services as $id) {
            $definition = $container->getDefinition($id);
            if (is_subclass_of($definition->getClass(), DebuggableInterface::class)) {
                $definition->addMethodCall('setDebug', [true]);
            }
        }
    }

    /**
     * Create and register default converters
     *
     * @param ContainerBuilder $container
     */
    private function registerDefaultConverters(ContainerBuilder $container)
    {
        $converterMapDefinition = $container->getDefinition('goat.converter_map');

        foreach (ConverterMap::getDefautConverterMap() as $type => $data) {
            list($class, $aliases) = $data;

            $converterDefinition = new Definition();
            $converterDefinition->setClass($class);
            $converterDefinition->setShared(false);
            $converterDefinition->setPublic(false);

            $converterMapDefinition->addMethodCall('register', [$type, $converterDefinition, $aliases]);
        }
    }
}
