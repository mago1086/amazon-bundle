<?php

/**
 * @package amazon-bundle
 * @author Aaron Scherer
 * @copyright (c) 2013 Undeground Elephant
 *
 * Copyright 2013 Underground Elephant
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Uecode\Bundle\AmazonBundle\Model;

// Symfony (and related)
use Monolog\Logger;
use Doctrine\DBAL\Connection;

// Models
use \Uecode\Bundle\AmazonBundle\Model\AmazonInterface;

// Exceptions
use \Uecode\Bundle\AmazonBundle\Exception\InvalidConfigurationException;
use \Uecode\Bundle\AmazonBundle\Exception\InvalidClassException;

// Amazon Bundle Components
use \Uecode\Bundle\AmazonBundle\Component\SimpleWorkFlow\DeciderWorker;
use \Uecode\Bundle\AmazonBundle\Component\SimpleWorkFlow\ActivityWorker;

// Uecode Bundle Components
use \Uecode\Bundle\UecodeBundle\Component\Config;

use \AmazonSWF as SWF;

/**
 * @todo this class should encapsulate swf, not extend it.
 */
class SimpleWorkFlow extends SWF implements AmazonInterface
{

	/**
	 * @var bool Defines whether or not initialize() has been ran.
	 */
	private $initialized = false;

	/**
	 * @var Config
	 */
	protected $config;

	/**
	 * @var
	 */
	protected $workflow;

	/**
	 * @var Logger Logger instance
	 * @access  protected
	 */
	protected $logger;

	/**
	 * @var array DB instances
	 */
	private $dbs = array();

	/**
	 * Returns a workflow defined in a config.
	 *
	 * @param string $configKey The config key for the workflow which is relative to uecode.amazon.simpleworkflow.domains.[name].workflows.
	 * @return DeciderWorker
	 */
	public function loadDeciderFromConfig($configKey)
	{
		$cfg = $this->config->get('simpleworkflow');
		foreach ($cfg['domains'] as $dk => $dv) {
			foreach ($dv['workflows'] as $kk => $kv) {
				if ($kk == $configKey) {
					return $this->loadDecider($kv['name'], $kv['version'], $kv['default_task_list'], $kv['event_namespace'], $kv['activity_namespace']);
				}
			}
		}
	}

	/**
	 * Load a decider
	 *
	 * @param string $name
	 * @param float  $version
	 * @param string $taskList
	 * @param string $eventNamespace
	 * @param string $activityNamespace
	 * @param string $deciderClass
	 *
	 * @throws InvalidClassException
	 * @return DeciderWorker
	 */
	public function loadDecider( $name, $version = 1.0, $taskList, $eventNamespace, $activityNamespace, $deciderClass = null )
	{
		$workflowOptions = array(
			'name' => $name,
			'version' => (string)$version,
			'taskList' => array('name' => $taskList),
			'domain' => $this->config->get( 'domain' )
		);

		if( null === $deciderClass ) {
			return new DeciderWorker( $this ,$workflowOptions, $eventNamespace, $activityNamespace );
		} else {
			$worker = new $deciderClass( $this, $workflowOptions, $eventNamespace, $activityNamespace );
			if( !( $worker instanceof DeciderWorker ) ) {
				throw new InvalidClassException( $deciderClass );
			}

			return $worker;
		}
	}

	public function loadActivity($taskList, $identity = null)
	{
		return new ActivityWorker($this, $taskList, $identity);
	}

	public function getActivityArray()
	{
		$config = $this->getConfig();
		$wf = $config->get('simpleworkflow');
		$domain = $config->get('domain');
		foreach ($wf['domains'] as $dk => $dv)
		{
			if ($domain == $dk)
			{
				return $dv['activities'];
			}
		}
	}

	public function getActivityDirectory()
	{
		$ar = $this->getActivityArray();
		return $ar['directory'];
	}

	public function getActivityNamespace()
	{
		$ar = $this->getActivityArray();
		return $ar['namespace'];
	}

	public function debug($str)
	{
		echo $str;
	}

	/**
	 * Initializes the current object
	 *
	 * @param Config $config
	 * @return void
	 */
	public function initialize( Config $config )
	{
		if ( $this->getInitialized() ) {
			return;
		}

		$this->initializeConfigs( $config );
		$this->setInitialized();

		return;
	}

	/**
	 * Initialize Configs
	 *
	 * @param Config $config
	 * @return void
	 */
	function initializeConfigs( Config $config )
	{
		$this->setConfig( $config );
		$this->validateConfigs();
	}


	/**
	 * Validates $this->configs. Should be called within initialize
	 *
	 * @throws InvalidConfigurationException
	 * @return bool
	 */
	public function validateConfigs()
	{
		if ( !$this->config->has( 'domain' ) ) {
			throw new  InvalidConfigurationException( "Domain must be specified in this config." );
		}
	}

	/**
	 * Should be called at the end of initialize to show that the class has been initialized.
	 *
	 * @param bool $bool
	 * @return void
	 */
	public function setInitialized( $bool = true )
	{
		$this->initialized = $bool;
	}

	/**
	 * Should return whether or not the initialize function has been ran.
	 *
	 * @return bool
	 */
	public function getInitialized()
	{
		return $this->initialized;
	}

	/**
	 * @param Config $config
	 * @return void
	 */
	public function setConfig( Config $config )
	{
		$this->config = $config;
	}

	/**
	 * @return Config
	 */
	public function getConfig()
	{
		return $this->config;
	}

	/**
	 * Set the logger
	 *
	 * @param Logger $logger
	 */
	public function setLogger(Logger $logger)
	{
		$this->logger = $logger;
	}

	/**
	 * Get the logger
	 *
	 * @return Logger
	 */
	public function getLogger()
	{
		return $this->logger;
	}

	/**
	 * A simple static method to add monolog log context for decider/activity workers.
	 *
	 * @param string $type Either 'decider' or 'activity'
	 * @param string $executionId Our execution id
	 * @param string $amazonRunId Amazon's run id
	 * @param string $amazonWorkflowId Amazon's workflow id
	 * @param mixed $data Additional data.
	 * @return array
	 */
	public static function logContext($type, $executionId, $amazonRunId = null, $amazonWorkflowId = null, $data = null)
	{
		return array(
			'type' => ($type == 'decider' || $type == 'activity') ? $type : 'unknown',
			'processId' => getmypid(),
			'executionId' => $executionId,
			'runId' => $amazonRunId,
			'workflowId' => $amazonWorkflowId,
			'data' => $data,
			'date' => date('c')
		);
	}

	/**
	 * Add a DB connection
	 *
	 * @access public
	 * @param string $key The key string of the DB connection
	 * @param string $db The db connection
	 */
	public function addDb($key, Connection $db) {
		$this->dbs[$key] = $db;
	}

	/**
	 * Get a DB connection
	 *
	 * @access public
	 * @param $key The key string of the DB connection to get
	 * @return Doctrine\DBAL\Connection
	 */
	public function getDb($key) {
		return isset($this->dbs[$key]) ? $this->dbs[$key] : null;
	}
}
