<?php defined('SYSPATH') or die('No direct script access.');





	// Статические страницы
	Route::set('static_pages', '<action>', array('action' => 'terms|privacy|about'))
	->defaults(array(
		'controller' => 'Pages',
	));

	// Эталон интерфейса: правила и живые примеры компонентов (читает агент
	// перед вёрсткой экрана). Адрес держим отдельным роутом, а не общим
	// `<controller>/<action>`: он не должен зависеть от порядка общих роутов.
	Route::set('design_sample', 'pages/required_design_sample')
	->defaults(array(
		'controller' => 'Pages',
		'action'     => 'required_design_sample',
	));

	// Источник вариантов для живого примера `smart_reference` на странице
	// эталона: статический список, только чтение.
	Route::set('design_sample_reference', 'pages/design_sample_reference')
	->defaults(array(
		'controller' => 'Pages',
		'action'     => 'design_sample_reference',
	));


	/**
	 * Set the routes. Each route must have a minimum of a name, a URI and a set of
	 * defaults for the URI.
	 */
	Route::set('default', '(<controller>(/<action>(/<id>)))')
		->defaults([
			'controller' => 'welcome',
			'action'     => 'index',
		]);
		
	
	// Универсальный маршрут
    Route::set('universal_param', '<controller>(/<action>(/<id>(/<param>)))')
		->defaults(array(
			'controller' => 'Welcome',
			'action'     => 'index',
		));