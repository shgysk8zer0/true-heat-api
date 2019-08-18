<?php
namespace Consts;

define(__NAMESPACE__ . '\DEBUG', $_SERVER['HTTP_HOST'] === 'localhost');
const BASE              = __DIR__ . DIRECTORY_SEPARATOR;
const CLASSES_DIR       = '../../';//BASE . 'classes' . DIRECTORY_SEPARATOR;
const DATA_DIR          = BASE . 'data' . DIRECTORY_SEPARATOR;
const LOGS_DIR          = BASE . 'logs' . DIRECTORY_SEPARATOR;
const UPLOADS_DIR       = BASE . 'uploads' . DIRECTORY_SEPARATOR;
const CREDS_FILE        = DATA_DIR . 'creds.json';
const HMAC_FILE         = DATA_DIR . 'hmac.key';
const GITHUB_WEBHOOK    = DATA_DIR . 'github.json';
const SQL_FILE          = DATA_DIR . 'db.sql';
const ERROR_LOG         = LOGS_DIR . 'errors.log';
const OPEN_WEATHER_MAP  = DATA_DIR . 'OpenWeatherMap.key';
const TIMEZONE          = 'America/Los_Angeles';
const EXCEPTION_HANDLER = '\Functions\exception_handler';
const ERROR_HANDLER     = '\Functions\error_handler';
const AUTOLOADER        = 'spl_autoload';
const AUTOLOAD_EXTS     = [
	'.php',
];
const INCLUDE_PATH      = [
	CLASSES_DIR,
	DATA_DIR,
];

const CSP_ALLOWED_HEADERS = [
	'Accept',
	'Content-Type',
	'Upgrade-Insecure-Requests',
];

define(__NAMESPACE__ . '\HOST', sprintf('%s://%s',
	(array_key_exists('HTTPS', $_SERVER) and ! empty($_SERVER['HTTPS'])) ? 'https' : 'http',
	$_SERVER['HTTP_HOST'] ?? 'localhost'
));

define(__NAMESPACE__ . '\BASE_PATH',
	rtrim(
		(DIRECTORY_SEPARATOR === '/')
			? '/' . trim(str_replace($_SERVER['DOCUMENT_ROOT'], null, __DIR__), '/')
			: '/' . trim(str_replace(
				DIRECTORY_SEPARATOR,
				'/',
				str_replace($_SERVER['DOCUMENT_ROOT'], null, __DIR__)
			), '/')
	,'/') . '/'
);

const BASE_URI = HOST . BASE_PATH;
