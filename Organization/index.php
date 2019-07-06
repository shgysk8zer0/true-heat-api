<?php
namespace Organization;
use \shgysk8zer0\PHPAPI\{API, Headers, HTTPException, PDO};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};
use \shgysk8zer0\PHPAPI\Schema\{Organization};

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoloader.php';

try {
	$api = new API('*');

	$api->on('GET', function(API $request)
	{
		$results = [];

		if ($request->get->has('name')) {
			$results = Organization::searchByName(
				$request->get->get('name', false),
				$request->get->get('limit', false, 10),
				$request->get->get('offset', false, 0)
			);
		} else {
			throw new HTTPException('No search paramaters given', HTTP::BAD_REQUEST);
		}

		if (empty($results)) {
			throw new HTTPException('No matches found', HTTP::NOT_FOUND);
		}

		Headers::contentType('application/json');
		echo json_encode($results);
	});

	$api();
} catch (HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	echo json_encode($e);
}
