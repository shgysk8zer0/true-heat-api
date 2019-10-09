<?php
namespace Lint;

use \shgysk8zer0\PHPAPI\{Linter, Headers};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPSTatusCodes as HTTP};
use function \Functions\{is_cli};

require_once('./autoloader.php');

if (is_cli()) {
	$linter = new Linter();
	$linter->ignoreDirs('./.git', './data', './logs', './classes/shgysk8zer0/phpapi', './vendor');
	$linter->scanExts('php');

	if (! $linter->scan(__DIR__)) {
		exit(1);
	}
} else {
	Headers::status(HTTP::NOT_FOUND);
}
