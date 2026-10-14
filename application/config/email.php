<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Отправка почты. Учётные данные — из .env (MAIL_USERNAME, MAIL_PASSWORD);
 * хост/порт можно переопределить переменными MAIL_HOST, MAIL_PORT, MAIL_ENCRYPTION.
 */
$env = static function ($key, $default = NULL) {
    $value = getenv($key);
    return ($value === FALSE OR $value === '') ? $default : $value;
};

return array(
	'driver'  => 'smtp',
	'options' => array(
		'hostname'   => $env('MAIL_HOST', 'smtp.timeweb.ru'),
		'username'   => $env('MAIL_USERNAME'),
		'password'   => $env('MAIL_PASSWORD'),
		'port'       => (int) $env('MAIL_PORT', 465),
		'encryption' => $env('MAIL_ENCRYPTION', 'ssl'),
	),
);
