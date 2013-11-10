<?php
/**
 * ProgressBarTask Test Cases
 *
 * Test Cases for progress bar shell task
 *
 * PHP version 5
 *
 * Copyright (c) 2010 Matt Curry
 * www.PseudoCoder.com
 * http://github.com/mcurry/progress_bar
 *
 * @author      Matt Curry <matt@pseudocoder.com>
 * @license     MIT
 */

App::uses('ShellDispatcher', 'Console');
App::uses('ConsoleOutput', 'Console');
App::uses('ConsoleInput', 'Console');
App::uses('Shell', 'Console');
App::uses('ProgressBarTask', 'ProgressBar.Console/Command/Task');

/**
 * TestProgressBarTask class
 *
 * @uses          ProgressBarTask
 * @package       ProgressBar
 * @subpackage    ProgressBar.Test.Case.Console.Command.Task
 */
class TestProgressBarTask extends ProgressBarTask {

/**
 * Output generated during test
 *
 * @var array
 */
	public $messages = array();

/**
 * niceRemaining proxy method
 *
 * @return void
 */
	public function niceRemaining() {
		return $this->_niceRemaining();
	}

/**
 * Overrides parent method, forcing info about terminal width to 80, so tests
 * can pass in both console and browser.
 *
 * @param mixed $width null
 * @return void
 */
	protected function _setTerminalWidth($width = null) {
		parent::_setTerminalWidth(80);
	}

/**
 * Overrides parent method, pushing messages to public property.
 *
 * @param mixed $message
 * @param integer $newlines
 * @param integer $level
 * @return integer
 */
	public function out($message = null, $newlines = 0, $level = Shell::NORMAL) {
		$this->messages[] = $message;
	}

}

/**
 * ProgressBarTask Test class
 *
 * @package       ProgressBar
 * @subpackage    ProgressBar.Test.Case.Console.Command.Task
 */
