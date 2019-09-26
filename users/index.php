<?php
namespace Users;
use \shgysk8zer0\PHPAPI\{API, User, PDO, Headers, HTTPException};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};
use function \Functions\{get_user};

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
		throw new HTTPException('Not implemented yet', HTTP::NOT_IMPLEMENTED);
	});

	$api->on('DELETE', function(API $req): void
	{
		throw new HTTPException('Not implemented yet', HTTP::NOT_IMPLEMENTED);
	});

	$api();
} catch (HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	echo json_encode($e);
}
