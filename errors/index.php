<?php
namespace Errors;
use \shgysk8zer0\PHPAPI\{API, PDO, User, Headers, HTTPException};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};
use function \Functions\{get_user};

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoloader.php';

try {
	$api = new API();

	$api->on('GET', function(API $req): void
	{
		if (! $req->get->has('token')) {
			throw new HTTPException('Missing token', HTTP::BAD_REQUEST);
		}  elseif (! $user = get_user($req->get)) {
			throw new HTTPException('Token invalid', HTTP::UNAUTHORIZED);
		} elseif (! $user->can('debug')) {
			throw new HTTPException('Permission denied', HTTP::FORBIDDEN);
		} else {
			$stm = PDO::load()->prepare('SELECT * FROM `jsErrors`;');

			if ($stm->execute()) {
				Headers::contentType('application/json');
				echo json_encode($stm->fetchAll());
			} else {
				throw new HTTPException('Error loading errors', HTTP::INTERNAL_SERVER_ERROR);
			}
		}
	});

	$api->on('POST', function(API $req): void
	{
		if ($req->post->has('message', 'file', 'line', 'column')) {
			$stm = PDO::load()->prepare('INSERT INTO `jsErrors` (
				`message`,
				`file`,
				`col`,
				`line`,
				`url`,
				`userAgent`,
				`ip`,
				`connection`
			) VALUES (
				:message,
				:file,
				:column,
				:line,
				:url,
				:userAgent,
				:ip,
				:connection
			);');
			if ($stm->execute([
				':message'   => $req->post->get('message'),
				':file'      => $req->post->get('file'),
				':column'    => $req->post->get('column'),
				':line'      => $req->post->get('line'),
				':url'       => $req->post->get('url', true, $req->referrer),
				':userAgent' => $req->userAgent,
				':ip'        => $req->remoteAddress,
				':connection' => $req->post->get('connection', true, 'unknown'),
			]) and $stm->rowCount() === 1) {
				Headers::status(HTTP::CREATED);
			} else {
				Headers::status(HTTP::INTERNAL_SERVER_ERROR);
			}
		} else {
			throw new HTTPException('No error data', HTTP::BAD_REQUEST);
		}
	});

	$api->on('DELETE', function(API $req): void
	{
		if (! $req->get->has('token')) {
			throw new HTTPException('Missing token', HTTP::BAD_REQUEST);
		}  elseif (! $user = get_user($req->get)) {
			throw new HTTPException('Token invalid', HTTP::UNAUTHORIZED);
		} elseif (! $user->can('debug')) {
			throw new HTTPException('Permission denied', HTTP::FORBIDDEN);
		} else {
			$stm = PDO::load()->prepare('TRUNCATE `jsErrors`;');

			if ($stm->execute()) {
				Headers::status(HTTP::NO_CONTENT);
			} else {
				throw new HTTPException('Error loading errors', HTTP::INTERNAL_SERVER_ERROR);
			}
		}
	});

	$api();
} catch (HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	echo json_encode($e);
}
