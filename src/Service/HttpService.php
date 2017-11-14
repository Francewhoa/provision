<?php
/**
 * @file
 * The base Provision HttpService class.
 *
 * @see \Provision_Service_http
 */

namespace Aegir\Provision\Service;

//require_once DRUSH_BASE_PATH . '/commands/core/rsync.core.inc';

use Aegir\Provision\Service;
use Aegir\Provision\ServiceSubscription;
use Consolidation\AnnotatedCommand\CommandFileDiscovery;

/**
 * Class HttpService
 *
 * @package Aegir\Provision\Service
 */
class HttpService extends Service {
  const SERVICE = 'http';
  const SERVICE_NAME = 'Web Server';

  protected $ssl_enabled = FALSE;


    /**
     * Implements Service::server_options()
     *
     * @return array
     */
    static function server_options()
    {
        return [
            'http_port' => 'The port which the web service is running on.',
            'web_group' => 'server with http: OS group for permissions; working default will be attempted',
            'web_disable_url' => 'server with http: URL disabled sites are redirected to; default {master_url}/hosting/disabled',
            'web_maintenance_url' => 'server with http: URL maintenance sites are redirected to; default {master_url}/hosting/maintenance',
            'restart_command' => 'The command to reload the web server configuration;'
        ];
    }
    
    /**
     * List context types that are allowed to subscribe to this service.
     * @return array
     */
    static function allowedContexts() {
        return [
            'platform'
        ];
    }

    /**
     * React to `provision verify` command when run on a subscriber, to verify the service's provider.
     *
     * This is used to allow skipping of the service restart.
     */
    function verifyProvider()
    {
        return [
            'configuration' => $this->writeConfigurations(),
        ];
    }

    /**
     * React to the `provision verify` command on Server contexts
     */
    function verifySubscription(ServiceSubscription $serviceSubscription) {
        $this->subscription = $serviceSubscription;
        return [
            'configuration' => $this->writeConfigurations($serviceSubscription),
            'service' => $this->restartService(),
        ];
    }
//
//    /**
//   * Support the ability to cloak the database credentials using environment variables.
//   */
//  function cloaked_db_creds() {
//    return FALSE;
//  }
//
//
//  function verify_server_cmd() {
//    $this->create_config($this->context->type);
//    $this->parse_configs();
//  }
//
//  function verify_platform_cmd() {
//    $this->create_config($this->context->type);
//    $this->parse_configs();
//  }
//
//  function verify_site_cmd() {
//    $this->create_config($this->context->type);
//    $this->parse_configs();
//  }
//
//
//  /**
//   * Register the http handler for platforms, based on the web_server option.
//   */
//  static function subscribe_platform($context) {
//    $context->setProperty('web_server', '@server_master');
//    $context->is_oid('web_server');
//    $context->service_subscribe('http', $context->web_server->name);
//  }

}
