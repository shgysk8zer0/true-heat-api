<?php
namespace Server;
use \shgysk8zer0\PHPAPI\{API, Headers, HTTPException, PDO, User};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoloader.php';

try {
	$api = new API('*');
	$api->on('GET', function(API $request): void
	{
		if ($request->get->has('token')) {
			$user = User::loadFromToken(PDO::load(), $request->get->get('token', false));
			if ($user->isAdmin()) {
				Headers::contentType('application/json');
				echo json_encode($_SERVER);
			} else {
				throw new HTTPException('Access denied', HTTP::FORBIDDEN);
			}
		} else {
			throw new HTTPException('Missing token in request', HTTP::UNAUTHORIZED);
		}
	});
	$api();
} catch(HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	echo json_encode($e);
}
