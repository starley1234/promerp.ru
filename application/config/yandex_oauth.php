<?php defined('SYSPATH') or die('No direct script access.');
return array(
    'client_id'    => getenv('YANDEX_OAUTH_CLIENT_ID'), // Замените на ваш client_id
    'redirect_uri' => 'https://test.promerp.ru/yandexauth/callback', // Замените на ваш Redirect URI
    'token_page_origin' => 'https://test.promerp.ru', // Origin вашей вспомогательной страницы (без пути)
);