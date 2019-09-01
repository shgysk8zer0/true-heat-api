<?php
namespace Claim;

use \shgysk8zer0\PHPAPI\{API, PDO, Headers, UUID, HTTPException};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};
use \StdClass;
require_once '../autoloader.php';

try {
	$api = new API('*');

	$api->on('GET', function(API $req): void
	{
		$pdo = PDO::load();

		if ($req->get->has('uuid', 'token')) {
			$stm = $pdo->prepare('SELECT `uuid`,
				`status`,
				`customer`,
				`contractor`,
				`lead`,
				`hours`,
				`price`
			FROM `Claim`
			WHERE `uuid` = :uuid
			LIMIT 1;');

			$stm->execute([':uuid' => $req->get->get('uuid')]);
			$results = $stm->fetchObject();
		} elseif ($req->get->has('token')) {
			$stm = $pdo->prepare('SELECT `uuid`,
				`status`,
				`customer`,
				`contractor`,
				`lead`,
				`hours`,
				`price`
			FROM `Claim`;');

			$stm->execute();
			$results = $stm->fetchAll();
		} else {
			throw new HTTPException('Unauthorized', HTTP::UNAUTHORIZED);
		}

		Headers::contentType('application/json');
		echo json_encode($results);
	});

	$api->on('POST', function(API $req): void
	{
		if (! $req->post->has('token')) {
			throw new HTTPException('Not authorized', HTTP::UNAUTHORIZED);
		} else {
			$pdo = PDO::load();
			$claim = $pdo->prepare('INSERT INTO `Claim` (
				`uuid`,
				`status`,
				`customer`,
				`contractor`,
				`lead`,
				`hours`,
				`price`
			) VALUES (
				:uuid,
				:status,
				:customer,
				:contractor,
				:lead,
				:hours,
				:price
			) ON DUPLICATE KEY UPDATE
				`status`     = COALESCE(:status,     `status`),
				`customer`   = COALESCE(:customer,   `customer`),
				`contractor` = COALESCE(:contractor, `contractor`),
				`lead`       = COALESCE(:lead,       `lead`),
				`hours`      = COALESCE(:hours,      `hours`),
				`price`      = COALESCE(:price,      `price`);');

			if ($claim->execute([
				':uuid'       => $req->post->get('uuid', true, new UUID()),
				':status'     => $req->post->get('status', true, null),
				':customer'   => $req->post->get('customer', false, null),
				':contractor' => $req->post->get('contractor', false, null),
				':lead'       => $req->post->get('lead', false, null),
				':hours'      => $req->post->get('hours', false, null),
				':price'      => $req->post->get('price', false, null),
			]) and intval($claim->rowCount()) !== 0) {
				Headers::status(HTTP::CREATED);
			} else {
				throw new HTTPException('Error saving claim', HTTP::INTERNAL_SERVER_ERROR);
			}
		}
	});

	$api->on('DELETE', function(API $req): void
	{
		if ($req->get->has('uuid', 'token')) {
			$pdo = PDO::load();
			$stm = $pdo->prepare('DELETE FROM `Claim` WHERE `uuid` = :uuid LIMIT 1;');
			if ($stm->execute([':uuid' => $req->get->get('uuid')]) and $stm->rowCount() !== 0) {
				Headers::status(HTTP::NO_CONTENT);
			} else {
				throw new HTTPException('Claim not found', HTTP::BAD_REQUEST);
			}
		} elseif (! $req->get->has('token')) {
			throw new HTTPException('Not authenticated', HTTP::UNAUTHORIZED);
		} elseif (! $req->get->has('uuid')) {
			throw new HTTPException('No claim selected', HTTP::BAD_REQUEST);
		}
	});

	$api();
} catch (HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	exit(json_encode($e));
}
