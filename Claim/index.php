<?php
namespace Claim;

use \shgysk8zer0\PHPAPI\{API, PDO, Headers, User, UUID, HTTPException};
use \shgysk8zer0\PHPAPI\Schema\{Person};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};
use function \Functions\{get_user, get_person, get_organization};
use \StdClass;
use \DateTime;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoloader.php';

try {
	$api = new API('*');

	$api->on('GET', function(API $req): void
	{
		if ($user = get_user($req->get)) {
			$pdo = PDO::load();

			if ($req->get->has('uuid')) {
				$stm = $pdo->prepare('SELECT `uuid`,
					`status`,
					`Claim`.`created`,
					JSON_OBJECT(
						"@context", "https://schema.org",
						"@type", "Person",
						"identifier", `Person`.`identifier`,
						"givenName", `Person`.`givenName`,
						"additionalName", `Person`.`additionalName`,
						"familyName", `Person`.`familyName`,
						"email", `Person`.`email`,
						"telephone", `Person`.`telephone`,
						"worksFor", `Organization`.`name`,
						"jobTitle", `Person`.`jobTitle`,
						"address", JSON_OBJECT(
							"@type", "PostalAddress",
							"identifier", `PostalAddress`.`identifier`,
							"streetAddress", `PostalAddress`.`streetAddress`,
							"postOfficeBoxNumber", `PostalAddress`.`postOfficeBoxNumber`,
							"addressLocality", `PostalAddress`.`addressLocality`,
							"addressRegion", `PostalAddress`.`addressRegion`,
							"postalCode", `PostalAddress`.`postalCode`,
							"addressCountry", `PostalAddress`.`addressCountry`
						),
						"image", JSON_OBJECT(
							"@type", "ImageObject",
							"identifier", `ImageObject`.`identifier`,
							"url", `ImageObject`.`url`,
							"height", `ImageObject`.`height`,
							"width", `ImageObject`.`width`
						)
					) AS `customer`,
					`contractor`,
					`lead`,
					`hours`,
					`price`
				FROM `Claim`
				LEFT OUTER JOIN `Person` ON `Person`.`id` = `Claim`.`customer`
				LEFT OUTER JOIN `PostalAddress` ON `Person`.`address` = `PostalAddress`.`id`
				LEFT OUTER JOIN `Organization` ON `Person`.`worksFor` = `Organization`.`id`
				LEFT OUTER JOIN `ImageObject` ON `Person`.`image` = `ImageObject`.`id`
				WHERE `uuid` = :uuid
				LIMIT 1;');

				if ($stm->execute([':uuid' => $req->get->get('uuid')]) and $results = $stm->fetchObject()) {
					$results->created = (new DateTime("{$results->created}Z"))->format(DateTime::W3C);
					$results->customer = json_decode($results->customer);

					if (isset($results->lead)) {
						$results->lead = get_person($pdo, $results->lead);
					}

					if (isset($results->contractor)) {
						$results->contractor = get_person($pdo, $results->contractor);
					}

					$notes = $pdo->prepare('SELECT
						`Note`.`uuid`,
						`Note`.`status`,
						`Note`.`text`,
						`Note`.`created`,
						`Note`.`updated`,
						`Person`.`givenName`,
						`Person`.`familyName`
					FROM `Note`
					LEFT OUTER JOIN `Person` ON `Note`.`author` = `Person`.`id`
					WHERE `Note`.`claim` = :claim;');

					$attachments = $pdo->prepare('SELECT `Attachment`.`uuid`,
						`Attachment`.`path`,
						`Attachment`.`size`,
						`Attachment`.`mime`,
						`Attachment`.`created`,
						`Person`.`identifier` AS `userUUID`,
						`Person`.`givenName`,
						`Person`.`familyName`
						FROM `Attachment`
						LEFT OUTER JOIN `Person` ON `Attachment`.`uploadedBy` = `Person`.`id`
						WHERE `Attachment`.`claim` = :claim;');
					if ($notes->execute([':claim' => $results->uuid])) {
						$results->notes = $notes->fetchAll();
					} else {
						$results->notes = [];
					}

					if ($attachments->execute([':claim' => $results->uuid])) {
						$results->attachments = $attachments->fetchAll();
					} else {
						$results->attachments = [];
					}

				} else {
					throw new HTTPException('Claim not found', HTTP::NOT_FOUND);
				}
			} else {
				$stm = $pdo->prepare(sprintf('SELECT JSON_OBJECT (
						"uuid", `Claim`.`uuid`,
						"status", `Claim`.`status`,
						"created", `Claim`.`created`,
						"customer", JSON_OBJECT (
							"@context", "https://schema.org",
							"@type", "Person",
							"identifier", `Person`.`identifier`,
							"givenName", `Person`.`givenName`,
							"familyName", `Person`.`familyName`,
							"worksFor", JSON_OBJECT (
								"identifier", `Organization`.`identifier`,
								"name", `Organization`.`name`
							)
						),
						"lead", `Claim`.`lead`
					) AS `json`
					FROM `Claim`
					LEFT OUTER JOIN `Person` ON `Claim`.`customer` = `Person`.`id`
					LEFT OUTER JOIN `Organization` ON `Person`.`worksFor` = `Organization`.`id`
					WHERE (:all OR `Claim`.`status` = :status)
					AND (:allow OR `Claim`.`lead` = :user)
					LIMIT %d, %d;',
					intval($req->get->get('from', true, 0)),
					intval($req->get->get('thru', true, 30))
				));

				$stm->bindValue(':allow', $user->can('listClaims'), PDO::PARAM_BOOL);
				$stm->bindValue(':user', $user->id, PDO::PARAM_INT);
				$stm->bindValue(':all', ! $req->get->has('status'), PDO::PARAM_BOOL);
				$stm->bindValue(':status', $req->get->get('status'));

				$stm->execute();

				$results = array_map(function(object $claim) use ($pdo): object
				{
					$parsed = json_decode($claim->json);
					$parsed->lead = get_person($pdo, $parsed->lead);
					return $parsed;
				}, $stm->fetchAll());
			}

			Headers::contentType('application/json');
			echo json_encode($results);
		} else {
			throw new HTTPException('Unauthorized', HTTP::UNAUTHORIZED);
		}
	});

	$api->on('POST', function(API $req): void
	{
		if (! $req->post->has('token')) {
			throw new HTTPException('Not authorized', HTTP::UNAUTHORIZED);
		} else {
			$pdo = PDO::load();
			$user = User::loadFromToken($pdo, $req->post->get('token', false));

			if (! $user->loggedIn) {
				throw new HTTPException('User data expired or invalid', HTTP::UNAUTHORIZED);
			} elseif ($req->post->has('uuid') and ! $user->can('editClaim')) {
				throw new HTTPException('You do not have permissions for this action', HTTP::FORBIDDEN);
			} elseif (! $user->can('createClaim')) {
				throw new HTTPException('You do not have permissions for this action', HTTP::FORBIDDEN);
			} else {
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
		}
	});

	$api->on('DELETE', function(API $req): void
	{
		if ($req->get->has('uuid', 'token')) {
			$pdo = PDO::load();
			$user = User::loadFromToken($pdo, $req->get->get('token', false));

			if (! $user->loggedIn) {
				throw new HTTPException('User data expired or invalid', HTTP::UNAUTHORIZED);
			} elseif (! $user->can('deleteClaim')) {
				throw new HTTPException('You do not have permissions for this action', HTTP::FORBIDDEN);
			} else {
				$stm = $pdo->prepare('DELETE FROM `Claim` WHERE `uuid` = :uuid LIMIT 1;');

				if ($stm->execute([':uuid' => $req->get->get('uuid')]) and $stm->rowCount() !== 0) {
					Headers::status(HTTP::NO_CONTENT);
				} else {
					throw new HTTPException('Claim not found', HTTP::NOT_FOUND);
				}
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
