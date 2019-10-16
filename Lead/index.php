<?php
namespace Lead;
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoloader.php';
use \shgysk8zer0\PHPAPI\{PDO, API, HTTPException, Headers};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};
use function \Functions\{get_user};

try {
	$api = new API('*');

	$api->on('GET', function(API $req): void
	{
		if (! $req->get->has('token')) {
			throw new HTTPException('Missing token', HTTP::BAD_REQUEST);
		} elseif (! $user = get_user($req->get)) {
			throw new HTTPException('Token invalid or expired', HTTP::UNAUTHORIZED);
		} elseif (! $user->can('listClaims')) {
			throw new HTTPException('You do not have permission to perform this action', HTTP::FORBIDDEN);
		} else {
			$pdo = PDO::load();
			$stm = $pdo->prepare('SELECT DISTINCT(`lead`) AS `name` FROM `Claim` WHERE `lead` IS NOT NULL AND `lead` != "";');
			$stm->execute();
			Headers::contentType('application/json');
			echo json_encode($stm->fetchAll());
		}
	});

	$api();
} catch(HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	echo json_encode($e);
}
