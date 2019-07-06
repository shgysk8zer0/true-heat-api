<?php
namespace Upload;

use \shgysk8zer0\PHPAPI\{PDO, User, API, Headers, Uploads, HTTPException};
use \shgysk8zer0\PHPAPI\Abstracts\{HTTPStatusCodes as HTTP};
use function \Functions\{upload_path};
use const \Consts\{HOST};

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoloader.php';

try {
	$api = new API('*');
	$api->on('POST', function(API $request): void
	{
		if (! $request->post->has('token')) {
			throw new HTTPException('Missing token in request', HTTP::BAD_REQUEST);
		} else {
			$user = User::loadFromToken(PDO::load(), $request->post->get('token', false));
			if (! $user->loggedIn) {
				throw new HTTPException('User data expired or invalid', HTTP::UNAUTHORIZED);
			} elseif (! (array_key_exists('upload', $user->permissions) and $user->permissions['upload'])) {
				throw new HTTPException('You do not have access', HTTP::FORBIDDEN);
			} elseif (empty($_FILES)) {
				throw new HTTPException('No file uploaded', HTTP::BAD_REQUEST);
			} else {
				$path = upload_path();

				foreach ($request->files as $file) {
					if (! $file->saveAs("{$path}{$file->md5}.{$file->ext}")) {
						throw new HTTPException("Error uploading {$file->name}");
					}
				}
				Headers::status(HTTP::CREATED);
				Headers::contentType('application/json');
				echo json_encode($request->files);
			}
		}
	});

	$api->on('DELETE', function(API $request): void
	{
		if (! $request->get->has('token')) {
			throw new HTTPException('Missing token in request', HTTP::BAD_REQUEST);
		} else {
			$user = User::loadFromToken(PDO::load(), $request->get->get('token', false));
			if (! $user->loggedIn) {
				throw new HTTPException('User data expired or invalid', HTTP::UNAUTHORIZED);
			} elseif (! $user->isAdmin()) {
				throw new HTTPException('You do not have permissions for this action', HTTP::FORBIDDEN);
			} else {
				// Delete the file
			}
		}
	});
	$api();
} catch (HTTPException $e) {
	Headers::status($e->getCode());
	Headers::contentType('application/json');
	echo json_encode($e);
}
