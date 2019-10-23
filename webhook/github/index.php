<?php
namespace WebHook\GitHub;

use const \Consts\{GITHUB_WEBHOOK};
use \shgysk8zer0\PHPAPI\{HTTPException, Headers, API};
use \shgysk8zer0\PHPAPI\WebHook\{GitHub};

require_once(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'autoloader.php');
try {
	$api = new API('*');
	$api->on('POST', function(API $api): void
	{
		$hook = new GitHub(GITHUB_WEBHOOK);
		switch ($hook->event) {
			case 'ping':
				Headers::contentType('application/json');
				echo json_encode($hook);
				break;
			case 'push':
				Headers::contentType('text/plain');
				if (! $hook->isMaster()) {
					echo sprintf('Not updating non-master branch, "%s"', $hook->getBranch());
				} elseif (! $hook->isClean()) {
					echo 'Not updating non-clean working directory' . PHP_EOL;
					echo $hook->status();
				} else {
					echo $hook->pull() . PHP_EOL;
					echo $hook->updateSubmodules() . PHP_EOL;
					echo $hook->status();
					`composer install`;
				}
				break;
			default:
				throw new HTTPException("Unsupported event: {$hook->event}", HTTP::NOT_IMPLEMENTED);
		}
	});
	$api();
} catch (HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	echo json_encode($e);
}
