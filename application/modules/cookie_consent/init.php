<?php defined('SYSPATH') or die('No direct script access.');

// Регистрируем маршрут для обработки закрытия окна
Route::set('cookie_consent_close', 'cookie_consent/close')
    ->defaults(array(
        'controller' => 'CookieConsent',
        'action'     => 'close',
    ));

// Добавляем хук для вставки HTML в шаблон
// Это можно сделать разными способами, например, через View::factory() в контроллере
// или через Event::add() если у вас есть система событий.
// Для простоты, мы покажем, как это можно сделать в контроллере,
// а здесь просто убедимся, что модуль включен.