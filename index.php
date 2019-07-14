<?php
namespace Index;

use \shgysk8zer0\PHPAPI\{PDO, Headers};
use \Throwable;
use const \Consts\{CREDS_FILE};

try {
	require_once __DIR__ . DIRECTORY_SEPARATOR . 'autoloader.php';

	$pdo = PDO::load(CREDS_FILE);
	$stm = $pdo->prepare('SELECT `users`.`password`,
		`Person`.`givenName`,
		`Person`.`familyName`,
		`Person`.`email`,
		`Person`.`telephone`,
		`PostalAddress`.`streetAddress`,
		`PostalAddress`.`postOfficeBoxNumber`,
		`PostalAddress`.`addressLocality` AS `city`,
		`PostalAddress`.`addressRegion` AS `state`,
		`PostalAddress`.`postalCode` AS `zip`,
		`Organization`.`name` AS `worksFor`,
		`roles`.`name` as `role`,
		`users`.`created`,
		`users`.`updated` FROM `Person`
	JOIN `users` ON `users`.`person` = `Person`.`id`
	JOIN `roles` on `users`.`role` = `roles`.`id`
	JOIN `PostalAddress` on `Person`.`address` = `PostalAddress`.`id`
	JOIN `Organization` ON `Organization`.`id` = `Person`.`worksFor`
	WHERE `Person`.`familyName` = :familyName
	LIMIT 1;');

	$stm->execute([':familyName' => 'Zuber']);
	$person = $stm->fetchObject();
	// $person->{'@type'} = 'Person';
	// $person->{'@context'} = 'https://schema.org';

	// $stm2 = $pdo->prepare('SELECT `streetAddress`,
	// 	`postOfficeBoxNumber`,
	// 	`addressLocality`,
	// 	`addressRegion`,
	// 	`postalCode`,
	// 	`addressCountry`
	// FROM `PostalAddress`
	// WHERE `id` = :id
	// LIMIT 1;');

	// $stm2->execute([':id' => $person->address]);
	// $person->address = $stm2->fetchObject();
	// $person->address->{'@type'} = 'PostalAddress';

	// $stm3 = $pdo->prepare('SELECT `name`,
	// 	`address`,
	// 	`telephone`,
	// 	`email`,
	// 	`url`,
	// 	`faxNUmber`
	// FROM `Organization`
	// WHERE `id` = :id
	// LIMIT 1;');

	// $stm3->execute([':id' => $person->worksFor]);
	// $person->worksFor = $stm3->fetchObject();
	// $stm2->execute([':id' => $person->worksFor->address]);
	// $person->worksFor->address = $stm2->fetchObject();
	// $person->worksFor->{'@type'} = 'Organization';

	Headers::contentType('application/json');
	echo json_encode($person);
} catch (Throwable $e) {
	header('Content-Type: application/json');
	echo json_encode([
		'message' => $e->getMessage(),
		'file'    => $e->getFile(),
		'line'    => $e->getLine(),
		'trace'   => $e->getTrace(),
	]);
}
