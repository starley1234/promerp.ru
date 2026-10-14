<?php
/**
 * Роутер для встроенного сервера PHP (`php -S`).
 *
 * Эмулирует поведение nginx: статику отдаём как есть,
 * всё остальное передаём в public_html/index.php (Kohana работает с «чистыми» URL).
 *
 * Использование: php -S 0.0.0.0:8080 -t public_html bin/router.php
 */

$docroot = realpath(__DIR__.'/../public_html');
$uri     = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri     = rawurldecode($uri);
$file    = $docroot.$uri;

// Статика: отдаём файл, если он реально существует
if ($uri !== '/' AND is_file($file))
{
    return FALSE;
}

$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $docroot.'/index.php';

require $docroot.'/index.php';
