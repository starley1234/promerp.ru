<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Доверенные хосты для URL::base()/URL::site().
 *
 * Хост, не попавший ни в один шаблон, приводит к исключению
 * «Untrusted host» (защита от Host-header injection).
 *
 * Свои хосты добавляются переменной окружения TRUSTED_HOSTS (через запятую),
 * шаблоны — регулярные выражения без разделителей.
 */

$extra = getenv('TRUSTED_HOSTS');

$hosts = [
    'localhost',
    '127\.0\.0\.1(:\d+)?',
    '.*\.local(:\d+)?',
    '.*\.e2b\.app',       // песочницы/превью разработки
    'promerp\.ru',
    'test\.promerp\.ru',
    'www\.test\.promerp\.ru',
    'pg\.promerp\.ru',
];

if ($extra !== FALSE AND trim($extra) !== '')
{
    foreach (explode(',', $extra) as $host)
    {
        $host = trim($host);

        if ($host !== '')
        {
            // Точки в имени хоста экранируем, чтобы не превращать их в «любой символ»
            $hosts[] = str_replace('.', '\.', $host);
        }
    }
}

return [
    'trusted_hosts' => $hosts,
];
