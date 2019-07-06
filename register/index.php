<?php
namespace Registration;
use \shgysk8zer0\PHPAPI\{Headers, URL, API, HTTPException};
require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoloader.php');

try {
	$api = new API('*');
	$api->on('POST', function(API $request): void
	{
		$request->url->pathname = '/user/';
		$request->redirect($request->url, true);
	});
	$api();
} catch(HTTPEXception $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	echo json_encode($e);
}