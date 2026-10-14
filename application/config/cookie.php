<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Соль для cookie и подписи сессий.
 * Значение генерируется в .env: bash bin/setup.sh (переменная COOKIE_SALT).
 */
return array(
    'salt' => getenv('COOKIE_SALT'),
);
