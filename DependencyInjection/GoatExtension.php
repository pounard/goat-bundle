<?php

namespace Goat\Bundle\DependencyInjection;

use Goat\Core\Client\ConnectionInterface;
use Goat\Core\Client\Dsn;
use Goat\Core\Converter\ConverterMap;
use Goat\Core\DebuggableInterface;
use Goat\Core\Error\NotImplementedError;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

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

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config/services'));
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

        if (!empty($config['mapping'])) {
            $bundles = $container->getParameter('kernel.bundles');
            foreach ($config['mapping'] as $bundle => $options) {
                // Be nice with the user, allow us to give the bundle name with
                // or without the 'Bundle' suffix. I struggled myself for too
                // long with bundle name normalization because no one ever
                // agrees on which to use. Just accept everything!
                if ('Bundle' !== substr($bundle, -6)) {
                    $realBundle = $bundle . 'Bundle';
                } else {
                    $realBundle = $bundle;
                }
                if (!isset($bundles[$realBundle])) {
                    throw new InvalidConfigurationException(sprintf('The bundle "%s" does not exist in kernel for path "goat.mapping.%s"', $realBundle, $bundle));
                }

                $this->registerMappingForBundle($container, $bundle, $options);
            }
        }

        if (in_array('Symfony\\Bundle\\WebProfilerBundle\\WebProfilerBundle', $container->getParameter('kernel.bundles'))) {
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
     * Create bundle mappers using annotations
     *
     * @param ContainerBuilder $container
     * @param string $bundle
     * @param array $options
     */
    private function registerMappingsForBundleAsAnnotations(ContainerBuilder $container, string $bundle, array $options)
    {
        // throw new NotImplementedError();
    }

    /**
     * Create bundle mappers
     *
     * @param ContainerBuilder $container
     * @param string $bundle
     * @param array $options
     */
    private function registerMappingForBundle(ContainerBuilder $container, string $bundle, array $options)
    {
        switch ($options['type']) {

            case 'annotation':
                $this->registerMappingsForBundleAsAnnotations($container, $bundle, $options);
                break;

            default:
                throw new InvalidConfigurationException(sprintf('The type "%s" is not implemented for path "goat.mapping.%s.type"', $bundle));
        }
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
