<?php
namespace Claim;

use \shgysk8zer0\PHPAPI\{API, PDO, Headers, UUID, HTTPException};
use \shgysk8zer0\PHPAPI\Schema\{Person};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};
use function \Functions\{get_user, get_person, get_organization};
use \StdClass;
use \DateTime;

require_once '../autoloader.php';

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
					`Person`.`givenName`,
					`Person`.`additionalName`,
					`Person`.`familyName`,
					`Person`.`email`,
					`Person`.`telephone`,
					`Person`.`worksFor`,
					`Person`.`jobTitle`,
					`PostalAddress`.`streetAddress`,
					`PostalAddress`.`addressLocality`,
					`PostalAddress`.`addressRegion`,
					`PostalAddress`.`postalCode`,
					`PostalAddress`.`addressCountry`,
					`contractor`,
					`lead`,
					`hours`,
					`price`
				FROM `Claim`
				LEFT OUTER JOIN `Person` ON `Person`.`id` = `Claim`.`customer`
				LEFT OUTER JOIN `PostalAddress` ON `Person`.`address` = `PostalAddress`.`id`
				WHERE `uuid` = :uuid
				LIMIT 1;');

				$stm->execute([':uuid' => $req->get->get('uuid')]);
				$results = $stm->fetchObject();

				$created = new \DateTime("{$results->created}Z");
				$results->created = $created->format(\DateTime::W3C);

				if (isset($results->givenName, $results->familyName)) {
					$results->customer = new StdClass();
					$results->customer->{'@context'} = 'https://schema.org';
					$results->customer->{'@type'} = 'Person';
					$results->customer->givenName = $results->givenName;
					$results->customer->additionalName = $results->additionalName;
					$results->customer->familyName = $results->familyName;
					$results->customer->telephone = $results->telephone;
					$results->customer->email = $results->email;

					$results->customer->jobTitle = $results->jobTitle;

					if (isset($results->worksFor)) {
						$results->worksFor = get_organization($pdo, $results->worksFor);
					}

					if (isset($results->streetAddress, $results->addressLocality)) {
						$results->customer->address = new StdClass();
						$results->customer->address->{'@type'} = 'PostalAddress';
						$results->customer->address->streetAddress = $results->streetAddress;
						$results->customer->address->addressLocality = $results->locality;
						$results->customer->address->addressRegion = $results->addressRegion;
						$results->customer->address->postalCode = intval($results->postalCode);
						$results->customer->address->addressCountry = $results->addressCountry;
					}
				}

				if (isset($results->lead)) {
					$results->lead = get_person($pdo, $results->lead);
				}

				if (isset($results->contractor)) {
					$results->contractor = get_person($pdo, $results->contractor);
				}
				unset($results->givenName, $results->additionalName, $results->familyName,
					$results->telephone, $results->email, $results->streetAddress,
					$results->addressLocality, $results->addressRegion, $results->postalCode,
					$results->addressCountry, $results->worksFor, $results->jobTitle);
			} else {
				$stm = $pdo->prepare('SELECT `uuid`,
					`status`,
					`created`,
					`customer`,
					`contractor`,
					`lead`,
					`hours`,
					`price`
				FROM `Claim`;');

				$stm->execute();
				$results = array_map(function(object $claim): object
				{
					if (isset($claim->customer)) {
						$claim->customer = new Person($claim->customer);
					}

					return $claim;
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
	});

	$api->on('DELETE', function(API $req): void
	{
		if ($req->get->has('uuid', 'token')) {
			$pdo = PDO::load();
			$stm = $pdo->prepare('DELETE FROM `Claim` WHERE `uuid` = :uuid LIMIT 1;');
			if ($stm->execute([':uuid' => $req->get->get('uuid')]) and $stm->rowCount() !== 0) {
				Headers::status(HTTP::NO_CONTENT);
			} else {
				throw new HTTPException('Claim not found', HTTP::BAD_REQUEST);
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
