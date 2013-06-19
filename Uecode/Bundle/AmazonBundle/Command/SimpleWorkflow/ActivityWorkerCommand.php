<?php

/**
 * Start an activity worker.
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

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;

// Amazon Classes
use \AmazonSWF;
use \CFRuntime;

class ActivityWorkerCommand extends ContainerAwareCommand
{
	protected function configure() {
		$this
			->setName('ue:aws:swf:activity_worker')
			->setDescription('Start an activity worker which will poll amazon for an activity task.')
			->addArgument(
				'domain',
				InputArgument::REQUIRED,
				'The SWF workflow domain config key.'
			)
			->addArgument(
				'tasklist',
				InputArgument::REQUIRED,
				'The SWF activity tasklist'
			)
			->addOption(
				'identity',
				null,
				InputOption::VALUE_REQUIRED,
				'The SWF activity identity'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$container = $this->getApplication()->getKernel()->getContainer();

		$logger = $container->get('logger');

		try {
			$logger->log(
				'debug',
				'About to start activity worker'
			);

			$amazonFactory = $container->get( 'uecode.amazon' )->getFactory( 'ue' );

			$domain = $input->getArgument('domain');
			$taskList = $input->getArgument('tasklist');
			$identity = $input->getOption('identity');

			$logger->log(
				'info',
				'Starting activity worker',
				array(
					'domain' => $domain,
					'taskList' => $taskList,
					'identity' => $identity,
				)
			);

			$swf = $amazonFactory->build('AmazonSWF', array('domain' => $domain), $container);
			$activity = $swf->loadActivity($taskList, $identity);

			// note that run() will sit in an infinite loop unless this process is killed.
			// it's better to use SIGHUP, SIGINT, or SIGTERM than SIGKILL since the workers
			// have signal handlers.
			$activity->run();

			$output->writeln('exiting');

			$activity->log(
				'info',
				'Activity worker ended'
			);
		} catch (\Exception $e) {
			try {
				$logger->log(
					'critical',
					'Caught exception: '.$e->getMessage(),
					array(
						'trace' => $e->getTrace()
					)
				);
			// if that failed... then... damn.
			} catch (Exception $e) {
				$output->writeln('EXCEPTION: '.$e->getMessage());
				$output->writeln(print_r($e, true));
			}
		}
	}
}
