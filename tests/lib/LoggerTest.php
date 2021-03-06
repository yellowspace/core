<?php
/**
 * Copyright (c) 2014 Thomas Müller <thomas.mueller@tmit.eu>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test;

use OC\Log;
use OCP\IConfig;
use OCP\Util;

class LoggerTest extends TestCase {
	/** @var \OCP\ILogger */
	private $logger;
	static private $logs = [];

	/** @var IConfig | \PHPUnit_Framework_MockObject_MockObject */
	private $config;

	protected function setUp() {
		parent::setUp();

		self::$logs = [];
		$this->config = $this->getMockBuilder(
			'\OC\SystemConfig')
			->disableOriginalConstructor()
			->getMock();
		$this->logger = new Log('Test\LoggerTest', $this->config);
	}

	public function testInterpolation() {
		$logger = $this->logger;
		$logger->warning('{Message {nothing} {user} {foo.bar} a}', ['user' => 'Bob', 'foo.bar' => 'Bar']);

		$expected = ['2 {Message {nothing} Bob Bar a}'];
		$this->assertEquals($expected, $this->getLogs());
	}

	public function testAppCondition() {
		$this->config->expects($this->any())
			->method('getValue')
			->will(($this->returnValueMap([
				['loglevel', Util::WARN, Util::WARN],
				['log.condition', [], ['apps' => ['files']]]
			])));
		$logger = $this->logger;

		$logger->info('Don\'t display info messages');
		$logger->info('Show info messages of files app', ['app' => 'files']);
		$logger->warning('Show warning messages of other apps');

		$expected = [
			'1 Show info messages of files app',
			'2 Show warning messages of other apps',
		];
		$this->assertEquals($expected, $this->getLogs());
	}

	private function getLogs() {
		return self::$logs;
	}

	public static function write($app, $message, $level) {
		self::$logs[]= "$level $message";
	}

	public function userAndPasswordData() {
		return [
			['abc', 'def'],
			['mySpecialUsername', 'MySuperSecretPassword'],
			['my-user', '324324()#ä234'],
			['my-user', ')qwer'],
			['my-user', 'qwer)asdf'],
			['my-user', 'qwer)'],
			['my-user', '(qwer'],
			['my-user', 'qwer(asdf'],
			['my-user', 'qwer('],
		];
	}

	/**
	 * @dataProvider userAndPasswordData
	 */
	public function testDetectlogin($user, $password) {
		$e = new \Exception('test');
		$this->logger->logException($e);

		$logLines = $this->getLogs();
		foreach($logLines as $logLine) {
			$this->assertNotContains($user, $logLine);
			$this->assertNotContains($password, $logLine);
			$this->assertContains('login(*** sensitive parameters replaced ***)', $logLine);
		}
	}

	/**
	 * @dataProvider userAndPasswordData
	 */
	public function testDetectcheckPassword($user, $password) {
		$e = new \Exception('test');
		$this->logger->logException($e);
		$logLines = $this->getLogs();

		foreach($logLines as $logLine) {
			$this->assertNotContains($user, $logLine);
			$this->assertNotContains($password, $logLine);
			$this->assertContains('checkPassword(*** sensitive parameters replaced ***)', $logLine);
		}
	}

	/**
	 * @dataProvider userAndPasswordData
	 */
	public function testDetectvalidateUserPass($user, $password) {
		$e = new \Exception('test');
		$this->logger->logException($e);
		$logLines = $this->getLogs();

		foreach($logLines as $logLine) {
			$this->assertNotContains($user, $logLine);
			$this->assertNotContains($password, $logLine);
			$this->assertContains('validateUserPass(*** sensitive parameters replaced ***)', $logLine);
		}
	}

	/**
	 * @dataProvider userAndPasswordData
	 */
	public function testDetecttryLogin($user, $password) {
		$e = new \Exception('test');
		$this->logger->logException($e);
		$logLines = $this->getLogs();

		foreach($logLines as $logLine) {
			$this->assertNotContains($user, $logLine);
			$this->assertNotContains($password, $logLine);
			$this->assertContains('tryLogin(*** sensitive parameters replaced ***)', $logLine);
		}
	}

	//loginWithPassword
	/**
	 * @dataProvider userAndPasswordData
	 */
	public function testDetectloginWithPassword($user, $password) {
		$e = new \Exception('test');
		$this->logger->logException($e);
		$logLines = $this->getLogs();

		foreach($logLines as $logLine) {
			$this->assertNotContains($user, $logLine);
			$this->assertNotContains($password, $logLine);
			$this->assertContains('loginWithPassword(*** sensitive parameters replaced ***)', $logLine);
		}
	}

}
