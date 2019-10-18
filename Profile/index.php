<?php
namespace Profile;
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoloader.php';
use \shgysk8zer0\PHPAPI\{PDO, API, HTTPException, Headers, UUID};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};
use \Throwable;
use function \Functions\{get_user, get_person_uuid_from_user_uuid};

try {
	$api = new API('*');

	$api->on('POST', function(API $req): void
	{
		if (! $req->post->has('token', 'uuid', 'person')) {
			throw new HTTPException('Missing profile info or token', HTTP::BAD_REQUEST);
		} elseif (! $user = get_user($req->post)) {
			throw new HTTPException('Token invalid or expired', HTTP::UNAUTHORIZED);
		} elseif ($user->uuid === $req->post->get('uuid')) {
			// User is changing own profile
			$pdo = PDO::load();
			$pdo->beginTransaction();

			if (isset($req->post->person->password)) {
				$np = $req->post->person->password->get('new');
				$rp = $req->post->person->password->get('repeat');
				$cp = $req->post->person->password->get('current');
				// @TODO Check given password->current matches current password
				if (! $req->post->person->password->has('new', 'current', 'repeat')) {
					throw new HTTPException('Missing one or more required password inputs', HTTP::BAD_REQUEST);
				} elseif ($np !== $rp) {
					throw new HTTPException('Password repeat does not match', HTTP::BAD_REQUEST);
				} elseif ($cp === $np) {
					throw new HTTPException('Cannot set new password same as current', HTTP::BAD_REQUEST);
				} elseif (! is_string($np) or strlen($np) < 8) {
					throw new HTTPException('Password too weak', HTTP::BAD_REQUEST);
				} else {
					$user_stm = $pdo->prepare('UPDATE `users` SET `password` = COALESCE(:new_pass, `password`)
						WHERE `id` = :id
						LIMIT 1;');

					if (! ($user_stm->execute([
						':new_pass' => password_hash($np, PASSWORD_DEFAULT),
						':id'       => $user->id,
					]) and $user_stm->rowCount() === 1)) {
						throw new HTTPException('Error updating password', HTTP::INTERNAL_SERVER_ERROR);
					}
					unset($cp, $rp, $np);
				}
			}

			try {
				// Hacky method to force something to change by seetting `updated` to `NOW()`
				$person_stm = $pdo->prepare('UPDATE `Person` SET
						`givenName`     = COALESCE(:givenName, `givenName`),
						`familyName`    = COALESCE(:familyName, `familyName`),
						`email`         = COALESCE(:email, `email`),
						`telephone`     = COALESCE(:telephone, `telephone`),
						`updated`       = NOW()
					WHERE `identifier` = :uuid
					LIMIT 1;');

				if ($person_stm->execute([
					':givenName'  => $req->post->person->get('givenName'),
					':familyName' => $req->post->person->get('familyName'),
					':email'      => $req->post->person->get('email'),
					':telephone'  => $req->post->person->get('telephone'),
					':uuid'       => $user->person->identifier,
				])  and $person_stm->rowCount() === 1) {
					Headers::contentType('application/json');
					Headers::status(HTTP::OK);
					$pdo->commit();

					echo json_encode([
						'status'  => HTTP::OK,
						'message' => 'Updated',
					]);
				} else {
					// @TODO Check that error is not no updated info
					throw new HTTPException('Error updating profile', HTTP::INTERNAL_SERVER_ERROR);
				}
			} catch (HTTPException $e) {
				if ($pdo->inTransaction()) {
					$pdo->rollBack();
				}
				throw $e;
			} catch (Throwable $e) {
				throw new HTTPException('Error updating user', HTTP::INTERNAL_SERVER_ERROR);
			}
		} elseif (! $user->can('editUser')) {
			throw new HTTPException('You do not have permission to perform this action', HTTP::FORBIDDEN);
		} else {
			// User is editing other profile
			$pdo = PDO::load();
			$pdo->beginTransaction();

			try {
				$u_stm = $pdo->prepare('UPDATE `users` SET
					`role`     = COALESCE(:role, `role`),
					`password` = COALESCE(:password, `password`),
					`updated`  = NOW()
				WHERE `uuid` = :uuid
				LIMIT 1;');

				$tmp_p = null;

				if (isset(
					$req->post->person->password,
					$req->post->person->password->new
				) and is_string($req->post->person->password->new)
					and $req->post->person->password->new !== '') {
					$tmp_p = $req->post->person->password->get('new');
					Headers::set('X-PW', $tmp_p);
					if (strlen($tmp_p) > 8) {
						$tmp_p = password_hash($tmp_p, PASSWORD_DEFAULT);
					} else {
						throw new HTTPException('Password too weak', HTTP::BAD_REQUEST);
					}
				}

				if (! ($u_stm->execute([
					':role'     => $req->post->person->get('role'),
					':uuid'     => $req->post->get('uuid'),
					':password' => $tmp_p,
				]) and $u_stm->rowCount() === 1)) {
					throw new HTTPException('Error updating user info', HTTP::INTERNAL_SERVER_ERROR);
				}

				unset($tmp_p);

				$p_stm = $pdo->prepare('UPDATE `Person` SET
						`givenName`  = COALESCE(:givenName, `givenName`),
						`familyName` = COALESCE(:familyName, `familyName`),
						`email`      = COALESCE(:email, `email`),
						`telephone`  = COALESCE(:telephone, `telephone`),
						`updated`     = NOW()
					WHERE `identifier` = :uuid
					LIMIT 1;');

				if (! ($p_stm->execute([
					':givenName'  => $req->post->person->get('givenName'),
					':familyName' => $req->post->person->get('familyName'),
					':email'      => $req->post->person->get('email'),
					':telephone'  => $req->post->person->get('telephone'),
					':uuid'       => get_person_uuid_from_user_uuid($pdo, $req->post->get('uuid')),
				]) and $p_stm->rowCount() === 1)) {
					throw new HTTPException('Error updating user data', HTTP::INTERNAL_SERVER_ERROR);
				} else {
					Headers::status(HTTP::OK);
					Headers::contentType('application/json');
					$pdo->commit();
					echo json_encode([
						'status'  => HTTP::OK,
						'message' => 'success',
					]);
				}
			} catch (Throwable $e) {
				if ($pdo->inTransaction()) {
					$pdo->rollBack();
				}
				throw new HTTPException('Error updating user info', HTTP::INTERNAL_SERVER_ERROR);
			}
		}
	});

	$api();
} catch(HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	echo json_encode($e);
}
