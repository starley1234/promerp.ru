<?php if (!Route::cache())
{

	// Маршрут для страницы авторизации
	Route::set('yandex_oauth_login', 'oauth/yandex/login')
		->defaults(array(
			'controller' => 'Oauth_Yandex',
			'action'     => 'login',
		));
		

	// Маршрут для вспомогательной страницы (Redirect URI)
	Route::set('yandex_oauth_callback', 'oauth/yandex/callback')
		->defaults(array(
			'controller' => 'Oauth_Yandex',
			'action'     => 'callback',
		));

	// Маршрут для обработки токена на сервере
	Route::set('yandex_oauth_process_token', 'oauth/yandex/process_token')
		->defaults(array(
			'controller' => 'Oauth_Yandex',
			'action'     => 'process_token',
		));
		
}