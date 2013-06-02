<?php

/**
 * Start a decider worker.
 *
 * @package amazon-bundle
 * @copyright (c) 2013 Underground Elephant
 * @author John Pancoast
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

namespace Uecode\Bundle\AmazonBundle\Command\SimpleWorkflow;

//use Symfony\Component\Console\Command\Command;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;

// Amazon Classes
use \AmazonSWF;
use \CFRuntime;

class DeciderWorkerCommand extends ContainerAwareCommand
{
	protected function configure() {
		$this
			->setName('ue:aws:simpleworkflow:deciderworker')
			->setDescription('Start a decider worker which will poll amazon for a decision task. The "domain" and "name" arguments are required and they both specify config params at uecode.amazon.simpleworkflow.domains.[<domain>].workflows.[<name>]. The rest of the config values can be overridden w/ their respective options to this command.')
			->addArgument(
				'domain',
				InputArgument::REQUIRED,
				'The SWF workflow domain.'
			)
			->addArgument(
				'name',
				InputArgument::REQUIRED,
				'The SWF workflow name.'
			)
			->addOption(
				'workflow_version',
				null,
				InputOption::VALUE_REQUIRED,
				'The SWF workflow version.'
			)
			->addOption(
				'taskList',
				null,
				InputOption::VALUE_REQUIRED,
				'The SWF workflow taskList'
			)
			->addOption(
				'event_namespace',
				null,
				InputOption::VALUE_REQUIRED,
				'Where your event classes are located'
			)
			->addOption(
				'activity_event_namespace',
				null,
				InputOption::VALUE_REQUIRED,
				'Where your activity classes are located'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$container = $this->getApplication()->getKernel()->getContainer();

		$logger = $container->get('logger');

		try {
			// default values
			$version = null;
			$taskList = null;
			$eventNamespace = null;
			$activityNamespace = null;

			$logger->log(
				'info',
				'About to start decider worker'
			);

			$amazonFactory = $container->get('uecode.amazon')->getFactory('ue');

			$domain = $input->getArgument('domain');
			$name = $input->getArgument('name');

			$cfg = $amazonFactory->getConfig()->get('simpleworkflow');

			foreach ($cfg['domains'] as $dk => $dv) {
				if ($dk == $domain) {
					foreach ($dv['workflows'] as $kk => $kv) {
						if ($kk == $name) {
							$version = $kv['version'];
							$taskList = $kv['task_list'];
							$eventNamespace = $kv['history_event_namespace'];
							$activityNamespace = $kv['history_activity_event_namespace'];
						}
					}
				}
			}

			// allow config to be overridden by passed values.
			$version = $input->getOption('workflow_version') ?: $version;
			$taskList = $input->getOption('taskList') ?: $taskList;
			$eventNamespace = $input->getOption('event_namespace') ?: $eventNamespace;
			$activityNamespace = $input->getOption('activity_event_namespace') ?: $activityNamespace;

			$logger->log(
				'info',
				'Starting decider worker',
				array(
					'domain' => $domain,
					'name' => $name,
					'version' => $version,
					'taskList' => $taskList,
					'eventNamespace' => $eventNamespace,
					'activityNamespace' => $activityNamespace
				)
			);

			$swf = $amazonFactory->build('AmazonSWF', array('domain' => $domain), $container);
			$decider = $swf->loadDecider($name, $version, $taskList, $eventNamespace, $activityNamespace);

			// note that run() will sit in a loop while(true).
			$decider->run();

			$output->writeln('done');
			$logger->log(
				'info',
				'Decider worker ended'
			);
		} catch (\Exception $e) {
			echo "ERROR: {$e->getMessage()}\n";
			// if this fails... then... damn...
			try {
				$logger->log(
					'error',
					'Caught exception: '.$e->getMessage(),
					array(
						'trace' => $e->getTrace()
					)
				);
			} catch (Exception $e) {
				echo 'EXCEPTION: '.$e->getMessage()."\n";
			}
		}
	}
}
