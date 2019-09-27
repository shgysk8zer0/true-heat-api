<?php
namespace People;

use \shgysk8zer0\PHPAPI\{API, PDO, Headers, HTTPException};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};
use function \Functions\{get_user};
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoloader.php';

try {
	$api = new API('*');

	$api->on('GET', function(API $req): void
	{
		if (! $req->get->has('token')) {
			throw new HTTPException('Missing token', HTTP::UNAUTHORIZED);
		} elseif (! $user = get_user($req->get)) {
			throw new HTTPException('Token invalid or expired', HTTP::FORBIDDEN);
		} else {
			$stm = PDO::load()->prepare('SELECT CONCAT(`Person`.`givenName`, " ", `Person`.`familyName`) as `name`,
				`Person`.`identifier` AS `person`,
				`Person`.`jobTitle`,
				`Organization`.`name` AS `organization`,
				`users`.`uuid` AS `user`,
				`roles`.`name` AS `role`,
				`ImageObject`.`url` AS `image`
			FROM `users`
			JOIN `Person` ON `users`.`person` = `Person`.`id`
			JOIN `roles` ON `users`.`role` = `roles`.`id`
			LEFT OUTER JOIN `Organization` ON `Person`.`worksFor` = `Organization`.`id`
			LEFT OUTER JOIN `ImageObject` ON `Person`.`image` = `ImageObject`.`id`;');

			$stm->execute();
			$users = $stm->fetchAll();
			Headers::contentType('application/json');
			echo json_encode($users);
		}
	});

	$api();
} catch (HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	exit(json_encode($e));
}
