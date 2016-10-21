<?php
/**
 * Created by PhpStorm.
 * User: jean
 * Date: 21/10/16
 * Time: 10:14
 */

namespace Overtrue\PHPLint;


class TokenizerThread implements ErrorChecker
{
	private $fileName;
	private $errors = [];
	private $running = false;

	private $tokens;
	private $index;
	private $lastIndex;
	private $importedClasses;
	private $namespace;

	public function __construct($fileName)
	{
		$this->fileName = $fileName;
	}

	public function start()
	{
		$this->running = true;
		if (array_search(basename($this->fileName), ['autoload.php', 'bootstrap.php', 'classloader.php'])) {
			require_once $this->fileName;
		} else {
			$fileContent = file_get_contents($this->fileName);
			$this->parseContent($fileContent);
		}
		$this->running = false;
	}

	static private function extractClassName($fullyQualifiedName)
	{
		$pos = strrpos($fullyQualifiedName, '\\');
		return substr($fullyQualifiedName, (integer) $pos);
	}

	private static function tokenToObject($token)
	{
		return (object) [
			'type' => $token[0],
			'lexeme' => $token[1],
			'line' => $token[2]
		];
	}

	private function readWhileString()
	{
		$token = $this->getCurrentToken();
		$fullString = '';
		while ($token->type == T_STRING || $token->type == T_NS_SEPARATOR ||
			$token->type == T_WHITESPACE) {
			if ($token->type != T_WHITESPACE)
				$fullString .= $token->lexeme;
			if (!$this->nextToken())
				break;
			$token = $this->getCurrentToken();
			if (!is_object($token))
				break;
		}
		return $fullString;
	}

	private function getCurrentToken() {
		$token = $this->tokens[$this->index];
		if (is_array($token))
			return static::tokenToObject($token);
		return $token;
	}

	public function nextToken()
	{
		$this->index++;
		return $this->index < $this->lastIndex;
	}

	public function ignoreWhites()
	{
		$token = $this->getCurrentToken();
		while (is_object($token) && $token->type == T_WHITESPACE && $this->nextToken()) {
			$token = $this->getCurrentToken();
		}
		return $token;
	}

	public function parseContent($fileContent)
	{
		$this->tokens = token_get_all($fileContent);
		$this->importedClasses = [];
		$this->namespace = '';

		$this->index = -1;
		$this->lastIndex = count($this->tokens) - 1;
		while ($this->nextToken()) {
			$token = $this->getCurrentToken();
			if (is_object($token)) {
				if ($token->type == T_STRING || $token->type == T_NS_SEPARATOR) {
					$initialLine = $token->line;
					$isFullyQualifiedName = $token->type == T_NS_SEPARATOR;
					$fullString = $this->readWhileString();
					// may change on readWhileString
					$token = $this->getCurrentToken();
					if (is_object($token) && $token->type == T_DOUBLE_COLON) {
						$this->validateClass($isFullyQualifiedName,$fullString,$initialLine);
					}
				} elseif ($token->type == T_USE || $token->type == T_NEW) {
					$token = $this->ignoreWhites();
					if ($token && is_object($token)) {
						$initialLine = $token->line;
						$isFullyQualifiedName = $token->type == T_NS_SEPARATOR;
						$fullString = $this->readWhileString();
						$this->validateClass($isFullyQualifiedName,$fullString,$initialLine);
						if ($token->type == T_USE)
							$this->importedClasses []= static::extractClassName($fullString);
					}
				} elseif ($token->type == T_NAMESPACE) {
					$token = $this->ignoreWhites();
					if ($token && is_object($token)) {
						$this->namespace = $this->readWhileString();
					}
				}
			}
		}
	}

	public function validateClass($isFullyQualified,$className,$usage)
	{
		if (array_search($className, ['self', 'parent', 'static']))
			return;

		if (array_search($className, $this->importedClasses))
			return;

		$fullClassName = ($isFullyQualified ? '' : $this->namespace.'\\').$className;
		if (!class_exists($fullClassName ))
			$this->errors []= [
				'error' => "Possible class not found: $fullClassName",
				'line' => $usage
			];
	}

	public function hasErrors() {
		return $this->errors;
	}
	public function getErrors() {
		return $this->errors;
	}

	public function isRunning()
	{
		return $this->running;
	}
}