<?php

use ConfigLoader\FileSystemIteratorFactory,
    ConfigLoader\ConfigurationInterface,
    ConfigLoader\YamlConfigLoader,
    Symfony\Component\Yaml\Dumper,
    Symfony\Component\Yaml\Yaml;

class YamlConfigLoaderTest extends PHPUnit_Framework_TestCase
{
    static $testCreateDir = "Temp";
    
    /** Required for phpunit [before|after]Class() **/
    static $staticTestFiles = ["dev.yml", "sandbox.yml", "staging.yml", "live.yml"];
    
    private $testFiles = ["dev.yml", "sandbox.yml", "staging.yml", "live.yml"];    
    
    /**
     * @var ConfigLoader\YamlConfigLoader
     */
    private $configLoader;
    
    /**
     * @var Symfony\Component\Yaml\Dumper
     */
    private $dumper;
    
    /**
     * @var Symfony\Component\Yaml\Yaml
     */
    private $parser;
    
    /**
     * We're going to need readable / writeable files to test parsing / loading
     * 
     * @throws Exception When file or directory can't be read from or written to
     */
    public static function setUpBeforeClass()
    {
        /** If an error occured last time, run the clean up first **/
        self::tearDownAfterClass();
        
        /** Create test directory path **/
        self::$testCreateDir = sprintf("%s/%s", __DIR__, self::$testCreateDir);
        
        /** Create test directory **/
        if (!mkdir(self::$testCreateDir, 0777) || !is_writable(self::$testCreateDir))
        {
            throw new Exception(sprintf("Can't create test create directory: %s", self::TEST_CREATE_DIR));
            exit;
        }
        
        /** Create global.yml file **/
        if (!touch(self::$testCreateDir . "/global.yml") || !is_writeable(self::$testCreateDir . "/global.yml"))
        {
           throw new Exception("Can't create global.yml file.");
           exit;
        }
        
        /** Create individual .yml files **/
        $testFiles = self::$staticTestFiles;
        
        foreach ($testFiles as $testFile)
        {
            $dirFile = sprintf("%s/%s", self::$testCreateDir, $testFile);
            
            if (!touch($dirFile) || !is_writeable($dirFile))
            {
                unlink(sprintf("%s/global.yml", self::$testCreateDir));
                
                foreach ($testFiles as $file)
                {
                    unlink(sprintf("%s/%s", self::$testCreateDir, $file));
                }
                
                throw new Exception(sprintf("Can't create test file: %s/%s", self::$testCreateDir, $testFile));
                exit;
            }
        }
    }
    
    public function setUp()
    {
        $this->parser = new Yaml;
        $this->dumper = new Dumper;
        $this->configLoader = new YamlConfigLoader($this->parser, new FilesystemIteratorFactory, $directory = self::$testCreateDir);
        
        /** In some tests, we may delete the global.yml file **/
        if (!file_exists(self::$testCreateDir . "/global.yml"))
        {
            touch(self::$testCreateDir . "/global.yml");
        }
    }
    
    public function testInterface()
    {
        $this->assertTrue($this->configLoader instanceof ConfigurationInterface);
    }
    
    public function testCanSetAndRetrieveDirectory() 
    {
        $this->configLoader->setDirectory(self::$testCreateDir);
        $this->assertTrue($this->configLoader->getDirectory() === self::$testCreateDir);
    }
    
    /**
     * Constructor doesn't just set $this->directory, it uses $this->setDirectory()
     */
    public function testDirectorySetterCanThrowDirectoryNotFoundException()
    {
        $this->setExpectedException("ConfigLoader\Exception\InvalidDirectoryException");
        $this->configLoader = new YamlConfigLoader(new Yaml, new FileSystemIteratorFactory, $directory = "/InvalidDirectory");
    }
    
    /**
     * Imagine someone deletes the config directory whilst the getter is running
     * 
     * 0777 is default, but added for extra clarification
     */
    public function testDirectoryGetterCanThrowDirectoryNotFoundException()
    {
        $this->setExpectedException("ConfigLoader\Exception\InvalidDirectoryException");
        $this->configLoader = new YamlConfigLoader(new Yaml, new FileSystemIteratorFactory, $directory = "/InvalidDirectory");
    }
    
    public function testCanSetAndRetrieveRequiredKeys()
    {
        $this->configLoader->setRequiredKeys($array = [1, 2, 3]);
        $this->assertTrue($this->configLoader->getRequiredKeys() === $array);
    }
    
    public function testCanManuallySetAndRetrieveEnvironment()
    {
        $this->configLoader->setEnvironment("dev");
        $this->assertTrue($this->configLoader->getEnvironment() === "dev");
    }
    
    public function testLoadThrowsExceptionOnNonExistentOrNonReadableGlobalYamlFile()
    {
        /** If we created the file and got this far, we'll be okay to delete it **/
        /** $this->setUp() will create a new global.yml file for the next test **/
        unlink(self::$testCreateDir . "/global.yml");
        
        $this->setExpectedException("ConfigLoader\Exception\GlobalConfigFileNotFoundException");

        $this->configLoader->load();
    }
    
    public function testLoadThrowsExceptionOnNonExistentEnvironmentKey()
    {
        $this->setExpectedException("ConfigLoader\Exception\NonExistentEnvironmentKeyException");
        $this->configLoader->load();
    }

