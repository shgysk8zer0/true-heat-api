<?php
namespace SMTP;

use \shgysk8zer0\PHPAPI\{API, PDO, UUID, User, Headers, HTTPException};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};
use const \Consts\{EMAIL_CREDS};
use function \Functions\{get_user, mail};

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoloader.php';

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
		} elseif (! $user->can('config')) {
			throw new HTTPException('You do not have permissions for this action', HTTP::FORBIDDEN);
		} elseif (! @file_exists(EMAIL_CREDS)) {
			throw new HTTPException('Email SMTP config not found', HTTP::NOT_FOUND);
		} else {
			$config = json_decode(file_get_contents(EMAIL_CREDS));
			unset($config->password);
			Headers::contentType('application/json');
			echo json_encode($config);
		}
	});

	$api->on('POST', function(API $req): void
	{
		if (! $req->post->has('token')) {
			throw new HTTPException('Missing token in request', HTTP::BAD_REQUEST);
		} elseif (! $req->post->has('username', 'password', 'host', 'port')) {
			throw new HTTPException('Missing required fields', HTTP::BAD_REQUEST);
		} elseif (! $user = get_user($req->post)) {
			throw new HTTPException('Error checking credentials', HTTP::INTERNAL_SERVER_ERROR);
		} elseif (! $user->can('config')) {
			throw new HTTPException('You do not have permissions for this action', HTTP::FORBIDDEN);
		} else {
			$config = [
				'username' => $req->post->get('username'),
				'password' => $req->post->get('password', false),
				'host'     => $req->post->get('host'),
				'port'     => intval($req->post->get('port')),
				'startTLS' => $req->post->has('startTLS'),
			];
			// @TODO check email credentials correct / send test email

			if (! file_put_contents(EMAIL_CREDS, json_encode($config, JSON_PRETTY_PRINT)) > 0) {
				throw new HTTPException('Error saving credentials', HTTP::INTERNAL_SERVER_ERROR);
			} else {
				Headers::status(HTTP::NO_CONTENT);
			}
		}
	});

	$api();
} catch (HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	exit(json_encode($e));
}
