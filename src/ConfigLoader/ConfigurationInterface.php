<?php

namespace ConfigLoader;

interface ConfigurationInterface
{
    /**
     * Provides the ability to retrieve the whole configuration array or
     * a specific key within the configuration array
     * 
     * @param string $key 
     * 
     * @return array The configuration array parsed via load()
     */
    public function getConfiguration($key = null);
    
    /**
     * Every configuration must have an environment. This can either be set in
     * the constructor or via this setter
     * 
     * @param string $environment The environment e.g. [dev|sandbox|live]
     * 
     * @return ConfigurationInterface Instance of self for method chaining
     */
    public function setEnvironment($environment);
    
    /**
     * Getter for the environment
     * 
     * @return string the environment set
     */
    public function getEnvironment();
    
    /**
     * Set the directory to find config files in
     * 
     * @param string $directory The directory path string
     * 
     * @throws ConfigLoader\Exception\InvalidDirectoryException
     * 
     * @return ConfigurationInterface Instance of self for method chaining
     */
    public function setDirectory($directory);
    
    /**
     * Retrieve the directory to find config files in
     * 
     * @throws ConfigLoader\Exception\InvalidDirectoryException
     * 
     * @return string The directory path
     */
    public function getDirectory();
    
    /**
     * Parses the configuration and places it in the $configuration class member
     * 
     * @throws ConfigLoader\Exception\NonExistantRequiredConfigKeyException
     * @throws ConfigLoader\Exception\NonExistantEnvironmentKeyException
     * @throws ConfigLoader\Exception\GlobalConfigFileNotFoundException
     * 
     * @return void
     */
    public function load();
}
