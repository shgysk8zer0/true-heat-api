<?php
namespace User;
use \shgysk8zer0\PHPAPI\{PDO, User, Headers, HTTPException, API};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};
use function \Functions\{is_pwned};
use \Throwable;
use \DateTime;
use \StdClass;

require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoloader.php');

try {
	$api = new API('*');

	$api->on('GET', function(API $api): void
	{
		if (! $api->get->has('token')) {
			throw new HTTPException('Missing token in request', HTTP::BAD_REQUEST);
		} else {
			Headers::contentType('application/json');
			$user = User::loadFromToken(PDO::load(), $api->get->get('token', false));

			if (! $user->loggedIn) {
				throw new HTTPException('User data expired or invalid', HTTP::UNAUTHORIZED);
			} elseif ($api->get->has('uuid')) {
				if ($user->uuid === $api->get->get('uuid') or $user->can('listUsers')) {
					$stm = PDO::load()->prepare('SELECT `users`.`uuid` AS `identifier`,
						`roles`.`name` AS `role`,
						`roles`.`id` AS `roleId`,
						`users`.`created`,
						`Person`.`honorificPrefix`,
						`Person`.`givenName`,
						`Person`.`additionalName`,
						`Person`.`familyName`,
						`Person`.`gender`,
						`Person`.`birthDate`,
						`Person`.`email`,
						`Person`.`telephone`,
						`PostalAddress`.`streetAddress`,
						`PostalAddress`.`postOfficeBoxNumber`,
						`PostalAddress`.`addressLocality`,
						`PostalAddress`.`addressRegion`,
						`PostalAddress`.`postalCode`,
						`PostalAddress`.`addressCountry`,
						`ImageObject`.`url` AS `image`,
						`Person`.`jobTitle`,
						`Organization`.`name` AS `worksFor`
					FROM `users`
					JOIN `Person` ON `users`.`person` = `Person`.`id`
					LEFT OUTER JOIN `ImageObject` ON `Person`.`image` = `ImageObject`.`id`
					LEFT OUTER JOIN `Organization` ON `Person`.`worksFor` = `Organization`.`id`
					LEFT OUTER JOIN `PostalAddress` ON `Person`.`address` = `PostalAddress`.`id`
					LEFT OUTER JOIN `roles` ON `users`.`role` = `roles`.`id`
					WHERE `Person`.`identifier` = :uuid
					OR `users`.`uuid` = :uuid
					LIMIT 1;');

					if ($stm->execute([':uuid' => $api->get->get('uuid')]) and $found = $stm->fetchObject()) {
						Headers::contentType('application/json');
						echo json_encode($found);
					} else {
						throw new HTTPException('User not found', HTTP::NOT_FOUND);
					}
				} else {
					throw new HTTPException('You do not have permission for that', HTTP::FORBIDDEN);
				}
			} else {
				$stm = PDO::load()->prepare('SELECT `users`.`uuid` AS `identifier`,
					`roles`.`name` AS `role`,
					`users`.`created`,
					`Person`.`honorificPrefix`,
					`Person`.`givenName`,
					`Person`.`additionalName`,
					`Person`.`familyName`,
					`Person`.`gender`,
					`Person`.`birthDate`,
					`Person`.`email`,
					`Person`.`telephone`,
					`PostalAddress`.`streetAddress`,
					`PostalAddress`.`postOfficeBoxNumber`,
					`PostalAddress`.`addressLocality`,
					`PostalAddress`.`addressRegion`,
					`PostalAddress`.`postalCode`,
					`PostalAddress`.`addressCountry`,
					`ImageObject`.`url` AS `image`,
					`Person`.`jobTitle`,
					`Organization`.`name` AS `worksFor`
				FROM `users`
				JOIN `Person` ON `users`.`person` = `Person`.`id`
				LEFT OUTER JOIN `ImageObject` ON `Person`.`image` = `ImageObject`.`id`
				LEFT OUTER JOIN `Organization` ON `Person`.`worksFor` = `Organization`.`id`
				LEFT OUTER JOIN `PostalAddress` ON `Person`.`address` = `PostalAddress`.`id`
				LEFT OUTER JOIN `roles` ON `users`.`role` = `roles`.`id`;');

				$stm->execute();
				$users = array_map(function(object $user): object
				{
					$user->{'@context'} = 'https://schema.org';
					$user->{'@type'} = 'Person';
					$user->created = (new DateTime("{$user->created}Z"))->format(DateTime::W3C);
					$user->address = new StdClass();
					$user->address->{'@type'} = 'PostalAddress';
					$user->address->streetAddress = $user->streetAddress;
					$user->address->postOfficeBoxNumber = $user->postOfficeBoxNumber;
					$user->address->addressLocality = $user->addressLocality;
					$user->address->addressRegion = $user->addressRegion;
					$user->address->postalCode = $user->postalCode;
					$user->address->addressCountry = $user->addressCountry;
					unset($user->streetAddress, $user->postOfficeBoxNumber,
					$user->addressLocality, $user->addressRegion, $user->postalCode,
					$user->addressCountry);
					return $user;
				}, $stm->fetchAll());
				echo json_encode($users);
			}
		}
	});

	$api->on('POST', function(API $api): void
	{
		try {
			$user = new User(PDO::load());
			if ($user->create($api->post)) {
				Headers::status(HTTP::CREATED);
				echo json_encode($user);
			} else {
				throw new HTTPException('Error registering user', HTTP::UNAUTHORIZED);
			}
		} catch (HTTPEXception $e) {
			throw $e;
		}
	});

	$api->on('DELETE', function(API $api): void
	{
		if (! $api->get->has('token')) {
			throw new HTTPException('Missing token in request', HTTP::BAD_REQUEST);
		} else {
			Headers::contentType('application/json');
			$user = User::loadFromToken(PDO::load(), $api->get('token', false));
			if (! $user->loggedIn) {
				throw new HTTPException('User data expired or invalid', HTTP::UNAUTHORIZED);
			} elseif ($user->delete()) {
				echo json_encode(['status' => 'success']);
			} else {
				throw new HTTPException('Error deleting user', HTTP::INTERNAL_SERVER_ERROR);
			}
		}
	});

	$api();
} catch (HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	echo json_encode($e);
}
