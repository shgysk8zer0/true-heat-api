<?php
namespace Test;
use \shgysk8zer0\PHPAPI\{API, Headers, HTTPException};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoloader.php';

try {
	$api = new API();

	$api->on('GET', function(API $request): void
	{
		Headers::contentType('application/json');
		echo json_encode($request);
	});

	$api->on('POST', function(API $request): void
	{
		Headers::contentType('application/json');
		echo json_encode($request);
	});

	$api->on('DELETE', function(API $request): void
	{
		Headers::contentType('application/json');
		echo json_encode($request);
	});

	$api();
} catch (HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	echo json_encode($e);
}
