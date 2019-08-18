<?php
namespace autoloader;

use const \Consts\{
	AUTOLOADER,
	AUTOLOAD_EXTS,
	TIMEZONE,
	INCLUDE_PATH,
	ERROR_HANDLER,
	EXCEPTION_HANDLER,
	HMAC_FILE,
	CREDS_FILE,
	CSP_ALLOWED_HEADERS,
	HOST
};

use \shgysk8zer0\PHPAPI\{API, User, PDO, UploadFile, RandomString};
// use \shgysk8zer0\PHPAPI\Schema\{Thing};

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'shims.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'consts.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'functions.php');

try {
	set_include_path(join(array_map('realpath', INCLUDE_PATH), PATH_SEPARATOR) . PATH_SEPARATOR . get_include_path());
	spl_autoload_register(AUTOLOADER);
	spl_autoload_extensions(join(AUTOLOAD_EXTS, ','));

	set_error_handler(ERROR_HANDLER);
	set_exception_handler(EXCEPTION_HANDLER);
	date_default_timezone_set(TIMEZONE);

	if (! file_exists(HMAC_FILE)) {
		(new RandomString(30, true, true, true, true))->saveAs(HMAC_FILE);
	}

	User::setKey(file_get_contents(HMAC_FILE));
	UploadFile::setHost(HOST);

	if (FILE_EXISTS(CREDS_FILE)) {
		PDO::setCredsFile(CREDS_FILE);
	}

	// Thing::setPDO(PDO::load());
	API::allowHeaders(...CSP_ALLOWED_HEADERS);

} catch (\Throwable $e) {
	print_r($e);
}