    /**
     * Although the YAML parser of Symfony has it's own unit tests, it's worth
     * make sure that the files are parsed as expected first
     */
    public function testCanParseConfigurationFiles()
    {
        /** We'll need an environment key and possible environments **/
        /** As these persist in the YAML until the teardown, we only need to do this once **/
        $envs = [
            "environment" => "dev",
            "possible"    => [
                "dev",
                "sandbox",
                "staging",
                "live"
            ]
        ];
        
        file_put_contents(self::$testCreateDir . "/global.yml", $this->dumper->dump($envs));
        
        $arrayToSave = [];
        $configRead = [];
        
        foreach ($this->testFiles as $file)
        {
            $file = sprintf("%s/%s", self::$testCreateDir, $file);
            
            $arrayToSave[$file] = "someValue";
            $array = [$file => "someValue"];

            /** No need for touch() as this creates the file anyway **/
            file_put_contents($file, $this->dumper->dump($array));
            
            /** Now read back into config array via parser in same foreach() **/
            $configRead += $this->parser->parse($file);
        }
        
        /** Does the original added array match the array we just parsed? **/
        $this->assertTrue($arrayToSave === $configRead);
    }
    
    public function testLoadMethodSuccessfulAndIgnoresOtherEnvYamlFiles()
    {
        $envs = [
            "environment" => "dev",
            "required_environments" => [
                "dev",
                "sandbox",
                "staging",
                "live"
            ]
        ];
        
        file_put_contents(self::$testCreateDir . "/global.yml", $this->dumper->dump($envs));
        file_put_contents(self::$testCreateDir . "/sandbox.yml", $this->dumper->dump(["badKey" => "this shouldnt be parsed"]));
        
        $this->configLoader->load();
        
        $this->assertTrue(!array_key_exists("badKey", $this->configLoader->getConfiguration()));
    }
    
    public function testNonYAMLFileIgnored()
    {
        $testFiles = array_merge($this->testFiles, ['invalidFile.txt']);
        
        file_put_contents(self::$testCreateDir . "/invalidFile.txt", $this->dumper->dump(["badKey" => "this shouldnt be parsed"]));

        $this->configLoader->load();

        $this->assertFalse(array_key_exists("badKey", $this->configLoader->getConfiguration()));
    }
    
    public function testRequiredKeysThrowsExceptionIfNotFound()
    {
        $envs = [
            "environment" => "dev"
        ];
        
        file_put_contents(self::$testCreateDir . "/global.yml", $this->dumper->dump($envs));
        
        $this->setExpectedException("ConfigLoader\Exception\NonExistentEnvironmentKeyException");
        
        $this->configLoader->load();
    }
    
    public function testGlobalConfigYamlFileNotRequiredIfEnvsManuallySet()
    {
        unlink(self::$testCreateDir . "/global.yml");
        
        $this->configLoader->setEnvironment("dev");
        $this->configLoader->setPossibleEnvironments(["dev", "sandbox"]);
        $this->configLoader->load();
        
        /** If we get this far, and exception not thrown, that's what we want! **/
        $this->assertTrue(true);
    }
    
    public function testRequiredKeysThrowsExceptionWhenKeyNotFound()
    {
        $this->configLoader->setEnvironment("dev");
        $this->configLoader->setPossibleEnvironments(["dev", "sandbox"]);
        $this->configLoader->setRequiredKeys(["keyNotHere"]);        
        
        $this->setExpectedException("ConfigLoader\Exception\NonExistentRequiredConfigKeyException");
        
        $this->configLoader->load();
    }
    
    public function testShortcutGetConfigurationMethod()
    {
        $envs = [
            "environment" => "dev",
            "required_environments" => ["dev", "sandbox"]
        ];
        
        file_put_contents(self::$testCreateDir . "/global.yml", $this->dumper->dump($envs));
        
        $this->configLoader->load();
        $this->assertTrue($this->configLoader->getConfig() === $this->configLoader->getConfiguration());
    }
    
    public function testTravisFailsThis()
    {
        $this->assertTrue(false);
    }
 
    public static function tearDownAfterClass()
    {   
        /** Delete global.yml file **/
        if (is_file(self::$testCreateDir . "/global.yml") && !unlink(self::$testCreateDir . "/global.yml"))
        {
            echo sprintf("Couldn't delete global config file: %s/global.yml. Please delete this manually before continuing. %s", self::$testCreateDir, PHP_EOL);
        }
        
        /** Delete individual test files **/
        $testFiles = self::$staticTestFiles;
        
        foreach ($testFiles as $file)
        {
            if (is_file(sprintf("%s/%s", self::$testCreateDir, $file)) && !unlink(sprintf("%s/%s", self::$testCreateDir, $file)))
            {
                echo sprintf("Couldn't delete the created file: %s/%s. Please delete this manually before continuing. %s", self::$testCreateDir, $file, PHP_EOL);
            }
        }
        
        /** Delete invalid file **/
        if (is_file(self::$testCreateDir . "/invalidFile.txt") && !unlink(self::$testCreateDir . "/invalidFile.txt"))
        {
            echo sprintf("Couldn't delete %s/invalidFile.txt. Please delete this manually before continuing. %s", self::$testCreateDir, PHP_EOL);
        }
        
        /** Delete test directory **/
        if (is_dir(self::$testCreateDir) && !rmdir(self::$testCreateDir))
        {
            echo sprintf("Couldn't delete the created dir: %s. Please delete this manually before continuing. %s", self::$testCreateDir, PHP_EOL);
        }
    }
}