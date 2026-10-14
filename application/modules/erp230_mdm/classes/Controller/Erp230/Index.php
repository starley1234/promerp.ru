<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Точка входа модуля MDM (ERP230) — дашборд.
 *
 * Реестр и CRUD объектов вынесены в Controller_Erp230_Objects,
 * REST-слой — в Controller_Erp230_Api. Здесь только сводная панель:
 * ключевые метрики, «грязные» записи, последние изменения, быстрые ссылки.
 *
 * Маршруты:
 *   GET /erp230_index                 — дашборд
 *   GET /erp230_index/stats           — сводные метрики (JSON, для авто-обновления виджетов)
 *   GET /erp230_index/docs/<page>     — документация и справка
 *
 * @package Erp230
 * @version 2.3.0
 */
class Controller_Erp230_Index extends Controller_Erp230_Main {

	/** Ключ кэша сводных метрик дашборда. */
	const CACHE_STATS_KEY = 'erp230_dashboard_stats';

	// =======================================================================
	//  ДАШБОРД
	// =======================================================================

	/**
	 * Сводная панель модуля.
	 */
	public function action_index()
	{
		if ( ! $this->can('mdm.object.view'))
			throw new HTTP_Exception_403('У вас нет прав на доступ к модулю MDM');

		$this->set_title('MDM — Единый реестр мастер-данных');
		$this->set_description('Панель управления Golden Records и цифровыми двойниками');

		$this->template->content = View::factory('erp230/dashboard')
			->set('stats',    $this->_collect_stats())
			->set('dirty',    $this->_recent_dirty(10))
			->set('recent',   $this->_recent_updated(10))
			->set('can_create', $this->can('mdm.object.create'));
	}

	/**
	 * GET /erp230_index/stats — те же метрики в JSON (для AJAX-обновления виджетов).
	 */
	public function action_stats()
	{
		if ( ! $this->can('mdm.object.view'))
			return $this->_json_response(['status' => 'error', 'message' => 'Forbidden'], 403);

		return $this->_json_response([
			'status' => 'ok',
			'stats'  => $this->_collect_stats(),
		]);
	}

	/**
	 * Вывод справочных страниц и документации.
	 * GET /erp230_index/docs/<page>
	 */
	public function action_docs()
	{
		$page = $this->request->param('id', 'about');
		
		$titles = [
			'about'      => 'О сервисе MDM',
			'types'      => 'Правила заведения типов компонентов',
			'dev'        => 'Руководство программиста',
			'api'        => 'Описание API',
			'best_prac'  => 'Рекомендации по использованию',
            'api_test'   => 'Тестирование API',
            
            
		];

		if ( ! isset($titles[$page]))
			throw new HTTP_Exception_404('Страница документации не найдена');

		$this->set_title($titles[$page]);
		
		$this->template->content = View::factory('erp230/docs/' . $page)
			->set('page', $page)
			->set('titles', $titles);
	}

	// =======================================================================
	//  ИНВАЛИДАЦИЯ КЭША
	// =======================================================================

	/**
	 * Сбросить кэш сводных метрик дашборда.
	 *
	 * Вызывается при любой мутации объектов реестра (create/update/delete/merge/
	 * смена состояния). Подписан на события erp230.object.* — см. init.php.
	 *
	 * @return void
	 */
	public static function invalidate_stats_cache()
	{
		try
		{
			Cache::instance()->delete(self::CACHE_STATS_KEY);
		}
		catch (Exception $e)
		{
			// Кэш недоступен — не критично для мутации; фиксируем в лог.
			Kohana::$log->add(Log::WARNING,
				'Erp230: не удалось сбросить кэш дашборда: :msg',
				[':msg' => $e->getMessage()]);
		}
	}

	// =======================================================================
	//  ДАННЫЕ ДАШБОРДА
	// =======================================================================

	/**
	 * Сводные метрики реестра. Агрегация делегирована модели
	 * Model_Erp230_Object::dashboard_stats(); кэширование — в контроллере.
	 *
	 * @return array
	 */
	protected function _collect_stats()
	{
		$cache = Cache::instance();
		if ($stats = $cache->get(self::CACHE_STATS_KEY))
			return $stats;

		$raw = Model_Erp230_Object::dashboard_stats();

		$objects = Arr::get($raw, 'objects', []);

		$stats = [
			'total'    => (int) Arr::get($objects, 'total', 0),
			'active'   => (int) Arr::get($objects, 'active', 0),
			'draft'    => (int) Arr::get($objects, 'draft', 0),
			'archived' => (int) Arr::get($objects, 'archived', 0),
			'merged'   => (int) Arr::get($objects, 'merged', 0),
			'dirty'    => (int) Arr::get($objects, 'dirty', 0),
			'types'    => (int) Arr::get($raw, 'types', 0),
			'orgs'     => (int) Arr::get($raw, 'org_units', 0),
		];

		$cache->set(self::CACHE_STATS_KEY, $stats, 5 * Date::MINUTE);
		return $stats;
	}

	/**
	 * Последние «грязные» записи (требуют пересборки Golden Record).
	 *
	 * @param  int $limit
	 * @return array
	 */
	protected function _recent_dirty($limit = 10)
	{
		return $this->_object_digest(
			ORM::factory('Erp230_Object')
				->where('deleted_at', 'IS', NULL)
				->where('is_dirty', '=', TRUE)
				->order_by('updated_at', 'DESC')
				->limit($limit)
				->find_all()
		);
	}

	/**
	 * Последние изменённые активные записи.
	 *
	 * @param  int $limit
	 * @return array
	 */
	protected function _recent_updated($limit = 10)
	{
		return $this->_object_digest(
			ORM::factory('Erp230_Object')
				->where('deleted_at', 'IS', NULL)
				->where('state', '=', 'active')
				->order_by('updated_at', 'DESC')
				->limit($limit)
				->find_all()
		);
	}

	/**
	 * Плоское представление коллекции объектов для виджетов.
	 *
	 * @param  Database_Result|ORM $rows
	 * @return array
	 */
	protected function _object_digest($rows)
	{
		$out = [];
		foreach ($rows as $o)
		{
			$out[] = [
				'id'          => $o->id,
				'master_code' => $o->master_code,
				'type_id'     => $o->type_id,
				'state'       => $o->state,
				'is_dirty'    => (bool) $o->is_dirty,
				'updated_at'  => $o->updated_at,
				'url'         => URL::site('erp230_objects/view/'.$o->id),
			];
		}
		return $out;
	}

}