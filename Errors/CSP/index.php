<?php
namespace Errors\CSP;

use \shgysk8zer0\PHPAPI\{Headers, API, PDO, UUID, User, HTTPException};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'autoloader.php';

try {
	$api = new API('*');

	$api->on('GET', function(API $req): void
	{
		if (! $req->get->has('token')) {
			throw new HTTPException('Missing token in request', HTTP::BAD_REQUEST);
		} elseif (! $user = User::loadFromToken(PDO::load(), $req->get->get('token', false))) {
			throw new HTTPException('Error checking credentials', HTTP::INTERNAL_SERVER_ERROR);
		} elseif (! $user->loggedIn) {
			throw new HTTPException('User data expired or invalid', HTTP::UNAUTHORIZED);
		} elseif (! $user->can('debug')) {
			throw new HTTPException('You do not have permissions for this action', HTTP::FORBIDDEN);
		} else {
			$stm = PDO::load()->query('SELECT `uuid`,
				`document-uri` AS `documentUri`,
				`blocked-uri` AS `blockedUri`,
				`violated-directive` AS `violatedDirective`,
				`script-sample` AS `scriptSample`,
				`referrer`,
				`userAgent`,
				`remoteAddress`,
				DATE_FORMAT(`dateTime`, "%Y-%m-%dT%T") AS `dateTime`
			FROM `CSPErrors`;');
			$stm->execute();
			Headers::contentType('application/json');
			echo json_encode($stm->fetchAll() ?? []);
		}
	});

	$api->on('POST', function(API $req): void
	{
		if ($req->post->has('csp-report')) {
			$report = $req->post->get('csp-report');
			$pdo = PDO::load();
			$stm = $pdo->prepare('INSERT INTO `CSPErrors` (
				`uuid`,
				`document-uri`,
				`blocked-uri`,
				`violated-directive`,
				`script-sample`,
				`referrer`,
				`userAgent`,
				`remoteAddress`
			) VALUES (
				:uuid,
				:documentUri,
				:blockedUri,
				:violatedDirective,
				:scriptSample,
				:referrer,
				:userAgent,
				:remoteAddress
			);');

			if ($stm->execute([
				':uuid'               => new UUID(),
				':documentUri'        => $report->get('document-uri'),
				':blockedUri'         => $report->get('blocked-uri'),
				':violatedDirective'  => $report->get('violated-directive'),
				':scriptSample'       => $report->get('script-sample'),
				':referrer'           => $report->get('referrer') ?? $req->referrer,
				':userAgent'          => $req->userAgent,
				':remoteAddress'      => $req->remoteAddress,
			]) and $stm->rowCount() === 1) {
				Headers::status(HTTP::CREATED);
			} else {
				throw new HTTPException('Error saving CSP report', HTTP::INTERNAL_SERVER_ERROR);
			}
		} else {
			throw new HTTPException('No CSP report submitted', HTTP::BAD_REQUEST);
		}
	});

	$api->on('DELETE', function(API $req): void
	{
		if (! $req->get->has('token')) {
			throw new HTTPException('Missing token in request', HTTP::BAD_REQUEST);
		} elseif (! $user = User::loadFromToken(PDO::load(), $req->get->get('token', false))) {
			throw new HTTPException('Error checking credentials', HTTP::INTERNAL_SERVER_ERROR);
		} elseif (! $user->loggedIn) {
			throw new HTTPException('User data expired or invalid', HTTP::UNAUTHORIZED);
		} elseif (! $user->can('debug')) {
			throw new HTTPException('You do not have permissions for this action', HTTP::FORBIDDEN);
		} elseif (PDO::load()->query('TRUNCATE `CSPErrors`;')->execute()) {
			Headers::status(HTTP::NO_CONTENT);
		} else {
			throw new HTTPException('Error clearing CSP Reports', HTTP::INTERNAL_SERVER_ERROR);
		}
	});

	$api();
} catch (HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	exit(json_encode($e));
}
