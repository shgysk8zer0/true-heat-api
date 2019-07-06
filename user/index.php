<?php
namespace User;
use \shgysk8zer0\PHPAPI\{PDO, User, Headers, HTTPException, API};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};
use function \Functions\{is_pwned};

require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoloader.php');

try {
	$api = new API('*');

	$api->on('GET', function(API $api): void
	{
		if (! $api->get->has('token')) {
			throw new HTTPException('Missing token in request', HTTP::BAD_REQUEST);
		} else {
			Headers::contentType('application/json');
			$user = User::loadFromToken(PDO::load(), $api->get->get('token', false));

			if (! $user->loggedIn) {
				throw new HTTPException('User data expired or invalid', HTTP::UNAUTHORIZED);
			} else {
				echo json_encode($user);
			}
		}
	});

	$api->on('POST', function(API $api): void
	{
		try {
			$user = new User(PDO::load());
			if ($user->create($api->post)) {
				Headers::status(HTTP::CREATED);
				echo json_encode($user);
			} else {
				throw new HTTPException('Error registering user', HTTP::UNAUTHORIZED);
			}
		} catch (\Throwable $e) {
			Headers::status(HTTP::INTERNAL_SERVER_ERROR);
			Headers::contentType('application/json');
			exit(json_encode([
				'message' => $e->getMessage(),
				'file'    => $e->getFile(),
				'line'    => $e->getLine(),
				'trace'   => $e->getTrace(),
			]));
		}
	});

	$api->on('DELETE', function(API $api): void
	{
		if (! $api->get->has('token')) {
			throw new HTTPException('Missing token in request', HTTP::BAD_REQUEST);
		} else {
			Headers::contentType('application/json');
			$user = User::loadFromToken(PDO::load(), $api->get('token', false));
			if (! $user->loggedIn) {
				throw new HTTPException('User data expired or invalid', HTTP::UNAUTHORIZED);
			} elseif ($user->delete()) {
				echo json_encode(['status' => 'success']);
			} else {
				throw new HTTPException('Error deleting user', HTTP::INTERNAL_SERVER_ERROR);
			}
		}
	});

	$api();
} catch (HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	echo json_encode($e);
}
