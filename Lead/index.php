<?php
namespace Lead;
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoloader.php';
use \shgysk8zer0\PHPAPI\{PDO, API, HTTPException, Headers};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};

try {
	$api = new API('*');

	$api->on('GET', function(API $req): void
	{
		$pdo = PDO::load();
		$stm = $pdo->prepare('SELECT DISTINCT(`lead`) FROM `Claim`;');
		$stm->execute();
		Headers::contentType('application/json');
		echo json_encode($stm->fetchAll());
	});

	$api();
} catch(HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	echo json_encode($e);
}
