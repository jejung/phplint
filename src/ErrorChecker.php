<?php
/**
 * Created by PhpStorm.
 * User: jean
 * Date: 21/10/16
 * Time: 11:55
 */

namespace Overtrue\PHPLint;


interface ErrorChecker {

	public function isRunning();
	public function start();
	public function hasErrors();
	public function getErrors();
}