<?php
/**
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

require __DIR__ . '/../../../../lib/composer/autoload.php';

use Behat\Behat\Hook\Scope\BeforeScenarioScope;

class CommandLineContext implements \Behat\Behat\Context\Context {
	use CommandLine;

	private $lastTransferPath;

	private $featureContext;

	public function __construct($ocPath, $baseUrl) {
		$this->ocPath = rtrim($ocPath, '/') . '/';
		$this->localBaseUrl = $baseUrl;
		$this->remoteBaseUrl = $baseUrl;
	}

	/** @BeforeScenario */
	public function gatherContexts(BeforeScenarioScope $scope) {
		$environment = $scope->getEnvironment();
		// this should really be "WebDavContext" ...
		$this->featureContext = $environment->getContext('FeatureContext');
	}

	private function findLastTransferFolderForUser($sourceUser, $targetUser) {
		$foundPaths = [];
		$results = $this->featureContext->listFolder($targetUser, '', 1);
		foreach ($results as $path => $data) {
			$path = rawurldecode($path);
			$parts = explode(' ', $path);
			if (basename($parts[0]) !== 'transferred') {
				continue;
			}
			if (isset($parts[2]) && $parts[2] === $sourceUser) {
				// store timestamp as key
				$foundPaths[] = [
					'date' => strtotime(trim($parts[4], '/')),
					'path' => $path,
				];
			}
		}

		if (empty($foundPaths)) {
			return null;
		}

		usort($foundPaths, function($a, $b) {
			return $a['date'] - $b['date'];
		});

		$davPath = rtrim($this->featureContext->getDavFilesPath($targetUser), '/');

		$foundPath = end($foundPaths)['path'];
		// strip dav path
		return substr($foundPath, strlen($davPath) + 1);
	}

	/**
	 * @When /^transfering ownership from "([^"]+)" to "([^"]+)"/
	 */
	public function transferingOwnership($user1, $user2) {
		if ($this->runOcc(['files:transfer-ownership', $user1, $user2]) === 0) {
			$this->lastTransferPath = $this->findLastTransferFolderForUser($user1, $user2);
		} else {
			// failure
			$this->lastTransferPath = null;
		}
	}

	/**
	 * @When /^transfering ownership of path "([^"]+)" from "([^"]+)" to "([^"]+)"/
	 */
	public function transferingOwnershipPath($path, $user1, $user2) {
		$path = '--path=' . $path;
		if($this->runOcc(['files:transfer-ownership', $path, $user1, $user2]) === 0) {
			$this->lastTransferPath = $this->findLastTransferFolderForUser($user1, $user2);
		} else {
			// failure
			$this->lastTransferPath = null;
		}
	}


	/**
	 * @When /^using received transfer folder of "([^"]+)" as dav path$/
	 */
	public function usingTransferFolderAsDavPath($user) {
		$davPath = $this->featureContext->getDavFilesPath($user);
		$davPath = rtrim($davPath, '/') . $this->lastTransferPath;
		$this->featureContext->usingDavPath($davPath);
	}
}
