<?php
namespace Users;
use \shgysk8zer0\PHPAPI\{API, User, UUID, PDO, Headers, HTTPException};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};
use function \Functions\{get_user};

function password_gen(int $length = 12): string
{
	// @TODO Make generate strong but usable passwords
	return '';
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoloader.php';

try {
	$api = new API();

	$api->on('GET', function(API $req): void
	{
		if (! $req->get->has('token')) {
			throw new HTTPException('No token given', HTTP::UNAUTHORIZED);
		} elseif (! $user = get_user($req->get)) {
			throw new HTTPException('Token invalid', HTTP::UNAUTHORIZED);
		} elseif (! $user->can('listUsers')) {
			throw new HTTPException('Permission denied', HTTP::FORBIDDEN);
		} else {
			$stm = PDO::load()->prepare('SELECT JSON_OBJECT(
				"uuid", `users`.`uuid`,
				"role", `roles`.`name`,
				"person", JSON_OBJECT(
					"@context", "https://schema.org",
					"@type", "Person",
					"id", `Person`.`id`,
					"identifier", `Person`.`identifier`,
					"name", CONCAT(`Person`.`givenName`,  " ", `Person`.`familyName`),
					"givenName", `Person`.`givenName`,
					"additionalName", `Person`.`additionalName`,
					"familyName", `Person`.`familyName`,
					"telephone", `Person`.`telephone`,
					"email", `Person`.`email`,
					"birthDate", `Person`.`birthDate`,
					"jobTitle", `Person`.`jobTitle`,
					"address", JSON_OBJECT(
						"@type", "PostalAddress",
						"streetAddress", `PostalAddress`.`streetAddress`,
						"postOfficeBoxNumber", `PostalAddress`.`postOfficeBoxNumber`,
						"addressLocality", `PostalAddress`.`addressLocality`,
						"addressRegion", `PostalAddress`.`addressRegion`,
						"postalCode", `PostalAddress`.`postalCode`,
						"addressCountry", `PostalAddress`.`addressCountry`
					),
					"image", JSON_OBJECT(
						"@type", "ImageObject",
						"url", `ImageObject`.`url`,
						"height", `ImageObject`.`height`,
						"width", `ImageObject`.`width`,
						"encodingFormat", `ImageObject`.`encodingFormat`
					)
				)
			) AS `json`
			FROM `users`
			JOIN `Person` ON `users`.`person` = `Person`.`id`
			JOIN `roles` ON `users`.`role` = `roles`.`id`
			LEFT OUTER JOIN `ImageObject` ON `Person`.`image` = `ImageObject`.`id`
			LEFT OUTER JOIN `PostalAddress` ON `Person`.`address` = `PostalAddress`.`id`;');
			$stm->execute();

			$users = array_map(function(object $user): object
			{
				return json_decode($user->json);
			}, $stm->fetchAll());

			Headers::contentType('application/json');
			echo json_encode($users);
		}
	});

	$api->on('POST', function(API $req): void
	{
		if (! $req->post->has('token')) {
			throw new HTTPException('Missing token', HTTP::BAD_REQUEST);
		} elseif (! $user = get_user($req->post)) {
			throw new HTTPException('Token invalid', HTTP::UNAUTHORIZED);
		} elseif ($req->post->has('uuid')) {
			if (! $user->can('editUser')) {
				throw new HTTPException('Permission denied', HTTP::FORBIDDEN);
			} elseif (! ($req->post->has('role') or $req->post->has('password'))) {
				throw new HTTPException('Missing password or role to change', HTTP::BAD_REQUEST);
			} else {
				$pdo = PDO::load();
				$user = $pdo->prepare('UPDATE `users`
				SET `password` = COALESCE(:password, `users`.`password`),
					`role`     = COALESCE(:role, `users`.`role`)
				WHERE `uuid`   = :uuid
				LIMIT 1;');
				if ($user->execute([
					':password' => $req->post->has('password')
						? password_hash($req->post->get('password'), PASSWORD_DEFAULT)
						: null,
					':role'     => $req->post->get('role'),
					':uuid' => $req->post->get('uuid'),
				]) and $user->rowCount() === 1) {
					Headers::status(HTTP::OK);
					Headers::contentType('application/json');
					echo json_encode([
						'message' => 'User updated',
						'status'  => HTTP::OK,
					]);
				} else {
					throw new HTTPException('Error updating user', HTTP::INTERNAL_SERVER_ERROR);
				}
			}
			// @TODO lift password requirement
		} elseif (! ($req->post->has('password', 'person') and $req->post->person->has('givenName', 'familyName', 'email'))) {
			throw new HTTPException('Missing required inputs for creating user', HTTP::BAD_REQUEST);
		} else {
			$pdo = PDO::load();
			$pdo->beginTransaction();

			try {
				$user = $pdo->prepare('INSERT INTO `users` (
					`uuid`,
					`password`,
					`person`,
					`role`
				) VALUES (
					:uuid,
					:password,
					:person,
					:role
				);');

				$person = $pdo->prepare('INSERT INTO `Person` (
					`identifier`,
					`givenName`,
					`additionalName`,
					`familyName`,
					`email`,
					`telephone`,
					`jobTitle`,
					`gender`
				) VALUES (
					:uuid,
					:givenName,
					:additionalName,
					:familyName,
					:email,
					:telephone,
					:jobTitle,
					:gender
				);');

				if (! $person->execute([
					':uuid'           => new UUID(),
					':givenName'      => $req->post->person->get('givenName'),
					':additionalName' => $req->post->person->get('additionalName'),
					':familyName'     => $req->post->person->get('familyName'),
					':email'          => $req->post->person->get('email'),
					':telephone'      => $req->post->person->get('telephone'),
					':jobTitle'       => $req->post->person->get('jobTitle'),
					':gender'         => $req->post->person->get('gender'),
				]) or ! $person_id = $pdo->lastInsertId()) {
					throw new HTTPException('Error saving Person', HTTP::INTERNAL_SERVER_ERROR);
				} elseif (! $user->execute([
					':uuid'     => new UUID(),
					':password' => password_hash($req->post->get('password', false, password_gen()), PASSWORD_DEFAULT),
					':person'   => $person_id,
					':role'     => $req->post->get('role', false, 2),
				]) or ! $user_id = $pdo->lastInsertId()) {
					throw new HTTPException('Error creating user', HTTP::INTERNAL_SERVER_ERROR);
				} else {
					$pdo->commit();
					Headers::status(HTTP::CREATED);
				}
			} catch (Throwable $e) {
				$pdo->rollBack();
				throw $e;
			}
		}
	});

	$api->on('DELETE', function(API $req): void
	{
		if (! $req->get->has('token')) {
			throw new HTTPException('Missing token', HTTP::BAD_REQUEST);
		} elseif (! $req->get->has('uuid')) {
			throw new HTTPException('Missing user UUID', HTTP::BAD_REQUEST);
		} elseif (! $user = get_user($req->get)) {
			throw new HTTPException('Token invalid or expired', HTTP::UNAUTHORIZED);
		} elseif (! $user->can('deleteUser')) {
			throw new HTTPException('You do not have permission to perform this action', HTTP::FORBIDDEN);
		} else {
			$stm = PDO::load()->prepare('DELETE FROM `users` WHERE `uuid` = :uuid LIMIT 1;');

			if ($stm->execute([':uuid' => $req->get->get('uuid')]) and $stm->rowCount() === 1) {
				Headers::status(HTTP::NO_CONTENT);
			} else {
				throw new HTTPException('User not found', HTTP::NOT_FOUND);
			}
		}
	});

	$api();
} catch (HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	echo json_encode($e);
}
