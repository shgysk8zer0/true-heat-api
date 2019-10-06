<?php
namespace Claim;

use \shgysk8zer0\PHPAPI\{API, PDO, Headers, User, UUID, HTTPException};
use \shgysk8zer0\PHPAPI\Schema\{Person};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};
use function \Functions\{get_user, get_person, get_organization};
use \StdClass;
use \DateTime;

function get_uuid(PDO $pdo, string $table, int $id, string $key = 'identifier'):? string
{
	$stm = $pdo->prepare("SELECT `{$key}` FROM `${table}` WHERE `id` = :id LIMIT 1;");

	if ($stm->execute([':id' => $id]) and $result = $stm->fetchObject()) {
		return $result->{$key};
	} else {
		return null;
	}
}

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
						"name", CONCAT(`Person`.`givenName`, \' \', `Person`.`familyName`),
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
				LEFT OUTER JOIN `Person` ON `Person`.`identifier` = `Claim`.`customer`
				LEFT OUTER JOIN `PostalAddress` ON `Person`.`address` = `PostalAddress`.`id`
				LEFT OUTER JOIN `Organization` ON `Person`.`worksFor` = `Organization`.`id`
				LEFT OUTER JOIN `ImageObject` ON `Person`.`image` = `ImageObject`.`id`
				WHERE `uuid` = :uuid
				LIMIT 1;');

				if ($stm->execute([':uuid' => $req->get->get('uuid')]) and $results = $stm->fetchObject()) {
					$results->created = (new DateTime("{$results->created}Z"))->format(DateTime::W3C);
					$results->customer = json_decode($results->customer);

					if (isset($results->lead)) {
						$results->lead = get_person($pdo, $results->lead, 'identifier');
					}

					if (isset($results->contractor)) {
						$results->contractor = get_person($pdo, $results->contractor, 'identifier');
					}

					$notes_stm = $pdo->prepare('SELECT
						`Note`.`uuid`,
						`Note`.`status`,
						`Note`.`text`,
						`Note`.`created`,
						`Note`.`updated`,
						JSON_OBJECT(
							"name", CONCAT(`Person`.`givenName`, " ", `Person`.`familyName`),
							"givenName", `Person`.`givenName`,
							"familyName", `Person`.`familyName`
						) AS `author`
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

					if ($notes_stm->execute([':claim' => $results->uuid])) {
						$results->notes = array_map(function(object $note): object
						{
							$note->author = json_decode($note->author);
							return $note;
						}, $notes_stm->fetchAll());

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
							"name", CONCAT(`Person`.`givenName`, " ", `Person`.`familyName`),
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
					LEFT OUTER JOIN `Person` ON `Claim`.`customer` = `Person`.`identifier`
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
					$parsed->lead = get_person($pdo, $parsed->lead, 'identifier');
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
			} elseif ($req->post->has('uuid') and strlen($req->post->get('uuid')) !== 0) {
				if (! $user->can('editClaim')) {
					throw new HTTPException('You do not have permissions for this action', HTTP::FORBIDDEN);
				} elseif ($req->post->has('status') and ! $req->post->has('customer')) {
					$stm = $pdo->prepare('UPDATE `Claim` SET `status` = :status WHERE `uuid` = :uuid LIMIT 1;');
					if ($stm->execute([
						':status' => $req->post->get('status'),
						':uuid'   => $req->post->get('uuid'),
					]) and $stm->rowCount() !== 0) {
						Headers::status(HTTP::OK);
						Headers::contentType('application/json');
						echo json_encode([
							'message' => 'Claim status updated',
							'status'  => HTTP::OK,
						]);
					} else {
						throw new HTTPException('Error updating claim status', HTTP::INTERNAL_SERVER_ERROR);
					}
				} else {
					throw new HTTPException('Editing claims is currently disabled', HTTP::NOT_IMPLEMENTED);
				}
			} elseif (! $user->can('createClaim')) {
				throw new HTTPException('You do not have permissions for this action', HTTP::FORBIDDEN);
			} else {
				$pdo->beginTransaction();

				try {
					// @TODO Figure out how to store contractor & lead
					$uuid = new UUID();
					$claim = $pdo->prepare('INSERT INTO `Claim` (
						`uuid`,
						`status`,
						`customer`,
						`contractor`,
						`lead`
					) VALUES (
						:uuid,
						:status,
						:customer,
						:contractor,
						:lead
					);');

					$customer = $pdo->prepare('INSERT INTO `Person` (
						`identifier`,
						`givenName`,
						`familyName`,
						`telephone`,
						`email`,
						`address`
					) VALUES (
						:uuid,
						:givenName,
						:familyName,
						:telephone,
						COALESCE(:email, ""),
						:address
					);');

					$address = $pdo->prepare('INSERT INTO `PostalAddress` (
						`identifier`,
						`streetAddress`,
						`postOfficeBoxNumber`,
						`addressLocality`,
						`addressRegion`,
						`postalCode`,
						`addressCountry`
					) VALUES (
						:uuid,
						:streetAddress,
						:postOfficeBoxNumber,
						:addressLocality,
						COALESCE(:addressRegion, "CA"),
						:postalCode,
						COALESCE(:addressCountry, "US")
					);');

					if (! $address->execute([
						':uuid'                => new UUID(),
						':streetAddress'       => $req->post->customer->address->get('streetAddress'),
						':postOfficeBoxNumber' => $req->post->customer->address->get('postOfficeBoxNumber'),
						':addressLocality'     => $req->post->customer->address->get('addressLocality'),
						':addressRegion'       => $req->post->customer->address->get('addressRegion', true, 'CA'),
						':postalCode'          => $req->post->customer->address->get('postalCode'),
						':addressCountry'      => $req->post->customer->address->get('addressCountry'),
					]) or ! ($addr_id = $pdo->lastInsertId())) {
						throw new HTTPException('Error saving customer address', HTTP::INTERNAL_SERVER_ERROR);
					} elseif (! $customer->execute([
						':uuid'       => new UUID(),
						':givenName'  => $req->post->customer->get('givenName'),
						':familyName' => $req->post->customer->get('familyName'),
						':telephone'  => $req->post->customer->get('telephone'),
						':email'      => $req->post->customer->get('email'),
						':address'    => $addr_id,
					]) or ! ($customer_id = $pdo->lastInsertId())) {
						throw new HTTPException('Error saving customer', HTTP::INTERNAL_SERVER_ERROR);
					} elseif (! $claim->execute([
						':uuid'       => $uuid,
						':status'     => $req->post->get('status', true, 'open'),
						':customer'   => get_uuid($pdo, 'Person', $customer_id),
						':contractor' => $req->post->get('contractor'),
						':lead'       => $req->post->get('lead'),
					]) or ! ($claim_id = $pdo->lastInsertId())) {
						throw new HTTPException('Error saving claim', HTTP::INTERNAL_SERVER_ERROR);
					} else {
						// Everything saved successfully
						$pdo->commit();

						Headers::status(HTTP::CREATED);
						Headers::contentType('application/json');

						echo json_encode([
							'message' => 'Claim created',
							'status'  => HTTP::CREATED,
							'claim'   => $uuid,
						]);
					}
				} catch (Throwable $e) {
					$pdo->rollBack();
					throw $e;
				}
			}
		}
	});

	$api->on('DELETE', function(API $req): void
	{
		if (! $req->get->has('token')) {
			throw new HTTPException('Not authenticated', HTTP::UNAUTHORIZED);
		} elseif (! $req->get->has('uuid')) {
			throw new HTTPException('No claim selected', HTTP::BAD_REQUEST);
		} elseif (! $pdo = PDO::load()) {
			throw new HTTPException('Error connecting to database', HTTP::INTERNAL_SERVER_ERROR);
		} elseif (! $user = User::loadFromToken($pdo, $req->get->get('token', false))) {
			throw new HTTPException('Error signing in', HTTP::INTERNAL_SERVER_ERROR);
		} elseif (! $user->loggedIn) {
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
