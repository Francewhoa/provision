<?php
/**
 * @file
 * Provides the Aegir\Provision\Context class.
 */

namespace Aegir\Provision;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Yaml;

/**
 * Base context class.
 */
class Context
{

    /**
     * @var string
     * Name for saving aliases and referencing.
     */
    public $name = null;

    /**
     * @var string
     * 'server', 'platform', or 'site'.
     */
    public $type = null;

    /**
     * @var string
     * Full path to this context's config file.
     */
    public $config_path = null;

    /**
     * @var array
     * Properties that will be persisted by provision-save. Access as object
     * members, $evironment->property_name. __get() and __set handle this. In
     * init(), set defaults with setProperty().
     */
    protected $properties = [];

    /**
     * Context constructor.
     *
     * @param $name
     * @param $console_config
     * @param array $options
     */
    function __construct($name, $console_config, $options = [])
    {
        $this->name = $name;
        $this->loadContextConfig($console_config, $options);
    }

    /**
     * Load and process the Config object for this context.
     *
     * @param $console_config
     * @param array $options
     *
     * @throws \Exception
     */
    private function loadContextConfig($console_config, $options = []) {
        $this->console_config = $console_config;
        $this->config_path = $console_config['config_path'] . '/provision/' . $this->type . '.' . $this->name . '.yml';

        $configs = [];

        try {
            $processor = new Processor();
            if (file_exists($this->config_path)) {
                $this->properties = Yaml::parse(file_get_contents($this->config_path));
                $configs[] = $this->properties;
            }
            else {
                // Load command line options into properties
                foreach ($this->option_documentation() as $option => $description) {
                    $this->properties[$option] = $options[$option];
                }
            }
            $this->properties['context_type'] = $this->type;

            $this->config = $processor->processConfiguration($this, $configs);
            
        } catch (\Exception $e) {
            throw new \Exception(
                "There is an error with the configuration for $this->type $this->name: " . $e->getMessage()
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $tree_builder = new TreeBuilder();
        $root_node = $tree_builder->root('server');
        $root_node
            ->children()
                ->scalarNode('name')
                    ->defaultValue($this->name)
                ->end()
            ->end();

        // @TODO: Figure out how we can let other classes add to Context properties.
        foreach ($this->option_documentation() as $name => $description) {
            $root_node
                ->children()
                    ->scalarNode($name)
                        ->defaultValue($this->properties[$name])
                    ->end()
                ->end();
        }

        if (method_exists($this, 'configTreeBuilder')) {
            $this->configTreeBuilder($root_node);
        }

        return $tree_builder;
    }

    /**
     * Return all properties for this context.
     *
     * @return array
     */
    public function getProperties() {
        return $this->properties;
    }

    /**
     * Return all properties for this context.
     *
     * @return array
     */
    public function getProperty($name) {
        return $this->properties[$name];
    }

    /**
     * Saves the config class to file.
     *
     * @return bool
     */
    public function save()
    {
        
        // Create config folder if it does not exist.
        $fs = new Filesystem();
        $dumper = new Dumper();
        
        try {
            $fs->dumpFile($this->config_path, $dumper->dump($this->config, 10));
            return true;
        } catch (IOException $e) {
            return false;
        }
    }

    /**
     * Deletes the config YML file.
     * @return bool
     */
    public function deleteConfig() {

        // Create config folder if it does not exist.
        $fs = new Filesystem();

        try {
            $fs->remove($this->config_path);
            return true;
        } catch (IOException $e) {
            return false;
        }
    }

    static function getClassName($type) {
        return '\Aegir\Provision\Context\\' . ucfirst($type) . "Context";
    }

    public function verify() {
        return "Provision Context";
    }
}