class ProgressBarTaskTest extends CakeTestCase {

/**
 * setUp method
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();
		$out = $this->getMock('ConsoleOutput', array(), array(), '', false);
		$in = $this->getMock('ConsoleInput', array(), array(), '', false);

		$this->Task = $this->getMock('TestProgressBarTask',
			array('in', 'err', '_stop'),
			array($out, $out, $in)
		);
	}

/**
 * tearDown method
 *
 * @return void
 */
	public function tearDown() {
		parent::tearDown();
		unset($this->Task);
	}

/**
 * testStartup method
 *
 * @return void
 */
	public function testStartup() {
		$total = 100;
		$now = time();
		$this->Task->start($total);
		$this->assertIdentical($this->Task->total, $total);
		$this->assertWithinMargin($this->Task->startTime, time(), 1);
		$this->assertIdentical($this->Task->done, 0);
	}

/**
 * testSimpleFormatting method
 *
 * @return void
 */
	public function testSimpleFormatting() {
		$this->Task->start(100);
		$this->Task->next(1);
		$result = end($this->Task->messages);
		$this->assertPattern('@\[>\s+\] 1.0% 1/100.*remaining$@', $result);

		$this->Task->next(49);
		$result = end($this->Task->messages);
		$this->assertPattern('@\[-+>\s+\] 50.0% 50/100.*remaining$@', $result);

		$this->Task->next(50);
		$result = end($this->Task->messages);
		$this->assertPattern('@\[-+>\] 100.0% 100/100.*remaining$@', $result);
	}

/**
 * testSimpleBoundaries method
 *
 * Test/demonstrate what happens when you bail early or overrun.
 *
 * @return void
 */
	public function testSimpleBoundaries() {
		$this->Task->start(100);
		$this->Task->next(50);
		$this->Task->finish(1);

		$result = end($this->Task->messages);
		$this->assertPattern('@\[-{8}>] 50.0% 50/100.*remaining$@', $result);

		$this->Task->start(100);
		$this->Task->next(150);

		$result = end($this->Task->messages);
		$this->assertPattern('@\[-{8}>\] 150.0% 150/100.*remaining$@', $result);
	}

/**
 * testMessageUsage method
 *
 * @return void
 */
	public function testMessageUsage() {
		$this->Task->message('Running your 100 step process');
		$this->Task->start(100);
		$this->Task->terminalWidth = 100;

		$this->Task->next(1);
		$result = end($this->Task->messages);
		$this->assertPattern('@Running your 100 step process\s+1.0% 1/100.*remaining \[>\s+\]$@', $result);

		$this->Task->message('Changed and muuuuuuuuuuuuuuuuuuuuuuuuuuuuuuuuuuch longer message');
		$this->Task->next(1);
		$result = end($this->Task->messages);
		$this->assertPattern('@Changed and muuuuuuuuuuuuuuuuuuuuuuuuuuuuuuuuuuch longer messa... 2.0% 2/100.*remaining \[>\s+\]$@', $result);
	}

/**
 * testExecuteNothing method
 *
 * @return void
 */
	public function testExecuteNothing() {
		$this->assertNull($this->Task->execute());
	}

/**
 * testNext method
 *
 * @return void
 */
	public function testNext() {
		$this->Task->start(100);
		$this->Task->next();
		$this->assertIdentical($this->Task->done, 1);
	}

/**
 * testNiceRemainingUnknown method
 *
 * @return void
 */
	public function testNiceRemainingUnknown() {
		$this->Task->start(100);

		$expected = '?';
		$this->assertEqual($this->Task->niceRemaining(), $expected);

		$this->Task->next();
		$expected = '?';
		$this->assertEqual($this->Task->niceRemaining(), $expected);
	}

/**
 * testNiceRemainingBasic method
 *
 * @return void
 */
	public function testNiceRemainingBasic() {
		// 2 seconds per iteration, should take 20 seconds total.
		$total = 10;
		$delay = 2;
		$loops = 3;
		$this->Task->start($total);

		for ($i = 0; $i < $loops; $i++) {
			sleep($delay);
			$this->Task->next();
		}
		$result = $this->Task->niceRemaining();
		$expected = '14 secs';
		$this->assertEqual($result, $expected);

		// Testing numbers not necessarily nice and rounded
		// 2 seconds per iteration, should take 20 seconds total.
		$total = 9;
		$delay = 1;
		$loops = 4;
		$this->Task->start($total);

		for ($i = 0; $i < $loops; $i++) {
			sleep($delay);
			$this->Task->next();
		}
		$result = $this->Task->niceRemaining();
		$expected = '05 secs';
		$this->assertEqual($result, $expected);
	}

/**
 * testNiceRemainingMinutes method
 *
 * @return void
 */
	public function testNiceRemainingMinutes() {
		// 2 seconds per iteration, should take 120 seconds total.
		$total = 60;
		$delay = 2;
		$loops = 3;
		$this->Task->start($total);

		for ($i = 0; $i < $loops; $i++) {
			sleep($delay);
			$this->Task->next();
		}
		$result = $this->Task->niceRemaining();

		$expected = '1 min, 54 secs';
		$this->assertEqual($result, $expected);

		// 2 seconds per iteration, should take 200 seconds total.
		$total = 120;
		$delay = 2;
		$loops = 3;
		$this->Task->start($total);

		for ($i = 0; $i < $loops; $i++) {
			sleep($delay);
			$this->Task->next();
		}
		$result = $this->Task->niceRemaining();

		$expected = '3 mins, 54 secs';
		$this->assertEqual($result, $expected);
	}

/**
 * testSet method
 *
 * @return void
 */
	public function testSet() {
		$this->Task->start(100);
		$this->Task->set(50);
		$this->assertEqual($this->Task->done, 50);

		$this->Task->set(200);
		$this->assertEqual($this->Task->done, 100);
	}

}
