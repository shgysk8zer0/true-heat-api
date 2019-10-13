<?php
namespace Index;

use \shgysk8zer0\PHPAPI\{PDO, Headers};
use \Throwable;
use const \Consts\{CREDS_FILE};
use function \Functions\{get_organization, get_person};

try {
	require_once __DIR__ . DIRECTORY_SEPARATOR . 'autoloader.php';

	Headers::contentType('application/json');
	echo json_encode(get_person(PDO::load(CREDS_FILE), 'c7ffc7fc-4250-46e8-a1eb-6ccf1fadcf79', 'identifier'), JSON_PRETTY_PRINT);
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

	// Headers::contentType('application/json');
	// echo json_encode($person);
} catch (Throwable $e) {
	header('Content-Type: application/json');
	echo json_encode([
		'message' => $e->getMessage(),
		'file'    => $e->getFile(),
		'line'    => $e->getLine(),
		'trace'   => $e->getTrace(),
	]);
}
