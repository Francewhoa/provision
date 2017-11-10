<?php
/**
 * @file
 * Provides the Aegir\Provision\Context class.
 */

namespace Aegir\Provision;

use Aegir\Provision\Console\Config;
use Consolidation\AnnotatedCommand\CommandFileDiscovery;
use Drupal\Console\Core\Style\DrupalStyle;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Yaml;

/**
 * Class Context
 *
 * Base context class.
 *
 * @package Aegir\Provision
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
    const TYPE = null;

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
     * @var array
     * A list of services provided by this server.
     */
    protected $services = [];

    /**
     * @var \Aegir\Provision\Application;
     */
    public $application;
    
    /**
     * @var LoggerInterface
     */
    public $logger;

    /**
     * Context constructor.
     *
     * @param $name
     * @param array $options
     */
    function __construct($name, Application $application = NULL, $options = [])
    {
        $this->name = $name;
        $this->application = $application;
        $this->loadContextConfig($options);
        $this->prepareServices();
    }

    /**
     * Load and process the Config object for this context.
     *
     * @param array $options
     *
     * @throws \Exception
     */
    private function loadContextConfig($options = []) {

        if ($this->application) {
            $this->config_path = $this->application->getConfig()->get('config_path') . '/provision/' . $this->type . '.' . $this->name . '.yml';
        }
        else {
            $config = new Config();
            $this->config_path = $config->get('config_path') . '/provision/' . $this->type . '.' . $this->name . '.yml';
        }

        $configs = [];

        try {
            $processor = new Processor();
            if (file_exists($this->config_path)) {
                $this->properties = Yaml::parse(file_get_contents($this->config_path));
            }
            else {
                // Load command line options into properties
                foreach ($this->option_documentation() as $option => $description) {
                    $this->properties[$option] = $options[$option];
                }
            }
            $configs[] = $this->properties;

            $this->properties['context_type'] = $this->type;

            $this->config = $processor->processConfiguration($this, $configs);
            
        } catch (\Exception $e) {
            throw new \Exception(
                strtr("There is an error with the configuration for !type '!name'. Check the file !file and try again. \n \nError: !message", [
                    '!type' => $this->type,
                    '!name' => $this->name,
                    '!message' => $e->getMessage(),
                    '!file' => $this->config_path,
                ])
            );
        }
    }

    /**
     * Load Service classes from config into Context..
     */
    protected function prepareServices() {
        // Only servers have config['services']
        if (isset($this->config['services'])) {

            foreach ($this->config['services'] as $service_name => $service) {
                $service_class = Service::getClassName($service_name, $service['type']);
                $this->services[$service_name] = new $service_class($service, $this);
            }
        }
        // @TODO: Since we switched to Config ContextNodeDefinition, it's not returning the context name in $context. So we have to look for $this->properties instead of $this->config.
        // We should figure out how to alter ContextNodeDefinition so it returns the string name or the server class.
        elseif (isset($this->properties['service_subscriptions'])) {
            foreach ($this->properties['service_subscriptions'] as $service_name => $service) {
                // Check for an empty server.
                if (empty($service['server'])) {
                  throw new \Exception("Item 'server' cannot be empty.");
                }
                $this->servers[$service_name] = $server = Application::getContext($service['server']);
                $this->services[$service_name] = new ServiceSubscription($this, $server, $service_name);
            }
        }
    }

    /**
     * Loads all available \Aegir\Provision\Service classes
     *
     * @return array
     */
    static public function findAvailableServices($service = '') {

        // Load all service classes
        $classes = [];
        $discovery = new CommandFileDiscovery();
        $discovery->setSearchPattern('*Service.php');
        $servicesFiles = $discovery->discover(__DIR__ .'/Service', '\Aegir\Provision\Service');
        foreach ($servicesFiles as $serviceClass) {
            // If this is a server, show all services. If it is not, but service allows this type of context, load it.
//            if (self::TYPE == 'server' || in_array(self::TYPE, $serviceClass::allowedContexts())) {
                $classes[$serviceClass::SERVICE] = $serviceClass;
//            }
        }
        return $classes;
//
//        if ($service && isset($classes[$service])) {
//            return $classes[$service];
//        }
//        elseif ($service && !isset($classes[$service])) {
//            throw new \Exception("No service with name $service was found.");
//        }
//        else {
//            return $classes;
//        }
    }

    /**
     * @param string $service
     */
    public function getAvailableServices($service = '')
    {
        $services_return = [];
        $services = $this->findAvailableServices();
        foreach ($services as $serviceClass) {
            // If this is a server, show all services. If it is not, but service allows this type of context, load it.
            if ($this::TYPE == 'server' || in_array($this::TYPE, $serviceClass::allowedContexts())) {
                $services_return[$serviceClass::SERVICE] = $serviceClass;
            }
        }

        if ($service && isset($services_return[$service])) {
            return $services_return[$service];
        }
        elseif ($service && !isset($services_return[$service])) {
            throw new \Exception("No service with name $service was found.");
        }
        else {
            return $services_return;
        }
    }
    /**
     * Lists all available services as a simple service => name array.
     * @return array
     */
    static public function getServiceOptions() {
        $options = [];
        $services = self::findAvailableServices();
        foreach ($services as $service => $class) {
            $options[$service] = $class::SERVICE_NAME;
        }
        return $options;
    }

    /**
     * @return array
     */
    static function getAvailableServiceTypes($service, $service_type = NULL) {

        // Load all service classes
        $classes = [];
        $discovery = new CommandFileDiscovery();
        $discovery->setSearchPattern(ucfirst($service) . '*Service.php');
        $serviceTypesFiles = $discovery->discover(__DIR__ .'/Service/' . ucfirst($service), '\Aegir\Provision\Service\\' . ucfirst($service));
        foreach ($serviceTypesFiles as $serviceTypeClass) {
            $classes[$serviceTypeClass::SERVICE_TYPE] = $serviceTypeClass;
        }

        if ($service_type && isset($classes[$service_type])) {
            return $classes[$service_type];
        }
        elseif ($service_type && !isset($classes[$service_type])) {
            throw new \Exception("No service type with name $service_type was found.");
        }
        else {
            return $classes;
        }
    }

    /**
     * Lists all available services as a simple service => name array.
     * @return array
     */
    static public function getServiceTypeOptions($service) {
        $options = [];
        $service_types = self::getAvailableServiceTypes($service);
        foreach ($service_types as $service_type => $class) {
            $options[$service_type] = $class::SERVICE_TYPE_NAME;
        }
        return $options;
    }

    /**
     * Lists all available context types as a simple service => name array.
     *
     * @TODO: Make this dynamically load from classes, like getServiceTypeOptions()
     * @return array
     */
    static public function getContextTypeOptions() {
        return [
            'server' => 'Server',
            'platform' => 'Platform',
            'site' => 'Site',
        ];
    }


    /**
     * Return all services for this context.
     *
     * @return array
     */
    public function getServices() {
        return $this->services;
    }

    /**
     * Return all services for this context.
     *
     * @return array
     */
    public function getService($type) {
        if (isset($this->services[$type])) {
            return $this->services[$type];
        }
        else {
            throw new \Exception("Service '$type' does not exist in the context '{$this->name}'.");
        }
    }
    
    /**
     * Return all services for this context.
     *
     * @return \Aegir\Provision\Service
     */
    public function service($type)
    {
        return $this->getService($type);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $tree_builder = new TreeBuilder();
        $root_node = $tree_builder->root($this->type);
        $root_node
            ->children()
                ->scalarNode('name')
                    ->defaultValue($this->name)
                ->end()
            ->end();

        // Load Services.
        if ($this->type == 'server') {
            $root_node
                ->attribute('context', $this)
                ->children()
                    ->arrayNode('services')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('type')
                            ->isRequired(true)
                        ->end()
                        ->append($this->addServiceProperties('services'))
                    ->end()
                ->end();
        }
        // Load service subscriptions.
        else {
            $root_node
                ->attribute('context', $this)
                ->children()
                    ->arrayNode('service_subscriptions')
                    ->prototype('array')
                        ->children()
                        ->setNodeClass('context', 'Aegir\Provision\ConfigDefinition\ContextNodeDefinition')
                        ->node('server', 'context')
                            ->isRequired()
                            ->attribute('context_type', 'server')
                        ->end()
                        ->append($this->addServiceProperties('service_subscriptions'))
                    ->end()
                ->end();
        }

        // @TODO: Figure out how we can let other classes add to Context properties.
        foreach ($this->option_documentation() as $name => $description) {
            $root_node
                ->children()
                    ->scalarNode($name)
                        ->defaultValue($this->properties[$name])
                    ->end()
                ->end();
        }
    
        // Load contextRequirements into config as ContextNodes.
        foreach ($this->contextRequirements() as $property => $type) {
            $root_node
                ->children()
                    ->setNodeClass('context', 'Aegir\Provision\ConfigDefinition\ContextNodeDefinition')
                    ->node($property, 'context')
                        ->isRequired()
                        ->attribute('context_type', $type)
                    ->end()
                ->end();
        }
        
        if (method_exists($this, 'configTreeBuilder')) {
            $this->configTreeBuilder($root_node);
        }

        return $tree_builder;
    }

    /**
     * Append Service class options_documentation to config tree.
     */
    public function addServiceProperties($property_name = 'services')
    {
        $builder = new TreeBuilder();
        $node = $builder->root('properties');

        // Load config tree from Service type classes
        if (!empty($this->getProperty($property_name)) && !empty($this->getProperty($property_name))) {
            foreach ($this->getProperty($property_name) as $service => $info) {

                // If type is empty, it's because it's in the ServerContext
                if (empty($info['type'])) {
                    $server = Application::getContext($info['server']);
                    $service_type = ucfirst($server->getService($service)->type);
                }
                else {
                    $service_type = ucfirst($info['type']);
                }
                $class = Service::getClassName($service, $service_type);
                $method = "{$this->type}_options";

                foreach ($class::{$method}() as $name => $description) {
                    $node
                        ->children()
                        ->scalarNode($name)->end()
                        ->end()
                        ->end();
                }
            }
        }
        return $node;
    }

    /**
     * Output a list of all services for this context.
     */
    public function showServices(DrupalStyle $io) {
        if (!empty($this->getServices())) {
            $is_server = $this->type == 'server';
            $rows = [];
            
            $headers = $is_server?
                ['Services']:
                ['Service', 'Server', 'Type'];
            
            foreach ($this->getServices() as $name => $service) {
                if ($is_server) {
                    $rows[] = [$name, $service->type];
                }
                else {
                    $rows[] = [
                        $name,
                        $service->server->name,
                        $service->server->getService($name)->type
                    ];
                }

                // Show all properties.
                if (!empty($service->properties )) {
                    foreach ($service->properties as $name => $value) {
                        $rows[] = ['  ' . $name, $value];
                    }
                }
            }
            $io->table($headers, $rows);
        }
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
        if (isset($this->properties[$name])) {
            return $this->properties[$name];
        }
        else {
          return NULL;
        }
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
    
    /**
     * Retrieve the class name of a specific context type.
     *
     * @param $type
     *   The type of context, typically server, platform, site.
     *
     * @return string
     *   The fully-qualified class name for this type.
     */
    static function getClassName($type) {
        return '\Aegir\Provision\Context\\' . ucfirst($type) . "Context";
    }

//    public function verify() {
//        return "Provision Context";
//    }
    
    /**
     * Verify this context.
     *
     * Running `provision verify CONTEXT` triggers this method.
     *
     * Collect all services for the context and run the verify() method on them
     */
    public function verify() {
        $return_codes = [];
        // Run verify method on all services.
        foreach ($this->getServices() as $service) {
            $friendlyName = $service->getFriendlyName();
            // If service is a service subscription
            if (get_class($service) == ServiceSubscription::class) {
                $prep = 'for';
            }
            else {
                $prep = 'on';
            }
            $this->application->io->title("Verify {$friendlyName} {$prep} {$service->context->name}");
            $return_codes[] = $service->verify()? 0: 1;
        }
        
        // If any service verify failed, exit with a non-zero code.
        if (count(array_filter($return_codes))) {
            throw new \Exception('Some services did not verify. Check your configuration and try again.');
        }
    }

    /**
     * Return an array of required services for this context.
     * Example:
     *   return ['http'];
     */
    public static function serviceRequirements() {
        return [];
    }

    /**
     * Return an array of required contexts in the format PROPERTY_NAME => CONTEXT_TYPE.
     *
     * Example:
     *   return ['platform' => 'platform'];
     */
    public static function contextRequirements() {
        return [];
    }

//    /**
//     * Check that there is at least one server that provides for each serviceRequirements().
//     *
//     * @return array
//     *   The key is the service, the value is 0 if not available or 1 if is available.
//     */
//    function checkRequirements() {
//        $reqs = self::serviceRequirements();
//        $services = [];
//        foreach ($reqs as $service) {
//            if (empty($this->application->getAllServers($service))) {
//                $services[$service] = 0;
//            }
//            else {
//                $services[$service] = 1;
//            }
//        }
//        return $services;
//    }
}
