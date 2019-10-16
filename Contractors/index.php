<?php
namespace Contractors;
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoloader.php';
use \shgysk8zer0\PHPAPI\{PDO, API, HTTPException, Headers, UUID};
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
		} elseif (! $user->can('listContractors')) {
			throw new HTTPException('You do not have permission to perform this action', HTTP::FORBIDDEN);
		} else {
			$pdo = PDO::load();
			$stm = $pdo->prepare('SELECT * FROM `Contractors`;');
			$stm->execute();
			Headers::contentType('application/json');
			echo json_encode($stm->fetchAll());
		}
	});

	$api->on('POST', function(API $req): void
	{
		if (! $req->post->has('name')) {
			throw new HTTPException('Missing name or token', HTTP::BAD_REQUEST);
		} elseif (! $user = get_user($req->post)) {
			throw new HTTPException('Token invalid or expired', HTTP::UNAUTHORIZED);
		} elseif (! $user->can('createContractors')) {
			throw new HTTPException('You do not have permission to perform this action', HTTP::FORBIDDEN);
		} else {
			$pdo = PDO::load();
			$stm = $pdo->prepare('INSERT INTO `Contractors` (
				`uuid`,
				`name`
			) VALUES (
				COALESCE(:uuid, UUID()),
				:name
			);');

			if ($stm->execute([
				':uuid' => new UUID(),
				':name' => $req->post->get('name'),
			]) and $stm->rowCount() === 1) {
				Headers::status(HTTP::CREATED);
			} else {
				throw new HTTPException('Error creating contractor', HTTP::INTERNAL_SERVER_ERROR);
			}
		}
	});

	$api->on('DELETE', function(API $req): void
	{
		if (! $req->get->has('token', 'uuid')) {
			throw new HTTPException('Missing token or uuid', HTTP::BAD_REQUEST);
		} elseif (! $user = get_user($req->get)) {
			throw new HTTPException('Token invalid or expired', HTTP::UNAUTHORIZED);
		} elseif (! $user->can('deleteContractors')) {
			throw new HTTPException('You do not have permission to perform this action', HTTP::FORBIDDEN);
		} else {
			$pdo = PDO::load();
			$stm = $pdo->prepare('DELETE FROM `Contractors` WHERE `uuid` = :uuid LIMIT 1;');

			if ($stm->execute([':uuid' => $req->get->get('uuid')]) and $stm->rowCount() === 1) {
				Headers::status(HTTP::NO_CONTENT);
			} else {
				throw new HTTPException('UUID does not exist', HTTP::NOT_FOUND);
			}
		}
	});

	$api();
} catch(HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	echo json_encode($e);
}
