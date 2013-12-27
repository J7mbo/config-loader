config-loader
====

Provides a object-oriented public interface to reading configuration files, and an object to Dependency Inject around your application to retrieve configuration values when you need them.

Currently only Yaml is included, and uses `Symfony\Yaml`.

The service provides to ability to:

- **Required Keys.** An exception will be thrown if the configuration does not contain all the required keys.
- **Environment Configuration.** The environment can either be parsed from a `global.yml` file under the `environment` key, or set at runtime via a simple setter. Other environments in the `required_environments` key have their configuration file ignored.

Installation
===

Installation is via [Composer](http://getcomposer.org/). Add the following to your `composer.json` file:

    "require": {
        "j7mbo/config-loader": "*" 
    }

Usage
===

- Create the `YamlConfigLoader` object in your bootstrap.
- Set up your configuration files and choose to either use a `global.yml` file with the `environment` and `required_environments` keys, or set these at runtime using setters.
- Set any required keys that your application *must* have, using a setter.
- Run the `YamlConfigLoader::load()` method in a `try/catch` and be ready for any exceptions like `InvalidDirectoryException`. Handle these accordingly, usually by logging the exception (for example, via `Monolog\Logger`) and providing a friendly message to the user.
- If all goes well, you then pass the `YamlConfigLoader` through your application, and call `YamlConfigLoader::getConfiguration()` to retrieve the configuration array.

Example
===

First, create a configuration directory containing your `YAML` files. These could be `dev.yml`, `sandbox.yml`, `staging.yml` and `live.yml`. 

In the following code, we'll need to set the required (possible) environments to the same name as the environmental files above, and also our actual environment as the environment to be used.

    // bootstrap.php
    require_once __DIR__ . "/vendor/autoload.php"; // You'll need the composer autoloader included
    
    $parser = new Symfony\Yaml\Yaml; // The Symfony YAML parser
    $iteratorFactory = new ConfigLoader\FileSystemIteratorFactory; // Factory for a directory iterator
    $configLoader = new ConfigLoader\YamlConfigLoader($parser, $iteratorFactory);
    $configLoader->setEnvironment("dev");
    $configLoader->setPossibleEnvironments(["dev", "sandbox", "staging", "live"]);
    
    // The above means that sandbox.yml, staging.yml and live.yml will be ignored, whilst dev.yml will be parsed. Usually, these files have identical keys, but different values (db settings etc)
    
    try
    {
        $configLoader->setDirectory( __DIR__ . "/config"); // Set the directory for our config files
        $configLoader->load(); // Parse the data into the class configuration member
        var_dump($configLoader->getConfiguration()); // This is the method you can use when you pass this configuration object around your application.
    }
    catch (ConfigLoader\Exception\InvalidDirectoryException $e)
    {
        // The config directory doesn't exist or isn't readable
        // Log the error message
        // Show a nice message to the user
    }

More Info
===

#### Constructor Directory Parameter

You can pass the directory as the third parameter of the `YamlConfigLoader::__construct()` method. However, you will need to catch an `InvalidDirectoryExecption` here also, as it is the directory setter that performs the validation and throws the exception, and the constructor uses this setter instead of setting the member variable directly.

#### Non-runtime environment settings

If you don't want to have to change your code every time you change your environment, you don't have to use the `YamlConfigLoader::setEnvironment()` and `YamlConfigLoader::setPossibleEnvironments()` methods. Instead, create a `global.yml` file with the keys `environment` (containing a single value string) and `required_environments` (containing subkeys of multiple values).

If you omit the runtime method calls, the following exceptions will be thrown if the `environment` and `required_environments` keys are not found, or if the `global.yml` file is not found: 

- `ConfigLoader\Exception\GlobalConfigFileNotFoundException`
- `ConfigLoader\Exception\NonExistentEnvironmentKeyException`
- `ConfigLoader\Exception\NonExistentRequiredConfigKeyException`

#### Ensure a valid configuration set

You can also ensure that a configuration is always valid by using `YamlConfigLoader::setRequiredKeys()`, with an array as it's only parameter. If any of these keys are not found after performing the `load()` call, then the `NonExistentRequiredConfigKeyException` is thrown.

#### Get a specific configuration set by key

You don't have to request the whole configuration set. Sometimes it's clearer to request configuration details by a specific key. The getter for the configuration allows a parameter to do just this:

    `YamlConfigLoader::getConfiguration($key = "database");` 
    
This returns either an array with the key "database" from your configuration, or an empty array if they key does not exist. No exceptions are thrown here.

#### getConfiguration() -> getConfig()

You can use `YamlConfigLoader::getConfig()` as a shortcut to `YamlConfigLoader::getConfiguration()`.

Extension
====

You can add your own config-loader classes by implementing `ConfigurationInterface` and following along the lines of the `YamlConfigLoader`. Make sure to add a test for this in the `test/` directory.

Additional Notes
====

This class-set is *almost* like a service locator for your configuration, but not quite. It's just an object oriented way of passing around a configuration array of keys and values. If you have a large application, this probably isn't the best approach, but for small applications it isn't an issue calling `$this->config->getConfiguration("database");`.

Depending on how you have set up your code, you could dependency inject specific configuration classes - but that is not the aim of the class.