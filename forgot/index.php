<?php
namespace Forgot;

use \shgysk8zer0\PHPAPI\{API, PDO, Headers, User, HTTPException};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};
use function \Functions\{email};

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoloader.php';

try {
	$api = new API('*');

	$api->on('POST', function(API $req): void
	{
		if ($req->post->has('email')) {
			$stm = PDO::load()->prepare('SELECT `Person`.`email`
			FROM `users`
			JOIN `Person` ON `users`.`person` = `Person`.`id`
			WHERE `Person`.`email` = :email
			LIMIT 1;');
			if ($stm->execute([':email' => $req->post->get('email')])) {
				$person = $stm->fetchObject();
				if (email($person->email, 'subject', 'body', [
					'reply-to' => 'noreplay@trueheatsolutions.com',
				])) {
					Headers::status(HTTP::CREATED);
					echo json_encode([
						'status'       => HTTP::CREATED,
						'notification' => [
							'title'   => 'Email sent',
							'body'    => 'If an account exists with that email address, a password recovery email has been sent.',
							'icon'    => '/img/octicons/bell.svg',
						],
					]);
				} else {
					throw new HTTPException('Error sending email', HTTP::INTERNAL_SERVER_ERROR);
				}
			} else {
				throw new HTTPException('Error requesting user data', HTTP::INTERNAL_SERVER_ERROR);
			}
		}
	});

	$api();
} catch (HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	exit(json_encode($e));
}
