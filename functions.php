<?php

namespace Functions;
use const \Consts\{DEBUG, ERROR_LOG, UPLOADS_DIR, DATA_DIR, EMAIL_CREDS, BASE, COMPOSER_AUTOLOAD};
use \shgysk8zer0\PHPAPI\{PDO, User, JSONFILE, Headers, HTTPException, Request, URL};
use \shgysk8zer0\PHPAPI\Schema\{Person};
use \shgysk8zer0\PHPAPI\Interfaces\{InputData};
use \PHPMailer\PHPMailer\{PHPMailer, SMTP, Exception};
use \StdClass;
use \DateTime;
use \Throwable;
use \ErrorException;

// @SEE https://github.com/PHPMailer/PHPMailer/blob/master/README.md
function mail(Person $from, Person $to, string $subject, string $body, bool $html = true): bool
{
	if (@file_exists(EMAIL_CREDS) and composer_autoloader()) {
		try {
			$creds            = json_decode(file_get_contents(EMAIL_CREDS));
			$mail             = new PHPMailer(true);
			// $mail->SMTPDebug  = SMTP::DEBUG_SERVER;                      // Enable verbose debug output
			$mail->isSMTP();                                            // Send using SMTP
			$mail->Host       = $creds->host;                    // Set the SMTP server to send through
			$mail->SMTPAuth   = true;                                   // Enable SMTP authentication
			$mail->Username   = $creds->username;                     // SMTP username
			$mail->Password   = $creds->password;                               // SMTP password
			$mail->SMTPSecure = $creds->startTls ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` also accepted
			$mail->Port       = $creds->port;                                    // TCP port to connect to

			//Recipients
			$mail->setFrom($from->email, "{$from->givenName} {$from->familyName}");
			$mail->addAddress($to->email, "{$to->givenName} {$to->familyName}");     // Add a recipient
			//$mail->AltBody

			// Content
			$mail->isHTML($html);                                  // Set email format to HTML
			$mail->Subject = $subject;
			$mail->Body    = $body;
			// $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';
			$mail->send();
			return true;
		} catch(Throwable $e) {
			return false;
		}
	} else {
		return false;
	}
}

function composer_autoloader(): bool
{
	if (@file_exists(COMPOSER_AUTOLOAD)) {
		require_once COMPOSER_AUTOLOAD;
		return true;
	} else {
		return false;
	}
}

function get_user_person_by_uuid(PDO $pdo, $uuid):? object
{
	static $stm =null;

	if (is_null($stm)) {
		$stm = $pdo->prepare('SELECT "https://schema.org" AS `@context`,
			"Person" AS `@type`,
			`users`.`uuid` AS `uuid`,
			`Person`.`identifier`,
			`Person`.`honorificPrefix`,
			CONCAT(`Person`.`givenName`, " ", `Person`.`familyName`) AS `name`,
			`Person`.`givenName`,
			`Person`.`additionalName`,
			`Person`.`familyName`,
			`Person`.`gender`,
			JSON_OBJECT(
				"@type", "PostalAddress",
				"streetAddress", `PostalAddress`.`streetAddress`,
				"postOfficeBoxNumber", `PostalAddress`.`postOfficeBoxNumber`,
				"addressLocality", `PostalAddress`.`addressLocality`,
				"addressRegion", `PostalAddress`.`addressRegion`,
				"postalCode", `PostalAddress`.`postalCode`,
				"addressCountry", `PostalAddress`.`addressCountry`
			) AS `address`,
			`Person`.`telephone`,
			`Person`.`email`,
			`Person`.`jobTitle`,
			JSON_OBJECT(
				"@type", "Organization",
				"identifier", `Organization`.`identifier`,
				"name", `Organization`.`name`,
				"telephone", `Organization`.`telephone`,
				"email", `Organization`.`email`,
				"url", `Organization`.`url`
			) AS `worksFor`,
			JSON_OBJECT(
				"@type", "ImageObject",
				"identifier", `ImageObject`.`identifier`,
				"url", `ImageObject`.`url`,
				"width", `ImageObject`.`width`,
				"height", `ImageObject`.`height`,
				"encodingFormat", `ImageObject`.`encodingFormat`
			) AS `image`
		FROM `Person`
		JOIN `users` ON `users`.`person` = `Person`.`id`
		LEFT OUTER JOIN `PostalAddress` on `Person`.`address` = `PostalAddress`.`id`
		LEFT OUTER JOIN `Organization` ON `Organization`.`id` = `Person`.`worksFor`
		LEFT OUTER JOIN `ImageObject` ON `ImageObject`.`id` = `Person`.`image`
		WHERE `users`.`uuid` = :uuid
		LIMIT 1;');
	}

	if ($stm->execute([":uuid" => $uuid])) {
		$result = $stm->fetchObject();
		$result->address = json_decode($result->address);
		$result->image = json_decode($result->image);
		$result->worksFor = json_decode($result->worksFor);
		return $result;
	} else {
		return null;
	}
}

function get_person(PDO $pdo, $val, string $key = 'id'):? object
{
	static $stms = [];

	if (! array_key_exists($key, $stms)) {
		$stms[$key] = $pdo->prepare('SELECT "https://schema.org" AS `@context`,
			"Person" AS `@type`,
			`Person`.`identifier`,
			`Person`.`honorificPrefix`,
			CONCAT(`Person`.`givenName`, " ", `Person`.`familyName`) AS `name`,
			`Person`.`givenName`,
			`Person`.`additionalName`,
			`Person`.`familyName`,
			`Person`.`gender`,
			JSON_OBJECT(
				"@type", "PostalAddress",
				"streetAddress", `PostalAddress`.`streetAddress`,
				"postOfficeBoxNumber", `PostalAddress`.`postOfficeBoxNumber`,
				"addressLocality", `PostalAddress`.`addressLocality`,
				"addressRegion", `PostalAddress`.`addressRegion`,
				"postalCode", `PostalAddress`.`postalCode`,
				"addressCountry", `PostalAddress`.`addressCountry`
			) AS `address`,
			`Person`.`telephone`,
			`Person`.`email`,
			`Person`.`jobTitle`,
			JSON_OBJECT(
				"@type", "Organization",
				"identifier", `Organization`.`identifier`,
				"name", `Organization`.`name`,
				"telephone", `Organization`.`telephone`,
				"email", `Organization`.`email`,
				"url", `Organization`.`url`
			) AS `worksFor`,
			JSON_OBJECT(
				"@type", "ImageObject",
				"identifier", `ImageObject`.`identifier`,
				"url", `ImageObject`.`url`,
				"width", `ImageObject`.`width`,
				"height", `ImageObject`.`height`,
				"encodingFormat", `ImageObject`.`encodingFormat`
			) AS `image`
		FROM `Person`
		LEFT OUTER JOIN `PostalAddress` on `Person`.`address` = `PostalAddress`.`id`
		LEFT OUTER JOIN `Organization` ON `Organization`.`id` = `Person`.`worksFor`
		LEFT OUTER JOIN `ImageObject` ON `ImageObject`.`id` = `Person`.`image`
		WHERE `Person`.`' . $key . '` = :val
		LIMIT 1;');
	}

	if ($stm = $stms[$key] and $stm->execute([":val" => $val])) {
		$result = $stm->fetchObject();
		$result->address = json_decode($result->address);
		$result->image = json_decode($result->image);
		$result->worksFor = json_decode($result->worksFor);
		return $result;
	} else {
		return null;
	}
}

function get_organization(PDO $pdo, int $id): ?object
{
	static $stm = null;

	if (is_null($stm)) {
		$stm = $pdo->prepare('SELECT "https://schema.org" AS `@context`,
			"Organization" AS `@type`,
			`Organization`.`identifier`,
			`Organization`.`name`,
			`Organization`.`telephone`,
			`Organization`.`email`,
			`Organization`.`url`,
			JSON_OBJECT(
				"@type", "PostalAddress",
				"streetAddress", `PostalAddress`.`streetAddress`,
				"postOfficeBoxNumber", `PostalAddress`.`postOfficeBoxNumber`,
				"addressLocality", `PostalAddress`.`addressLocality`,
				"addressRegion", `PostalAddress`.`addressRegion`,
				"postalCode", `PostalAddress`.`postalCode`,
				"addressCountry", `PostalAddress`.`addressCountry`
			) AS `address`,
			JSON_OBJECT(
				"@type", "ImageObject",
				"identifier", `ImageObject`.`identifier`,
				"url", `ImageObject`.`url`,
				"width", `ImageObject`.`width`,
				"height", `ImageObject`.`height`,
				"encodingFormat", `ImageObject`.`encodingFormat`
			) AS `image`
		FROM `Organization`
		LEFT OUTER JOIN `PostalAddress` on `Organization`.`address` = `PostalAddress`.`id`
		LEFT OUTER JOIN `ImageObject` ON `ImageObject`.`id` = `Organization`.`image`
		WHERE `Organization`.`id` = :id
		LIMIT 1;');
	}

	if ($stm->execute([':id' => $id])) {
		$result = $stm->fetchObject();
		$result->address = json_decode($result->address);
		$result->image = json_decode($result->image);
		return $result;
	}
}

function get_person_id_for_user(PDO $pdo, int $id):? int
{
	$stm = $pdo->prepare('SELECT `person` FROM `users` WHERE `id` = :id LIMIT 1;');
	if ($stm->execute([':id' => $id]) and $user = $stm->fetchObject()) {
		return $user->person ?? null;
	} else {
		return null;
	}
}

function get_user_id_for_person(PDO $pdo, int $id):? int
{
	$stm = $pdo->prepare('SELECT `person` FROM `users` WHERE `id` = :id LIMIT 1;');

	if ($stm->execute([':id' => $id]) and $pid = $stm->fetchObject()) {
		return $p->person;
	} else {
		return null;
	}
}

function get_person_uuid_from_user_uuid(PDO $pdo, string $user):? string
{
	$stm = $pdo->prepare('SELECT `Person`.`identifier`
		FROM `users`
		LEFT OUTER JOIN `Person` ON `users`.`person` = `Person`.`id`
		WHERE `users`.`uuid` = :uuid
		LIMIT 1;');
	if ($stm->execute([':uuid' => $user]) and $p = $stm->fetchObject()) {
		return $p->identifier;
	} else {
		return null;
	}
}

function get_address(PDO $pdo, int $id): ?object
{
	static $stm = null;

	if (is_null($stm)) {
		$stm = $pdo->prepare('SELECT "https://schema.org" AS `@context`,
			"PostalAddress" AS `@type`,
			`streetAddress`,
			`addressLocality`,
			`addressRegion`,
			`postalCode`,
			`addressCountry`
		FROM `PostalAddress`
		WHERE `id` = :id
		LIMIT 1;');
	}

	if ($stm->execute([':id' => $id])) {
		$result = $stm->fetchObject();
		$result->postalCode = intval($result->postalCode);
		return $result;
	}
}

function get_user(InputData $data):? User
{
	if ($data->has('token')) {
		$user = User::loadFromToken(PDO::load(), $data->get('token', false));

		if ($user->loggedIn) {
			return $user;
		} else {
			return null;
		}
	} elseif ($data->has('username', 'password') and filter_var($data->get('username', false))) {
		$user = new User(PDO::load());

		if ($user->login($data->get('username', false), $data->get('password', false))) {
			return $user;
		} else {
			return null;
		}
	} else {
		return null;
	}
}

function is_pwned(string $pwd): bool
{
	$hash   = strtoupper(sha1($pwd));
	$prefix = substr($hash, 0, 5);
	$rest   = substr($hash, 5);
	$req    = new Request("https://api.pwnedpasswords.com/range/{$prefix}");
	$resp   = $req->send();

	if ($resp->ok) {
		return strpos($resp->body, "{$rest}:") !== false;
	} else {
		return false;
	}
}

function upload_path(): string
{
	$date = new DateTime();
	return UPLOADS_DIR . $date->format(sprintf('Y%sm%s', DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR));
}

function https(): bool
{
	return array_key_exists('HTTPS', $_SERVER) and ! empty($_SERVER['HTTPS']);
}

function dnt(): bool
{
	return array_key_exists('HTTP_DNT', $_SERVER) and $_SERVER['HTTP_DNT'] === '1';
}

function is_cli(): bool
{
	return in_array(PHP_SAPI, ['cli']);
}

function error_handler(int $errno, string $errstr, string $errfile, int $errline = 0): bool
{
	return log_exception(new ErrorException($errstr, 0, $errno, $errfile, $errline));
}

function exception_handler(Throwable $e)
{
	if ($e instanceof HTTPException) {
		log_exception($e);
		Headers::status($e->getCode());
		Headers::contentType('application/json');
		exit(json_encode($e));
	} else {
		log_exception($e);
		Headers::status(Headers::INTERNAL_SERVER_ERROR);
		Headers::contentType('application/json');
		$error = [
			'message' =>  DEBUG ? $e->getMessage() : 'Internal Server Error',
			'status'  => Headers::INTERNAL_SERVER_ERROR,
		];
		if (DEBUG) {
			$error['file']    = $e->getFile();
			$error['line']    = $e->getLine();
			$error['code']    = $e->getCode();
			$error['trace']   = $e->getTrace();
		}
		exit(json_encode([
			'error' => $error,
		]));
	}
}

function log_exception(Throwable $e): bool
{
	static $stm = null;

	static $url = null;

	if (is_null($url)) {
		$url = URL::getRequestUrl();
		unset($url->password);
		$url->searchParams->delete('token');
	}

	if (is_null($stm)) {
		$pdo = PDO::load();
		$stm = $pdo->prepare('INSERT INTO `logs` (
			`type`,
			`message`,
			`file`,
			`line`,
			`code`,
			`remoteIP`,
			`url`
		) VALUES (
			:type,
			:message,
			:file,
			:line,
			:code,
			:ip,
			:url
		);');
	}

	$code = $e->getCode();

	return $stm->execute([
		':type'    => get_class($e),
		':message' => substr($e->getMessage(), 0, 255),
		':file'    => str_replace(BASE, null, $e->getFile()),
		':line'    => $e->getLine(),
		':code'    => is_int($code) ? $code : 0,
		':ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
		':url'     => substr($url, 0, 255),
	]);
}
