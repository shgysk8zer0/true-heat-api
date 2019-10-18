<?php
namespace Upload;

use \shgysk8zer0\PHPAPI\{PDO, User, API, Headers, Uploads, Files, UUID, HTTPException};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};
use \Throwable;
use function \Functions\{upload_path, get_person_id_for_user};
use const \Consts\{HOST, ALLOWED_UPLOAD_TYPES};

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoloader.php';

try {
	$api = new API('*');

	$api->on('GET', function(API $req): void
	{
		if ($req->get->has('uuid')) {
			$stm = PDO::load()->prepare('SELECT `md5`,
				`path`,
				`size`,
				`mime`,
				`created`
			FROM `Attachment`
			WHERE `uuid` = :uuid
			LIMIT 1;');

			if ($stm->execute([':uuid' => $req->get->get('uuid')]) and $file = $stm->fetchObject()) {
				Headers::contentType('application/json');
				echo(json_encode($file));
			} else {
				throw new HTTPException('File not found', HTTP::NOT_FOUND);
			}
		} elseif ($req->get->has('claim')) {
			$stm = PDO::load()->prepare('SELECT `uuid`, `md5`,
				`path`,
				`size`,
				`mime`,
				`created`
			FROM `Attachment`
			WHERE `claim` = :uuid;');

			if ($stm->execute([':uuid' => $req->get->get('claim')]) and $files = $stm->fetchAll()) {
				Headers::contentType('application/json');
				echo(json_encode($files));
			} else {
				throw new HTTPException('File not found', HTTP::NOT_FOUND);
			}
		} else {
			throw new HTTPException('Missing claim UUID', HTTP::BAD_REQUEST);
		}
	});

	$api->on('POST', function(API $request): void
	{
		Files::setAllowedTypes(...ALLOWED_UPLOAD_TYPES);
		if (! $request->post->has('token', 'claim')) {
			throw new HTTPException('Missing token or claim in request', HTTP::BAD_REQUEST);
		} else {
			$user = User::loadFromToken(PDO::load(), $request->post->get('token', false));
			if (! $user->loggedIn) {
				throw new HTTPException('User data expired or invalid', HTTP::UNAUTHORIZED);
			} elseif (! $user->can('createUpload')) {
				throw new HTTPException('You do not have access', HTTP::FORBIDDEN);
			} elseif (! isset($request->files->upload)) {
				throw new HTTPException('No file uploaded', HTTP::BAD_REQUEST);
			} elseif ($request->files->upload->error instanceof Throwable) {
				throw $request->files->upload->error;
			} else {
				$pdo = PDO::load();
				$uuid = new UUID();
				$stm = $pdo->prepare('INSERT INTO `Attachment` (
					`uuid`,
					`md5`,
					`filename`,
					`claim`,
					`path`,
					`size`,
					`mime`,
					`uploadedBy`
				) VALUES (
					:uuid,
					:md5,
					:filename,
					:claim,
					:path,
					:size,
					:mime,
					:user
				);');
				$file = $request->files->upload;
				$path = upload_path();
				$save_path = "{$path}{$file->md5}.{$file->ext}";
				if ($file->saveAs($save_path, true)) {
					$uuid = new UUID();
					if ($stm->execute([
						':uuid'     => $uuid,
						':md5'      => $file->md5(),
						':filename' => $file->name,
						':claim'    => $request->post->get('claim'),
						':path'     => $file->url->pathname,
						':size'     => $file->size,
						':mime'     => $file->type,
						':user'     => get_person_id_for_user($pdo, $user->id),
					]) and $stm->rowCount() === 1) {
						Headers::status(HTTP::CREATED);
						Headers::contentType('application/json');
						echo json_encode([
							'uuid'     => $uuid,
							'url'      => "{$file->url}",
							'filename' => $file->name,
							'path'     => $file->url->pathname,
							'size'     => $file->size,
							'mime'     => $file->type,
						]);
					} else {
						throw new HTTPException('Error saving upload', HTTP::INTERNAL_SERVER_ERROR);
					}
				} else {
					throw new HTTPException('Error saving upload', HTTP::INTERNAL_SERVER_ERROR);
				}
			}
		}
	});

	$api->on('DELETE', function(API $request): void
	{
		if (! $request->get->has('token')) {
			throw new HTTPException('Missing token in request', HTTP::BAD_REQUEST);
		} elseif (! $request->get->has('uuid')) {
			throw new HTTPException('Missing file UUID', HTTP::BAD_REQUEST);
		} else {
			$pdo = PDO::load();
			$user = User::loadFromToken($pdo, $request->get->get('token', false));
			if (! $user->loggedIn) {
				throw new HTTPException('User data expired or invalid', HTTP::UNAUTHORIZED);
			} elseif (! $user->can('deleteUpload')) {
				throw new HTTPException('You do not have permissions for this action', HTTP::FORBIDDEN);
			} else {
				$file_stm = $pdo->prepare('SELECT `id`, `path` FROM `Attachment` WHERE `uuid` = :uuid LIMIT 1;');
				if ($file_stm->execute([':uuid' => $request->get->get('uuid')]) and $file = $file_stm->fetchObject()) {
					$pdo->beginTransaction();
					$del = $pdo->prepare('DELETE FROM `Attachment` WHERE `id` = :id LIMIT 1;');
					if ($del->execute([':id' => $file->id]) and $del->rowCount() === 1) {
						if (@unlink($_SERVER['DOCUMENT_ROOT'] . $file->path)) {
							$pdo->commit();
							Headers::status(HTTP::NO_CONTENT);
						} else {
							$pdo->rollBack();
							throw new HTTPException('Error deleting file', HTTP::INTERNAL_SERVER_ERROR);
						}
					}
				} else {
					throw new HTTPException('File not found', HTTP::NOT_FOUND);
				}
			}
		}
	});

	$api();
} catch (HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	echo json_encode($e);
}
