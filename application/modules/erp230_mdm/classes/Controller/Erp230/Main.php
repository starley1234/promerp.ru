<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Базовый контроллер модуля MDM (ERP230).
 *
 * Наследует общую инфраструктуру ERP (ACL, _current_user, Storage, Notify,
 * Llm, события) от Controller_Base и дополнительно активирует RLS-контекст
 * на соединении 'mdm'.
 */
class Controller_Erp230_Main extends Controller_Base {

	/** Актор для снимков/аудита и GUC RLS. Заполняется в before(). */
	protected $_app_user = 'system';
	protected $_app_role = 'guest';

	public function before()
	{
		// 1. Общий стек ERP: auth-guard + _current_user + меню и пр.
		parent::before();

		// 2. Теперь пользователь известен — берём реального актора.
		if ($this->_current_user)
		{
			$this->_app_user = (string) $this->_current_user->id;
			$this->_app_role = $this->_resolve_role();
		}
        
        $this->set_menu($this->_module_menu());

		// 3. Активируем RLS-контекст ИМЕННО на соединении 'mdm'.
		//    Контекст живёт на КАЖДЫЙ запрос, а не только после логина.
		try
		{
			$db = Database::instance('mdm');

			DB::query(Database::SELECT, "SELECT set_config('app.current_user_id', :uid, false)")
				->param(':uid', $this->_app_user)
				->execute($db);

			DB::query(Database::SELECT, "SELECT set_config('app.role', :role, false)")
				->param(':role', $this->_app_role)
				->execute($db);
		}
		catch (Exception $e)
		{
			// Если БД недоступна — логируем, но не роняем весь запрос здесь;
			// доступ к данным всё равно ограничит RLS (контекст пуст → нет прав).
			Kohana::$log->add(Log::ERROR, 'Failed to set PostgreSQL RLS context in Main::before(): :error', array(
				':error' => $e->getMessage(),
			));
		}
	}

	// =======================================================================
	//  НАВИГАЦИЯ
	// =======================================================================

	/**
	 * Меню модуля с ACL-разметкой пунктов.
	 *
	 * @return array
	 */
	protected function _module_menu()
	{
		return [
			[
				'title' => 'Дашборд',
				'href'  => URL::site('erp230_index'),
				'icon'  => 'fas fa-gauge-high',
				'acl'   => 'mdm.object.view',
			],
			[
				'title' => 'Реестр записей',
				'href'  => URL::site('erp230_objects'),
				'icon'  => 'fas fa-database',
				'acl'   => 'mdm.object.view',
			],
            [
				'title' => 'Добавление',
				'href'  => '#',
				'icon'  => 'fas fa-plus',
				'acl'   => 'mdm.object.create',
				'children' => [
					[
						'title' => 'Добавить объект',
						'href'  => URL::site('erp230_objects/create'),
						'acl'   => 'mdm.object.view',
					],
					[
						'title' => 'Списком',
						'href'  => URL::site('erp230_objects/batch'),
						'acl'   => 'mdm.object.view',
					],
             		],
				],
            
			[
				'title' => 'Типы объектов',
				'href'  => URL::site('erp230_types'),
				'icon'  => 'fas fa-tags',
				'acl'   => 'mdm.type.view',
			],
			[
				'title' => 'Источники данных',
				'href'  => URL::site('erp230_sources'),
				'icon'  => 'fas fa-file-import',
				'acl'   => 'mdm.source.view',
			],
			[
				'title' => 'Справочники',
				'href'  => URL::site('erp230_dictionaries'),
				'icon'  => 'fas fa-sitemap',
				'acl'   => 'mdm.dictionary.view',
			],
			[
				'title' => 'Документация',
				'href'  => '#',
				'icon'  => 'fas fa-book',
				'acl'   => 'mdm.object.view',
				'children' => [
					[
						'title' => 'О сервисе',
						'href'  => URL::site('erp230_index/docs/about'),
						'acl'   => 'mdm.object.view',
					],
					[
						'title' => 'Правила типов',
						'href'  => URL::site('erp230_index/docs/types'),
						'acl'   => 'mdm.object.view',
					],
					[
						'title' => 'Для разработчиков',
						'href'  => URL::site('erp230_index/docs/dev'),
						'acl'   => 'mdm.object.view',
					],
					[
						'title' => 'Описание API',
						'href'  => URL::site('erp230_index/docs/api'),
						'acl'   => 'mdm.admin',
					],
                    [
						'title' => 'Тест API',
						'href'  => URL::site('erp230_index/docs/api_test'),
						'acl'   => 'mdm.admin',
					],
					[
						'title' => 'Рекомендации',
						'href'  => URL::site('erp230_index/docs/best_prac'),
						'acl'   => 'mdm.object.view',
					],
				],
			],
		];
	}
	

	/** Первая роль пользователя как строка (адаптируй под свою модель ролей). */
	protected function _resolve_role()
	{
		if (method_exists($this, 'has_any_role') AND $this->has_any_role(['admin']))
			return 'admin';

		return 'user';
	}

	/**
	 * JSON-ответ. Обязан быть public — в Controller_Base он public,
	 * сузить видимость нельзя. Добавляем pretty-print для читаемости API.
	 */
	public function _json_response($data, $status = 200)
	{
		$this->auto_render = FALSE;

		$this->response
			->status($status)
			->headers('Content-Type', 'application/json; charset=utf-8')
			->body(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
	}
}