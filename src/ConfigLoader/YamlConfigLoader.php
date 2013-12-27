<?php

namespace ConfigLoader;

use ConfigLoader\Exception\NonExistentRequiredConfigKeyException,
    ConfigLoader\Exception\NonExistentEnvironmentKeyException,
    ConfigLoader\Exception\GlobalConfigFileNotFoundException,
    ConfigLoader\Exception\InvalidDirectoryException,
    ConfigLoader\FileSystemIteratorFactoryInterface,
    Symfony\Component\Yaml\Yaml;

class YamlConfigLoader implements ConfigurationInterface
{
    /**
     * @var ConfigLoader\FileSystemIteratorFactoryInterface
     */
    protected $fileSystemIteratorFactory;
    
    /**
     * @var array An array containing possible environments to ignore yaml files
     * for (excluding $environment) when parsing all the different config files
     */
    private $possibleEnvironments= [];
    
    /**
     * @var array The configuration array retrievable by ::getConfiguration()
     */
    private $configuration = [];
    
    /**
     * @var array A list of keys that must be in the parsed configuration array
     */
    private $requiredKeys = [];
    
    /**
     * @var string The environment
     */
    private $environment;
    
    /**
     * @var string Directory path string to parse config files in
     */
    private $directory;
    
    /**
     * {@inheritdoc}
     */
    public function getConfiguration($key = null)
    {
        if (!is_null($key))
        {
            if (isset($this->configuration[$key]))
            {
                return $this->configuration[$key];
            }
        }
        
        return $this->configuration;
    }
    
    /**
     * {@inheritdoc}
     */
    public function setEnvironment($environment)
    {
        $this->environment = $environment;
        
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getEnvironment()
    {
        return $this->environment;
    }
    
    /**
     * Set possible environments array
     * 
     * @param array $environments
     * 
     * @return YamlConfigLoader Instance of self for method chaining
     */
    public function setPossibleEnvironments(array $environments)
    {
        $this->possibleEnvironments = $environments;
        
        return $this;
    }
    
    /**
     * Return possible environments array
     * 
     * @return array
     */
    public function getPossibleEnvironments()
    {
        return $this->possibleEnvironments;
    }
    
    /**
     * {@inheritdoc}
     */
    public function setDirectory($directory)
    {
        if (!is_dir($directory) || !is_writeable($directory))
        {
            throw new InvalidDirectoryException(sprintf("Unable to find / write to directory: %s. Please check your f/s permissions.", $directory));
        }
        
        $this->directory = $directory;
        
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getDirectory()
    {
        if (!is_dir($this->directory) || !is_writeable($this->directory))
        {
            throw new InvalidDirectoryException(sprintf("Unable to find / write to directory: %s. Please check your f/s permissions.", $this->directory));
        }
        
        return $this->directory;
    }
    
    /**
     * Set required keys array
     * 
     * @param array $keys
     * 
     * @return YamlConfigLoader Instance of self for method chaining
     */
    public function setRequiredKeys(array $keys)
    {
        $this->requiredKeys = $keys;
        
        return $this;
    }
    
    /**
     * Return required keys array
     * 
     * @return array
     */
    public function getRequiredKeys()
    {
        return $this->requiredKeys;
    }
    
    /**
     * Shortcut to $this->getConfiguration()
     * 
     * @see YamlConfigLoader::getConfiguration()
     */
    public function getConfig($key = null)
    {
        return $this->getConfiguration($key);
    }
    
    /**
     * 
     * @param Symfony\Component\Yaml\Yaml                     $parser 
     * @param ConfigLoader\FileSystemIteratorFactoryInterface $iteratorFactory
     * @param string                                           $directory
     */
    public function __construct(Yaml $parser, FileSystemIteratorFactoryInterface $iteratorFactory, $directory = null)
    {
        $this->fileSystemIteratorFactory = $iteratorFactory;
        $this->parser = $parser;
        
        if (!is_null($directory))
        {
            $this->setDirectory($directory);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function load()
    {
        $requiredFile = sprintf("%s/global.yml", $this->getDirectory());
        
        if (is_null($this->getEnvironment()) || is_null($this->getPossibleEnvironments()))
        {
            if (is_file($requiredFile) && is_writable($requiredFile))
            {
                $global = $this->parser->parse($requiredFile);
                
                if (isset($global['environment']) && isset($global['required_environments']))
                {
                    $this->setEnvironment($global['environment']);
                    $this->setPossibleEnvironments($global['required_environments']);                    
                }
                else
                {
                    throw new NonExistentEnvironmentKeyException("The global.yml file requires an environment key and a required_environments key");
                }
            }
            else
            {
                throw new GlobalConfigFileNotFoundException(sprintf("Global.yml file required, but not found, in: %s", $this->getDirectory()));
            }
        }
        
        $iterator = $this->fileSystemIteratorFactory->build($this->getDirectory());

        foreach ($iterator as $file)
        {   
            if ($file->getExtension() === "yml" && !in_array(basename($file->getFilename(), '.yml'), array_diff(array_values($this->getPossibleEnvironments()), [$this->getEnvironment()])))
            {
                $data = $this->parser->parse($file);

                if (!is_null($data))
                {
                    $this->configuration += $data;
                }
            }
        }
        
        foreach ($this->requiredKeys as $requiredKey)
        {
            if (!array_key_exists($requiredKey, $this->configuration))
            {
                throw new NonExistentRequiredConfigKeyException(sprintf("Configuration requires the key: %s", $requiredKey));
            }
        }
    }
}