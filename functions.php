<?php

namespace Functions;
use const \Consts\{DEBUG, ERROR_LOG, UPLOADS_DIR, BASE};
use \shgysk8zer0\PHPAPI\{PDO, User, JSONFILE, Headers, HTTPException, Request, URL};
use \StdClass;
use \DateTime;
use \Throwable;
use \ErrorException;

function is_pwned(string $pwd): bool
{
	$hash   = strtoupper(sha1($pwd));
	$prefix = substr($hash, 0, 5);
	$rest   = substr($hash, 5);
	$req    = new Request("https://api.pwnedpasswords.com/range/{$prefix}");
	$resp   = $req->send();

	if ($resp->ok) {
		return strpos($resp->body, "{$rest}:") !== false;
	} else {
		return false;
	}
}

function upload_path(): string
{
	$date = new DateTime();
	return UPLOADS_DIR . $date->format(sprintf('Y%sm%s', DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR));
}

function https(): bool
{
	return array_key_exists('HTTPS', $_SERVER) and ! empty($_SERVER['HTTPS']);
}

function dnt(): bool
{
	return array_key_exists('HTTP_DNT', $_SERVER) and $_SERVER['HTTP_DNT'] === '1';
}

function is_cli(): bool
{
	return in_array(PHP_SAPI, ['cli']);
}

function error_handler(int $errno, string $errstr, string $errfile, int $errline = 0): bool
{
	return log_exception(new ErrorException($errstr, 0, $errno, $errfile, $errline));
}

function exception_handler(Throwable $e)
{
	if ($e instanceof HTTPException) {
		log_exception($e);
		Headers::status($e->getCode());
		Headers::contentType('application/json');
		exit(json_encode($e));
	} else {
		log_exception($e);
		Headers::status(Headers::INTERNAL_SERVER_ERROR);
		Headers::contentType('application/json');
		$error = [
			'message' => 'Internal Server Error',
			'status'  => Headers::INTERNAL_SERVER_ERROR,
		];
		if (DEBUG) {
			$error['file']  = $e->getFile();
			$error['line']  = $e->getLine();
			$error['code']  = $e->getCode();
			$error['trace'] = $e->getTrace();
		}
		exit(json_encode([
			'error' => [
				'message' => 'Internal Server Error',
				'status'  => Headers::INTERNAL_SERVER_ERROR,
			],
		]));
	}
}

function log_exception(Throwable $e): bool
{
	static $stm = null;

	if (is_null($stm)) {
		$pdo = PDO::load();
		$stm = $pdo->prepare('INSERT INTO `logs` (
			`type`,
			`message`,
			`file`,
			`line`,
			`code`,
			`remoteIP`,
			`url`
		) VALUES (
			:type,
			:message,
			:file,
			:line,
			:code,
			:ip,
			:url
		);');
	}

	$url = URL::getRequestUrl();
	unset($url->password);
	$code = $e->getCode();

	return $stm->execute([
		':type'    => get_class($e),
		':message' => $e->getMessage(),
		':file'    => str_replace(BASE, null, $e->getFile()),
		':line'    => $e->getLine(),
		':code'    => is_int($code) ? $code : 0,
		':ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
		':url'     => $url,
	]);
}
