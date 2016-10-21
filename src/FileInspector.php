<?php
/**
 * Created by PhpStorm.
 * User: jean
 * Date: 21/10/16
 * Time: 09:39
 */

namespace Overtrue\PHPLint;
use Overtrue\PHPLint\Process\Lint;

class FileInspector
{

	private $processes = [];

	public function __construct($fileName) {
		$this->processes []= new Lint(PHP_BINARY.' -d error_reporting=E_ALL -d display_errors=On -l '.escapeshellarg($fileName));
		$this->processes []= new TokenizerThread($fileName);
	}

	public function start() {
		foreach ($this->processes as $process)
			$process->start();
	}

	public function isRunning() {
		return array_reduce($this->processes, function($running, ErrorChecker $process){
			return $running || $process->isRunning();
		}, false);;
	}

	public function hasErrors()
	{
		return array_reduce($this->processes, function($errors, ErrorChecker $process){
			return $errors || $process->hasErrors();
		}, false);
	}

	public function  getErrors()
	{
		$errors = array_reduce($this->processes, function($errors, ErrorChecker $process){
			return $errors []= $process->getErrors();
		}, []);
		return $errors;
	}
}