<?php

/**
 * ActivityTaskCompleted event.
 *
 * Note that this is one of the ActivityTask* tasks and extends ActivityTask.
 *
 * @package amazon-bundle
 * @copyright Underground Elephant
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

namespace Uecode\Bundle\AmazonBundle\Component\SimpleWorkflow\HistoryEvent;

use \Uecode\Bundle\AmazonBundle\Component\SimpleWorkFlow\HistoryEvent\ActivityTask;
use \Uecode\Bundle\AmazonBundle\Component\SimpleWorkFlow\DeciderWorker;
use \Uecode\Bundle\AmazonBundle\Component\SimpleWorkFlow\Decision;

/**
 * ActivityTaskCompleted event.
 *
 * Note that this is one of the ActivityTask* tasks and extends ActivityTask.
 *
 * @package amazon-bundle
 * @copyright Underground Elephant
 * @author John Pancoast
 */
class ActivityTaskCompleted extends ActivityTask
{
	protected $eventType = 'ActivityTaskCompleted';
}