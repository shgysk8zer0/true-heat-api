<?php
namespace Note;

use \shgysk8zer0\PHPAPI\{API, PDO, Headers, UUID, HTTPException};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};
use function \Functions\{get_user, get_person_id_for_user};
use \StdClass;
use \DateTime;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoloader.php';

try {
	$api = new API('*');

	$api->on('GET', function(API $req): void
	{
		if (! $req->get->has('token', 'claim')) {
			throw new HTTPException('Missing token or claim UUID', HTTP::BAD_REQUEST);
		} elseif (! $user = get_user($req->get)) {
			throw new HTTPException('Token invalid or expired', HTTP::UNAUTHORIZED);
		} elseif (false /* @TODO Check permission to list notes? */) {
			throw new HTTPException('You do not have permission to perform this operation', HTTP::FORBIDDEN);
		} else {
			$pdo = PDO::load();
			$stm = $pdo->prepare('SELECT `text`, `status`, `created`, `author` FROM `Note` WHERE `claim` = :claim;');

			if ($stm->execute([':claim' => $req->get->get('claim')])) {
				Headers::contentType('application/json');
				echo json_encode($stm->fetchAll());
			} else {
				throw new HTTPException('Notes not found for claim', HTTP::NOT_FOUND);
			}
		}
	});

	$api->on('POST', function(API $req): void
	{
		if (! $req->post->has('token', 'claim', 'text', 'status')) {
			throw new HTTPException('Missing data for creating note', HTTP::BAD_REQUEST);
		} elseif (! $user = get_user($req->post)) {
			throw new HTTPException('Token invalid or expired', HTTP::UNAUTHORIZED);
		} elseif (! $user->can('createComment')) {
			throw new HTTPException('You do not have permission to perform this operation', HTTP::FORBIDDEN);
		} else {
			$pdo = PDO::load();
			$note = $pdo->prepare('INSERT INTO `Note` (
				`uuid`,
				`author`,
				`claim`,
				`status`,
				`text`
			) VALUES (
				COALESCE(:uuid, UUID()),
				:author,
				:claim,
				:status,
				:text
			);');

			if (! $note->execute([
				':uuid'   => new UUID(),
				':author' => get_person_id_for_user($pdo, $user->id),
				':claim'  => $req->post->get('claim'),
				':status' => $req->post->get('status'),
				':text'   => $req->post->get('text'),
			]) or intval($pdo->lastInsertId()) === 0) {
				throw new HTTPException('Error saving note', HTTP::INTERNAL_SERVER_ERROR);
			} else {
				Headers::status(HTTP::CREATED);
			}
		}
	});

	$api->on('DELETE', function(API $req): void
	{
		if (! $req->get->has('token', 'uuid')) {
			throw new HTTPException('Missing token or claim UUID', HTTP::BAD_REQUEST);
		} elseif (! $user = get_user($req->get)) {
			throw new HTTPException('Token invalid or expired', HTTP::UNAUTHORIZED);
		} elseif (! $user->can('deleteComment')) {
			throw new HTTPException('You do not have permission to perform this operation', HTTP::FORBIDDEN);
		} else {
			$pdo = PDO::load();
			$stm = $pdo->prepare('DELETE FROM `Note` WHERE `uuid` = :uuid;');

			if ($stm->execute([':uuid' => $req->get->get('uuid')]) and $stm->rowCount() !== 0) {
				Headers::status(HTTP::NO_CONTENT);

			} else {
				throw new HTTPException('Note not found', HTTP::NOT_FOUND);
			}
		}
	});

	$api();
} catch (HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	exit(json_encode($e));
} catch (Throwable $e) {
	Headers::status(HTTP::INTERNAL_SERVER_ERROR);
	Headers::contentType('application/json');
	exit(json_encode(['error' => [
		'message' => $e->getMessage(),
		'file'    => $e->getFile(),
		'line'    => $e->getLine(),
		'trace'   => $e->getTrace(),
	]]));
}
