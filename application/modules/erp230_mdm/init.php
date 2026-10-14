<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Init file for MDM (Master Data Management) Module
 * Module ID: 230
 * Prefix: erp230_
 * 
 * ВАЖНО: Контроллеры Baselines, Structure, Type, Codes используют расширенный роутинг,
 * где <id> является единственным сегментом после <action>.
 */

// 1. API роуты для специализированных контроллеров (Baselines, Structure, Type, Codes)
// Формат: erp230_api/<controller>/<action>(/<id>)
Route::set('erp230_api_extended', 'erp230_api/<controller>(/<action>(/<id>))', array(
		'controller' => 'baselines|structure|type|codes',
	))
	->defaults(array(
		'directory'  => 'Erp230/Api',
		'action'     => 'list', // у Baselines/Type/Codes есть action_list (у Structure — bom/bom_tree)
	));

// Зеркало для префикса mdm/
Route::set('mdm_api_extended', 'mdm/<controller>(/<action>(/<id>))', array(
		'controller' => 'baselines|structure|type|codes',
	))
	->defaults(array(
		'directory'  => 'Erp230/Api',
		'action'     => 'list', // у Baselines/Type/Codes есть action_list (у Structure — bom/bom_tree)
	));

// 2. Роуты для вложенных ресурсов объекта (Object Sub-resources)
// Формат: erp230_api/object/<id>/<action>
Route::set('erp230_api_object_sub', 'erp230_api/object/<id>/<action>', array(
		'id' => '[0-9a-f\-]+'
	))
	->defaults(array(
		'directory'  => 'Erp230',
		'controller' => 'Api',
	));

Route::set('mdm_api_object_sub', 'mdm/object/<id>/<action>', array(
		'id' => '[0-9a-f\-]+'
	))
	->defaults(array(
		'directory'  => 'Erp230',
		'controller' => 'Api',
	));

// 3. Роуты для состояния близнеца (Twin State)
Route::set('erp230_api_twin_state', 'erp230_api/twin/<id>/state', array(
		'id' => '[0-9a-f\-]+'
	))
	->defaults(array(
		'directory'  => 'Erp230',
		'controller' => 'Api',
		'action'     => 'state_at', // срез состояния на момент времени (см. Model_Erp230_Object::state_at)
	));

Route::set('mdm_api_twin_state', 'mdm/twin/<id>/state', array(
		'id' => '[0-9a-f\-]+'
	))
	->defaults(array(
		'directory'  => 'Erp230',
		'controller' => 'Api',
		'action'     => 'state_at', // срез состояния на момент времени (см. Model_Erp230_Object::state_at)
	));

// 4. Общий API-роут (Fallback для Controller_Erp230_Api)
Route::set('erp230_mdm_api', 'erp230_api(/<action>(/<id>))')
	->defaults(array(
		'directory'  => 'Erp230',
		'controller' => 'Api',
		'action'     => 'objects', // action_index у контроллера нет
	));

Route::set('mdm_api', 'mdm/api(/<action>(/<id>))')
	->defaults(array(
		'directory'  => 'Erp230',
		'controller' => 'Api',
		'action'     => 'objects', // action_index у контроллера нет
	));

// 5. Базовые контроллеры UI и тестов
Route::set('erp230_mdm_base', 'erp230_<controller>(/<action>(/<id>))', array(
		'controller' => '(index|stewardship|dictionary|widget|main|objects|apitest)',
	))
	->defaults(array(
		'directory'  => 'Erp230',
		'controller' => 'Index',
		'action'     => 'index',
	));

/**
 * Настройка сессионных переменных PostgreSQL для RLS
 */
Event::listen('auth.login_success', function($user) {
	try {
		$db = Database::instance('mdm');
		$user_id = (string) $user->id;
		$role    = (string) $user->role;

		$db->query(Database::SELECT, "SELECT set_config('app.current_user_id', ".$db->quote($user_id).", false)");
		$db->query(Database::SELECT, "SELECT set_config('app.role', ".$db->quote($role).", false)");
	} catch (Exception $e) {
		Kohana::$log->add(Log::ERROR, 'Failed to set PostgreSQL RLS context: :error', array(
			':error' => $e->getMessage(),
		));
	}
});