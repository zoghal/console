<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         2.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Console;

use Cake\Core\App;
use Cake\Core\Configure;
use Cake\Error\Exception;
use Cake\Utility\Inflector;

/**
 * Shell dispatcher handles dispatching cli commands.
 *
 * Consult https://github.com/cakephp/app/tree/master/App/Console/cake.php
 * for how this class is used in practice.
 */
class ShellDispatcher {

/**
 * Contains command switches parsed from the command line.
 *
 * @var array
 */
	public $params = [];

/**
 * Contains arguments parsed from the command line.
 *
 * @var array
 */
	public $args = [];

/**
 * Constructor
 *
 * The execution of the script is stopped after dispatching the request with
 * a status code of either 0 or 1 according to the result of the dispatch.
 *
 * @param array $args the argv from PHP
 * @param bool $bootstrap Should the environment be bootstrapped.
 */
	public function __construct($args = [], $bootstrap = true) {
		set_time_limit(0);
		$this->args = $args;

		if ($bootstrap) {
			$this->_initEnvironment();
		}
	}

/**
 * Run the dispatcher
 *
 * @param array $argv The argv from PHP
 * @return int The exit code of the shell process.
 */
	public static function run($argv) {
		$dispatcher = new ShellDispatcher($argv);
		return $dispatcher->dispatch();
	}

/**
 * Defines current working environment.
 *
 * @return void
 * @throws \Cake\Error\Exception
 */
	protected function _initEnvironment() {
		if (!$this->_bootstrap()) {
			$message = "Unable to load CakePHP core.\nMake sure Cake exists in " . CAKE_CORE_INCLUDE_PATH;
			throw new Exception($message);
		}

		if (function_exists('ini_set')) {
			ini_set('html_errors', false);
			ini_set('implicit_flush', true);
			ini_set('max_execution_time', 0);
		}

		$this->shiftArgs();
	}

/**
 * Initializes the environment and loads the CakePHP core.
 *
 * @return bool Success.
 */
	protected function _bootstrap() {
		if (!Configure::read('App.fullBaseUrl')) {
			Configure::write('App.fullBaseUrl', 'http://localhost');
		}

		return true;
	}

/**
 * Dispatches a CLI request
 *
 * @return int The cli command exit code. 0 is success.
 */
	public function dispatch() {
		return $this->_dispatch() === true ? 0 : 1;
	}

/**
 * Dispatch a request.
 *
 * @return bool
 * @throws \Cake\Console\Error\MissingShellMethodException
 */
	protected function _dispatch() {
		$shell = $this->shiftArgs();

		if (!$shell) {
			$this->help();
			return false;
		}
		if (in_array($shell, ['help', '--help', '-h'])) {
			$this->help();
			return true;
		}

		$Shell = $this->_getShell($shell);

		$command = null;
		if (isset($this->args[0])) {
			$command = $this->args[0];
		}

		if ($Shell instanceof Shell) {
			$Shell->initialize();
			return $Shell->runCommand($command, $this->args);
		}

		$methods = array_diff(get_class_methods($Shell), get_class_methods('Cake\Console\Shell'));
		$added = in_array($command, $methods);
		$private = $command[0] === '_' && method_exists($Shell, $command);

		if (!$private) {
			if ($added) {
				$this->shiftArgs();
				$Shell->startup();
				return $Shell->{$command}();
			}
			if (method_exists($Shell, 'main')) {
				$Shell->startup();
				return $Shell->main();
			}
		}

		throw new Error\MissingShellMethodException(['shell' => $shell, 'method' => $command]);
	}

/**
 * Get shell to use, either plugin shell or application shell
 *
 * All paths in the loaded shell paths are searched.
 *
 * @param string $shell Optionally the name of a plugin
 * @return mixed An object
 * @throws \Cake\Console\Error\MissingShellException when errors are encountered.
 */
	protected function _getShell($shell) {
		list($plugin, $shell) = pluginSplit($shell);

		$plugin = Inflector::camelize($plugin);
		$class = Inflector::camelize($shell);
		if ($plugin) {
			$class = $plugin . '.' . $class;
		}
		$class = App::classname($class, 'Console/Command', 'Shell');

		if (!class_exists($class)) {
			throw new Error\MissingShellException([
				'class' => $shell,
			]);
		}
		$Shell = new $class();
		$Shell->plugin = trim($plugin, '.');
		return $Shell;
	}

/**
 * Removes first argument and shifts other arguments up
 *
 * @return mixed Null if there are no arguments otherwise the shifted argument
 */
	public function shiftArgs() {
		return array_shift($this->args);
	}

/**
 * Shows console help. Performs an internal dispatch to the CommandList Shell
 *
 * @return void
 */
	public function help() {
		$this->args = array_merge(['command_list'], $this->args);
		$this->dispatch();
	}

/**
 * Stop execution of the current script
 *
 * @param int|string $status see http://php.net/exit for values
 * @return void
 */
	protected function _stop($status = 0) {
		exit($status);
	}

}
