<?php
namespace Login;

use \shgysk8zer0\PHPAPI\{PDO, User, Headers, HTTPException, API};

require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoloader.php');

try {
	$api = new API('*');

	$api->on('POST', function(API $api): void
	{
		if ($api->accept !== 'application/json') {
			throw new HTTPException('Accept header must be "application/json"', Headers::NOT_ACCEPTABLE);
		} else  if ($api->post->has('username', 'password')) {
			$user = new User(PDO::load());

			if (
				$user->login($api->post->get('username', false), $api->post->get('password', false))
				and API::isEmail($api->post->get('username', false))
			) {
				echo json_encode($user);
			} else {
				throw new HTTPException('Invalid username or password', Headers::UNAUTHORIZED);
			}
		} else {
			throw new HTTPException('Missing username or password fields', Headers::BAD_REQUEST);
		}
	});

	$api();
} catch (HTTPException $e) {
	Headers::status($e->getCode());
	Headers::set('Content-Type', 'application/json');
	echo json_encode($e);
}
