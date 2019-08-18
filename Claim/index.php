<?php
namespace Claim;

use \shgysk8zer0\PHPAPI\{API, PDO, Headers, HTTPException};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};
use \StdClass;
require_once '../autoloader.php';

try {
	$api = new API('*');

	$api->on('GET', function(API $req): void
	{
		$pdo = PDO::load();

		if ($req->get->has('id')) {
			$stm = $pdo->prepare('SELECT `status`,
				`customer`,
				`contractor`,
				`lead`,
				`hours`,
				`price`
			FROM `Claim`
			WHERE `id` = :id
			LIMIT 1;');
			$stm->execute([':id' => $req->get->get('id')]);
		} else {
			$stm = $pdo->prepare('SELECT `status`,
				`customer`,
				`contractor`,
				`lead`,
				`hours`,
				`price`
			FROM `Claim`;');
			$stm->execute();
		}

		$results = $stm->fetchAll();
		Headers::contentType('application/json');
		echo json_encode($results);
	});

	$api->on('POST', function(API $req): void
	{
		$pdo = PDO::load();
	});

	$api->on('DELETE', function(API $req): void
	{
		$pdo = PDO::load();
	});

	$api();
} catch (HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	exit(json_encode($e));
}
