<?php
namespace Roles;

use \shgysk8zer0\PHPAPI\{API, PDO, Headers, HTTPException};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};
use \StdClass;
require_once '../autoloader.php';

try {
	$api = new API('*');

	$api->on('GET', function(): void
	{
		$pdo = PDO::load();
		$stm = $pdo->prepare('SELECT `id`, `name` FROM `roles`;');
		$stm->execute();
		$roles = $stm->fetchAll();

		if (! is_array($roles) or empty($roles)) {
			throw new HTTPException('No roles available', HTTP::INTERNAL_SERVER_ERROR);
		} else {
			Headers::contentType('application/json');

			exit(json_encode(array_map(function(StdClass $role): StdClass
			{
				$role->id = intval($role->id);
				return $role;
			}, $roles)));
		}
	});

	$api();
} catch (HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	exit(json_encode($e));
}
