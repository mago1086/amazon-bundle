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
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Process\Process;

// Amazon Classes
use \AmazonSWF;
use \CFRuntime;

class CronCommand extends ContainerAwareCommand
{
	protected function configure() {
		$this
			->setName('ue:aws:simpleworkflow:cron')
			->setDescription('Start and stop decider and activity workers based on counts that are specified in your config. You specify the counts for your deciders and activity task lists. The values are respectively "uecode.amazon.simpleworkflow.domains.[domain].cron.deciders.[name].count" and "uecode.amazon.simpleworkflow.domains.[domain].cron.activities.[task_list].count". It\'s suggested that you run this script from cron every 2 minutes. NOTE THAT THIS COMMAND IS TEMPORARY AND WE HAVE FUTURE PLANS FOR PROC MGMT. ADDITIONALLY, THERE EXISTS A RACE CONDITION IF YOU RUN THIS SCRIPT OFTEN, HOWEVER, IT IS RELATIVELY HARMLESS AND IRRELEVANT IF YOU LET CRON RUN THIS SCRIPT.')
			->addOption(
				'update',
				'u',
				InputOption::VALUE_NONE,
				'Reload values from config and start/stop workers based on the config counts.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		// for now all we care about is the --update option but eventually
		// maybe user can set counts manually (hence the requirement for the update option).
		$update = $input->getOption('update');
		if (!$update) {
			$output->writeln('Did nothing...');
			return;
		}

		$application = $this->getApplication();
		$kernel = $application->getKernel();
		$container = $kernel->getContainer();

		$rootDir = $kernel->getRootDir();
		//$logger = $container->get('logger');

		$cfg = $container->get('uecode.amazon')
		  ->getFactory('ue')
		  ->getConfig()
		  ->get('simpleworkflow');

		foreach ($cfg['domains'] as $domain => $v) {
			if (isset($cfg['domains'][$domain]['cron']['deciders'])) {
				foreach ($cfg['domains'][$domain]['cron']['deciders'] as $name => $value) {
					$procStr = "console ue:aws:simpleworkflow:deciderworker $domain $name";

					$pids = array();
					$process = new Process('ps -ef | grep "'.$procStr.'" | grep -v grep | awk \'{print $2}\'');
					$process->setTimeout(5);
					$process->run();

					if (!$process->isSuccessful()) {
						throw new \Exception($process->getErrorOutput());
					}

					foreach (explode("\n", $process->getOutput()) as $line) {
						if (is_numeric($line)) {
							$pids[] = $line;
						}
					}

					$cnt = count($pids);

					// kill processes
					if ($cnt > $value['count']) {
						$output->writeln('Killing '.($cnt-$value['count']).' decider workers');
						$killed = array();
						for ($i = 0, $cnt; $cnt > $value['count']; --$cnt, ++$i) {
							$pid = $pids[$i];
							$process = new Process("kill $pid");
							$process->setTimeout(5);
							$process->run();
							if (!$process->isSuccessful()) {
								throw new \Exception($process->getErrorOutput());
							}

							$killed[] = $pid;
						}

						$output->writeln("Sent a SIGTERM signal to the following PIDs. They will finish their current job before exiting:\n".implode(', ', $killed));

					// start processes
					} elseif ($cnt < $value['count']) {
						$output->writeln('Starting '.($value['count']-$cnt).' decider workers.');
						for (; $cnt < $value['count']; ++$cnt) {
							exec(escapeshellcmd("$rootDir/$procStr").' > /dev/null &');
						}
					}
				}
			}

			if (isset($cfg['domains'][$domain]['cron']['activities'])) {
				foreach ($cfg['domains'][$domain]['cron']['activities'] as $tasklist => $value) {
					$procStr = "console ue:aws:simpleworkflow:activityworker $domain $tasklist";

					$pids = array();
					$process = new Process('ps -ef | grep "'.$procStr.'" | grep -v grep | awk \'{print $2}\'');
					$process->setTimeout(5);
					$process->run();
					if (!$process->isSuccessful()) {
						throw new \Exception($process->getErrorOutput());
					}

					foreach (explode("\n", $process->getOutput()) as $line) {
						if (is_numeric($line)) {
							$pids[] = $line;
						}
					}

					$cnt = count($pids);

					// kill processes
					if ($cnt > $value['count']) {
						$output->writeln('Killing '.($cnt-$value['count']).' activity workers');

						$killed = array();
						for ($i = 0, $cnt; $cnt > $value['count']; --$cnt, ++$i) {
							$pid = $pids[$i];
							$process = new Process("kill $pid");
							$process->setTimeout(5);
							$process->run();
							if (!$process->isSuccessful()) {
								throw new \Exception($process->getErrorOutput());
							}

							$killed[] = $pid;
						}

						$output->writeln("Sent a SIGTERM signal to the following PIDs. They will finish their current job before exiting:\n".implode(', ', $killed));
					// start processes
					} elseif ($cnt < $value['count']) {
						$output->writeln('Starting '.($value['count']-$cnt).' activity workers.');
						for (; $cnt < $value['count']; ++$cnt) {
							exec(escapeshellcmd("$rootDir/$procStr").' > /dev/null &');
						}
					}
				}
			}
		}
	}
}