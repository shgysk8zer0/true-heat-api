<?php
namespace Assignees;
use \shgysk8zer0\PHPAPI\{API, PDO, Headers, HTTPException};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};
use function \Functions\{get_user};

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoloader.php';

try {
	$api = new API();

	$api->on('GET', function(API $req): void
	{
		if (! $req->get->has('token')) {
			throw new HTTPException('Missing token', HTTP::BAD_REQUEST);
		} elseif (! $user = get_user($req->get)) {
			throw new HTTPException('Token invalid or expired', HTTP::UNAUTHORIZED);
		} elseif (! $user->can('createClaim')) {
			throw new HTTPException('You do not have permission to perform this action', HTTP::FORBIDDEN);
		} else {
			$stm = PDO::load()->prepare('SELECT `users`.`uuid`,
				CONCAT(`Person`.`givenName`, " ", `Person`.`familyName`) AS `name`
			FROM `users`
			JOIN `Person` ON `users`.`person` = `Person`.`id`;');
			$stm->execute();
			Headers::contentType('application/json');
			echo json_encode($stm->fetchAll());
		}
	});

	$api();
} catch (HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	echo json_encode($e);
}
