<?php
namespace Errors\Server;

use \shgysk8zer0\PHPAPI\{PDO, User, Headers, HTTPException, API};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};
use \DateTime;
use \StdClass;

require_once(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'autoloader.php');

try {
	$api = new API('*');

	$api->on('GET', function(API $req): void
	{
		if (! $req->get->has('token')) {
			throw new HTTPException('Missing token in request', HTTP::BAD_REQUEST);
		} elseif (! $user = User::loadFromToken(PDO::load(), $req->get->get('token', false))) {
			throw new HTTPException('Error checking credentials', HTTP::INTERNAL_SERVER_ERROR);
		} elseif (! $user->loggedIn) {
			throw new HTTPException('User data expired or invalid', HTTP::UNAUTHORIZED);
		} elseif (! $user->can('debug')) {
			throw new HTTPException('You do not have permissions for this action', HTTP::FORBIDDEN);
		} else {
			$stm = PDO::load()->query('SELECT `message`,
				`type`,
				`file`,
				`line`,
				`code`,
				DATE_FORMAT(`datetime`, "%Y-%m-%dT%T") AS `datetime`,
				`remoteIP`,
				`url`
			FROM `ServerErrors`;');

			$stm->execute();

			$logs = array_map(function(StdClass $entry): StdClass
			{
				$entry->line     = intval($entry->line);
				$entry->code     = intval($entry->code);
				$datetime        = new DateTime($entry->datetime);
				$entry->datetime = $datetime->format(DateTime::W3C);
				return $entry;
			}, $stm->fetchAll() ?? []);

			Headers::contentType('application/json');
			echo json_encode($logs);
		}
	});

	$api->on('DELETE', function(API $req): void
	{
		if (! $req->get->has('token')) {
			throw new HTTPException('Missing token in request', HTTP::BAD_REQUEST);
		} elseif (! $user = User::loadFromToken(PDO::load(), $req->get->get('token', false))) {
			throw new HTTPException('Error checking credentials', HTTP::INTERNAL_SERVER_ERROR);
		} elseif (! $user->loggedIn) {
			throw new HTTPException('User data expired or invalid', HTTP::UNAUTHORIZED);
		} elseif (! $user->can('debug')) {
			throw new HTTPException('You do not have permissions for this action', HTTP::FORBIDDEN);
		} elseif (PDO::load()->query('TRUNCATE `ServerErrors`;')->execute()) {
			Headers::status(HTTP::NO_CONTENT);
		} else {
			throw new HTTPException('Error clearing logs', HTTP::INTERNAL_SERVER_ERROR);
		}
	});

	$api();
} catch (HTTPException $e) {
	Headers::status($e->getCode());
	Headers::set('Content-Type', 'application/json');
	echo json_encode($e);
}
