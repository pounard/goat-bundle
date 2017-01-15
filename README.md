# Goat bundle

Experimental bundle for the [Goat database connector](https://github.com/pounard/goat).


# Install

Install this bundle using composer:

```sh
composer require makinacorpus/goat-bundle
```

Register it into your kernel class:

```php
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            // ... (your other bundles)
            new Goat\Bundle\GoatBundle(),
        ];

        return $bundles;
    }
}
```


# Configure

In your ``app/config/parameters.yml`` file:

```yaml
    database_host: ext_pgsql://192.168.57.102/my_database
    database_user: my_username
    database_password: my_password
```

Where the database host is not a real hostname, but a complete DSN composed
with:

 *  ``ext_pgsql`` is the driver to use, accepted values are ``ext_pgsql``,
    ``pdo_mysql`` and ``pdo_pgsql``;

 *  other parameters are obvious.

In your ``app/config/config.yml`` file:

```yaml
goat:
    debug:              "%kernel.debug%"
    connection:
        readwrite:
            host:       "%database_host%"
            user:       "%database_user%"
            password:   "%database_password%"
            charset:    UTF-8
            debug:      "%kernel.debug%"
```

This needs to be documented, but please see the complete and documented
[sample config.yml file](Resources/config/sample.config.yml) for more
information.


# Todo

 *  [postponed] entity generation
 *  [postponed] mapper generation
 *  [x] converters default configuration (extension)
 *  [x] driver configuration
 *  converters user configuration (compiler pass with tags)
 *  entity aliasing
 *  entity configuration
 *  generated-hydrator autoloader
 *  generated-hydrator cache handling
 *  profiler integration
 *  validation integration
