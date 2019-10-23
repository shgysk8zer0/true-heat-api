<?php
namespace ResetPassword;

use \shgysk8zer0\PHPAPI\{API, PDO, User, UUID, URL, Headers, HTTPException};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};
use const \Consts\{EMAILS, EMAIL_EXPIRES, PRETTY_DATE, CLIENT_URL};
use function \Functions\{get_person, get_user, mail, log_exception};
use \DateTimeImmutable;
use \Template;
use \Throwable;
use \Exception;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoloader.php';

try {
	$api = new API();

	$api->on('GET', function(API $req): void
	{
		if ($req->get->has('token')) {
			$pdo = PDO::load();
			$stm = $pdo->prepare('SELECT `Person`.`email`,
			`PasswordReset`.`expires`
			FROM `PasswordReset`
			LEFT OUTER JOIN `users` ON `PasswordReset`.`user` = `users`.`id`
			LEFT OUTER JOIN `Person` ON `users`.`person` = `Person`.`id`
			WHERE `token` = :token LIMIT 1;');

			if ($stm->execute([':token' => $req->get->get('token')]) and $info = $stm->fetchObject()) {
				Headers::contentType('application/json');
				echo json_encode($info);
			} else {
				throw new HTTPException('Token not found', HTTP::NOT_FOUND);
			}
		} else {
			throw new HTTPException('Missing reset request token', HTTP::BAD_REQUEST);
		}
	});

	$api->on('POST', function(API $req): void
	{
		if ($req->post->has('token', 'password')) {
			// Change User password & login
			$pdo = PDO::load();
			$now = new DateTimeImmutable();
			$user_stm = $pdo->prepare('SELECT `user`, `expires`,
				TIMESTAMP(`created`) AS `created`
			FROM `PasswordReset`
			WHERE `token` = :token
			LIMIT 1;');

			if ($user_stm->execute(['token' => $req->post->get('token')]) and $reset = $user_stm->fetchObject()) {
				$expires = new DateTimeImmutable($reset->expires);

				if ($now > $expires) {
					throw new HTTPException('Password reset request expired', HTTP::FORBIDDEN);
				} else {
					$user = User::getUser($pdo, $reset->user);

					if ($user->changePassword($req->post->get('password', false))) {
						Headers::status(HTTP::OK);
						$pdo->prepare('DELETE FROM `PasswordReset` WHERE `token` = :token LIMIT 1;')
							->execute([':token' => $req->post->get('token')]);
					} else {
						throw new HTTPException('Error updating password', HTTP::INTERNAL_SERVER_ERROR);
					}
				}
			}
		} elseif ($req->post->has('email')) {
			Headers::set('Connection', 'close');
			Headers::status(HTTP::ACCEPTED);
			ignore_user_abort(true);
			ob_start();
			ob_end_flush();
			flush();
			// Check if user exists and send password reset link
			$pdo = PDO::load();
			$stm = $pdo->prepare('SELECT `users`.`id`
			FROM `users`
			JOIN `Person` on `users`.`person` = `Person`.`id`
			WHERE `Person`.`email` = :email
			LIMIT 1;');

			if ($stm->execute([':email' => $req->post->get('email')]) and $found = $stm->fetchObject()) {
				$pdo->beginTransaction();
				try {
					$user = User::getUser($pdo, $found->id);
					$date = new DateTimeImmutable(EMAIL_EXPIRES);
					$token = new UUID();
					$reset_stm = $pdo->prepare('INSERT INTO `PasswordReset` (
						`token`,
						`user`,
						`expires`
					) VALUES (
						:token,
						:user,
						TIMESTAMP(:expires)
					);');

					if ($reset_stm->execute([
						':token'  => $token,
						':user'   => $user->id,
						'expires' => $date->format('Y-m-d H:i:s'),
					]) and $reset_stm->rowCount() === 1) {
						$tmp = new Template(EMAILS['forgot-password']['template']);
						$url = new URL(CLIENT_URL);
						$url->hash = sprintf('#forgot-password/%s', $token);
						$tmp->expires = $date->format(PRETTY_DATE);
						$tmp->url = $url;
						$from = User::getUser($pdo, 11);
						$success = mail($from->person, $user->person, EMAILS['forgot-password']['subject'], $tmp);

						if ($success) {
							$pdo->commit();
						} else {
							throw new Exception('Error sending password reset email');
						}
					}
				} catch (Throwable $e) {
					log_exception($e);
					file_put_contents('./err.json', json_encode(['error' => [
						'message' => $e->getMessage(),
						'file'    => $e->getFile(),
						'line'    => $e->getLine(),
						'trace'   => $e->getTrace(),
					]], JSON_PRETTY_PRINT));
					if ($pdo->inTranscation()) {
						$pdo->rollback();
					}
				}
			}
		} else {
			throw new HTTPException('Not a valid password reset request', HTTP::BAD_REQUEST);
		}
	});

	$api();
} catch(HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	exit(json_encode($e));
}
