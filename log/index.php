<?php
namespace Log;
use \shgysk8zer0\PHPAPI\{PDO, User, Headers, HTTPException, API};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};
use const \Consts\{ERROR_LOG};
use \DateTime;
use \StdClass;

require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoloader.php');

try {
	$api = new API('*');

	$api->on('GET', function(API $request): void
	{
		if (! $request->get->has('token')) {
			throw new HTTPException('Missing token in request', HTTP::BAD_REQUEST);
		} else {
			$user = User::loadFromToken(PDO::load(), $request->get->get('token', false));

			if (! $user->loggedIn) {
				throw new HTTPException('User data expired or invalid', HTTP::UNAUTHORIZED);
			} elseif (! $user->isAdmin()) {
				throw new HTTPException('You do not have permissions for this action', HTTP::FORBIDDEN);
			} else {
				$pdo = PDO::load();
				$stm = $pdo->query('SELECT `message`, `type`, `file`, `line`, `code`, `datetime`, `remoteIP`, `url` FROM `logs`;');
				$logs = array_map(function(StdClass $entry): StdClass
				{
					$entry->line     = intval($entry->line);
					$entry->code     = intval($entry->code);
					$datetime        = new DateTime($entry->datetime);
					$entry->datetime = $datetime->format(DateTime::W3C);
					return $entry;
				}, $stm->fetchAll());

				Headers::contentType('application/json');
				echo json_encode($logs);
			}
		}
	});

	$api->on('DELETE', function(API $request): void
	{
		if (! $request->get->has('token')) {
			throw new HTTPException('Missing token in request', HTTP::BAD_REQUEST);
		} else {
			$user = User::loadFromToken(PDO::load(), $request->get->get('token', false));

			if (! $user->loggedIn) {
				throw new HTTPException('User data expired or invalid', HTTP::UNAUTHORIZED);
			} elseif (! $user->isAdmin()) {
				throw new HTTPException('You do not have permissions for this action', HTTP::FORBIDDEN);
			} else {
				$pdo = PDO::load();
				$stm = $pdo->query('TRUNCATE TABLE `logs`;');

				if ($stm->execute()) {
					Headers::status(HTTP::NO_CONTENT);
				} else {
					throw new HTTPException('Error clearing logs', HTTP::INTERNAL_SERVER_ERROR);
				}
			}
		}
	});

	$api();
} catch (HTTPException $e) {
	Headers::status($e->getCode());
	Headers::set('Content-Type', 'application/json');
	echo json_encode($e);
}
