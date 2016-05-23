<?php
/**
 * @author Georg Ehrke <georg@owncloud.com>
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

namespace OCA\Files\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Controller;
use OCP\Files\NotFoundException;
use OCP\Files\Search\IIndexer;
use OCP\IConfig;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\Files\Folder;
use OCP\IUserSession;
use \OCP\Files\IRootFolder;
use OCP\Search\ScoredResult;
use Zend_Search_Lucene_Search_Query_Boolean;
use Zend_Search_Lucene_Search_Query_Phrase;
use Zend_Search_Lucene_Search_Query_MultiTerm;
use Zend_Search_Lucene_Search_Query_Term;
use Zend_Search_Lucene_Index_Term;


/**
 * Class SearchController
 *
 * @package OCA\Files\Controller
 */
class SearchController extends Controller {

	/** IUserSession */
	private $userSession;
	/** string */
	private $userId;
	/** IConfig */
	private $config;
	/** IRootFolder */
	protected $rootFolder;

	/**
	 * SearchController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IUserSession $userSession
	 * @param IConfig $config
	 * @param IRootFolder $rootFolder
	 */
	public function __construct($appName,
								IRequest $request,
								IUserSession $userSession,
								IConfig $config,
								IRootFolder $rootFolder) {
		parent::__construct($appName, $request);
		$this->userSession = $userSession;
		$this->config = $config;
		$this->rootFolder = $rootFolder;

		$user = $this->userSession->getUser();
		if (!$user) {
			// TODO - throw some exception
		}

		$this->userId = $user->getUID();
	}

	/**
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 *
	 * @param string $phrase phrase to search
	 * @param string $path limit path to search
	 * @param integer $page page of pagination
	 * @param integer $size size of pagination
	 */
	public function search($phrase, $path='/', $page=null, $size=null) {
		if ($path === '') {
			$path = '/';
		}
		$path = '/' . $this->userId . '/files' . $path;

		try {
			$query = $this->getQuery($phrase, $path);
		} catch(\Exception $ex) {
			return;
			// TODO Handle exception
		}

		$indexStorageMap = [];

		// Collect mounts to search
		try {
			$mounts = $this->rootFolder->getMountsIn($path);
			foreach($mounts as $mount) {
				/** @var \OCP\Files\Mount\IMountPoint $mount */
				/** @var \OCP\Files\Storage $storage */
				$storage = $mount->getStorage();
				$indexerIdentifier = $storage->getIndexerIdentifier();

				if (!isset($indexStorageMap[$indexerIdentifier])) {
					$indexStorageMap[$indexerIdentifier] = [];
				}
				$indexStorageMap[$indexerIdentifier][] = $storage;
			}
		} catch(NotFoundException $ex) {

		}

		$indexers = [];
		foreach($indexStorageMap as $singleIndexer => $storages) {
			if (!class_exists($singleIndexer)) {
				continue;
			}

			try {
				/** @var IIndexer $instance */
				$instance = new $singleIndexer();
				$instance->setStorages($storages);
				$indexers[] = $instance;
			} catch(\Exception $ex) {
				//TODO
			}
		}

		// Search Indexers from 0 to $page + $size
		// Some better approximation of what $page should look like
		// should be added in a later version
		$indexerSize = $page + $size;

		$results = [];
		foreach($indexers as $indexer) {
			/** @var IIndexer $indexer */
			$results = array_merge($results, $indexer->search($query, 0, $indexerSize));
		}

		usort($results, function(ScoredResult $a, ScoredResult $b) {
			return ($a->score < $b->score) ? -1 : 1;
		});
		$results = array_slice($results, $page, $size);
		
	}

	protected function getQuery($phrase, $path) {
		$query = new Zend_Search_Lucene_Search_Query_Boolean();

		$this->addPathQuery($query, $path);
		$this->addPhraseQuery($query, $phrase);
		$this->addUserQuery($query);

		return $query;
	}

	/**
	 * @param Zend_Search_Lucene_Search_Query_Boolean $query
	 * @param string $path
	 */
	protected function addPathQuery($query, $path) {
		$indexTerm = new Zend_Search_Lucene_Index_Term($path, 'child_of');
		$queryTerm = new Zend_Search_Lucene_Search_Query_Term($indexTerm);
		$query->addSubquery($queryTerm, true);
	}

	/**
	 * @param Zend_Search_Lucene_Search_Query_Boolean $query
	 * @param string $phrase
	 */
	protected function addPhraseQuery($query, $phrase) {
		$parts = explode(' ', $phrase);

		$phraseQuery = new Zend_Search_Lucene_Search_Query_Phrase($parts);
		$query->addSubquery($phraseQuery, true);
	}

	/**
	 * @param Zend_Search_Lucene_Search_Query_Boolean $query
	 */
	protected function addUserQuery($query) {
		$user = $this->userSession->getUser();
		if (!$user) {
			// TODO - throw some exception
		}

		$userId = $user->getUID();
		$indexTerm = new Zend_Search_Lucene_Index_Term($userId, 'userid');
		$queryTerm = new Zend_Search_Lucene_Search_Query_Term($indexTerm);
		$query->addSubquery($queryTerm, true);
	}
}
