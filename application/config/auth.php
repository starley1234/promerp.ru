<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Настройки аутентификации (модуль auth + драйвер ORM).
 * Ключ хеширования паролей хранится в .env (AUTH_HASH_KEY) — в git не попадает.
 */
return array(

	'driver'       => 'ORM',
	'hash_method'  => 'sha256',
	'hash_key'     => getenv('AUTH_HASH_KEY'),
	'lifetime'     => 1209600,
	'session_type' => Session::$default,
	'session_key'  => 'auth_user',

	// Логин/пароль для драйвера File (не используется в production)
	'users' => array(
		// 'admin' => '11111111111111',
	),

);
