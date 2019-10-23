<?php
namespace Errors\Client;

use \shgysk8zer0\PHPAPI\{API, PDO, UUID, User, Headers, HTTPException};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'autoloader.php';

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
			$stm = PDO::load()->query('SELECT `uuid`,
				`name`,
				`message`,
				`fileName`,
				`lineNumber`,
				`columnNumber`,
				`stack`,
				`referrer`,
				`userAgent`,
				`remoteAddress`,
				DATE_FORMAT(`dateTime`, "%Y-%m-%dT%T") AS `dateTime`
			FROM `ClientErrors`;');

			$stm->execute();
			Headers::contentType('application/json');
			echo json_encode($stm->fetchAll() ?? []);
		}
	});

	$api->on('POST', function(API $req): void
	{
		if ($req->post->has('name', 'fileName', 'message', 'lineNumber', 'columnNumber')) {
			$stm = PDO::load()->prepare('INSERT INTO `ClientErrors` (
				`uuid`,
				`referrer`,
				`userAgent`,
				`remoteAddress`,
				`name`,
				`message`,
				`fileName`,
				`lineNumber`,
				`columnNumber`,
				`stack`
			) VALUES (
				:uuid,
				:referrer,
				:userAgent,
				:remoteAddress,
				:name,
				:message,
				:fileName,
				:lineNumber,
				:columnNumber,
				:stack
			);');

			if (! ($stm->execute([
				':uuid'          => new UUID(),
				':referrer'      => $req->referrer,
				':userAgent'     => $req->userAgent,
				':remoteAddress' => $req->remoteAddress,
				':name'          => $req->post->name,
				':message'       => $req->post->message,
				':fileName'      => $req->post->name,
				':lineNumber'    => $req->post->lineNumber,
				':columnNumber'  => $req->post->columnNumber,
				':stack'         => preg_replace('/' . preg_quote("\r\n") . '/', PHP_EOL, $req->post->stack),
			]) and $stm->rowCount() === 1)) {
				throw new HTTPException('Error saving reported error', HTTP::INTERNAL_SERVER_ERROR);
			}
		} else {
			throw new HTTPException('Invalid data reported', HTTP::BAD_REQUEST);
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
		} elseif (PDO::load()->query('TRUNCATE `ClientErrors`;')->execute()) {
			Headers::status(HTTP::NO_CONTENT);
		} else {
			throw new HTTPException('Error clearing table', HTTP::INTERNAL_SERVER_ERROR);
		}
	});

	$api();
} catch (HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	exit(json_encode($e));
}
