<?php defined('SYSPATH') or die('No direct script access.');

/**
 * UI-контроллер справочника источников данных (MDM).
 *
 * Источники (таблица sources) — фундамент логики Golden Record: поле trust
 * (Trust Score) определяет, чьё значение атрибута победит при консолидации.
 * Поэтому изменение источников должно быть управляемым и наблюдаемым из UI,
 * а не только правкой в БД.
 *
 * Тонкий слой представления: список, карточка с аналитикой использования,
 * формы CRUD. Доменная работа с БД — в Model_Erp230_Source и агрегатах.
 *
 * Маршруты:
 *   GET  /erp230_sources                — список источников
 *   GET  /erp230_sources/view/<id>      — карточка источника + аналитика
 *   GET  /erp230_sources/create         — форма создания
 *   POST /erp230_sources/create         — сохранить новый
 *   GET  /erp230_sources/edit/<id>      — форма редактирования
 *   POST /erp230_sources/edit/<id>      — сохранить изменения
 *   POST /erp230_sources/delete/<id>    — удаление источника
 *
 * Предупреждение: понижение trust у активного источника может привести к тому,
 * что часть «победивших» текущих значений будет перебита другими источниками
 * при следующей консолидации. Карточка показывает объём таких значений.
 *
 * @package Erp230
 * @version 1.0.0
 */
class Controller_Erp230_Sources extends Controller_Erp230_Main {

	/** Ключ кэша справочника источников. */
	const CACHE_KEY = 'erp230_mdm_sources_all';

	// =======================================================================
	//  СПИСОК
	// =======================================================================

	/**
	 * Список источников данных с базовой статистикой использования.
	 */
	public function action_index()
	{
		if ( ! $this->can('mdm.dictionary.view'))
			throw new HTTP_Exception_403('Нет прав на просмотр справочника источников');

		$sources = ORM::factory('Erp230_Source')
			->order_by('trust', 'DESC')
			->order_by('name', 'ASC')
			->find_all();

		// Агрегат: сколько текущих значений привязано к каждому источнику.
		$usage = $this->_usage_counts();

		$items = [];
		foreach ($sources as $s)
		{
			$items[] = [
				'id'    => $s->id,
				'name'  => $s->display_name,
				'kind'  => $s->kind,
				'trust' => (int) $s->trust_score,
				'usage' => (int) Arr::get($usage, $s->id, 0),
			];
		}

		$this->set_title('Источники данных');
		$this->set_description('Справочник источников MDM и их баллы доверия (Trust Score)');

		$this->template->content = View::factory('erp230/sources/list')
			->set('items', $items);
	}

	// =======================================================================
	//  КАРТОЧКА
	// =======================================================================

	/**
	 * Карточка источника: метаданные + аналитика использования.
	 */
	public function action_view()
	{
		if ( ! $this->can('mdm.dictionary.view'))
			throw new HTTP_Exception_403('Нет прав на просмотр источников');

		$source = $this->_load_source($this->request->param('id'));

		$stats = $this->_usage_stats($source->id);

		$this->set_title('Источник: '.($source->display_name ?: $source->id));

		$this->template->content = View::factory('erp230/sources/card')
			->set('source',   $source)
			->set('stats',    $stats)
			->set('can_edit', $this->can('mdm.dictionary.edit'));
	}

	// =======================================================================
	//  СОЗДАНИЕ
	// =======================================================================

	/**
	 * Форма создания источника + обработка сохранения.
	 */
	public function action_create()
	{
		if ( ! $this->can('mdm.dictionary.edit'))
			throw new HTTP_Exception_403('Нет прав на создание источников');

		$source = ORM::factory('Erp230_Source');
		$errors = [];

		if ($this->request->method() === Request::POST)
		{
			$post = $this->request->post();

            $source->id           = trim((string) Arr::get($post, 'id'));
            $source->display_name = trim((string) Arr::get($post, 'display_name'));
            $source->kind         = trim((string) Arr::get($post, 'kind')) ?: 'external';
            $source->trust_score  = (int) Arr::get($post, 'trust_score', 50);
            $source->save();

            $this->_invalidate_cache();
            $this->_notice('Источник создан: '.$source->id, 'success');

            $this->redirect('erp230_sources/view/'.$source->id);

		}

		$this->set_title('Новый источник данных');

		$this->template->content = View::factory('erp230/sources/form')
			->set('source', $source)
			->set('errors', $errors)
			->set('mode',   'create')
			->set('kinds',  $this->_kinds());
	}

	// =======================================================================
	//  РЕДАКТИРОВАНИЕ
	// =======================================================================

	/**
	 * Форма редактирования источника + обработка сохранения.
	 *
	 * Первичный ключ id — идентификатор источника — не меняется через форму
	 * (он используется в object_properties.source_id как ссылка).
	 */
	public function action_edit()
	{
		if ( ! $this->can('mdm.dictionary.edit'))
			throw new HTTP_Exception_403('Нет прав на редактирование источников');

		$source = $this->_load_source($this->request->param('id'));
		$errors = [];

		// Предупреждение о влиянии понижения trust.
		$old_trust = (int) $source->trust_score;

		if ($this->request->method() === Request::POST)
		{
			$post = $this->request->post();

            $new_trust = (int) Arr::get($post, 'trust_score', $old_trust);

            $source->display_name = trim((string) Arr::get($post, 'display_name'));
            $source->kind         = trim((string) Arr::get($post, 'kind')) ?: $source->kind;
            $source->trust_score  = $new_trust;
            $source->save();

            $this->_invalidate_cache();

            if ($new_trust < $old_trust)
            {
                $this->_notice(
                    'Trust снижен с '.$old_trust.' до '.$new_trust.
                    '. Часть текущих значений может быть перебита при следующей консолидации.',
                    'warning'
                );
            }
            else
            {
                $this->_notice('Источник обновлён: '.$source->id, 'success');
            }

            $this->redirect('erp230_sources/view/'.$source->id);

		}

		$this->set_title('Редактирование источника: '.$source->id);

		$this->template->content = View::factory('erp230/sources/form')
			->set('source', $source)
			->set('errors', $errors)
			->set('mode',   'edit')
			->set('kinds',  $this->_kinds())
			->set('usage',  $this->_usage_counts()[$source->id] ?? 0);
	}

	// =======================================================================
	//  УДАЛЕНИЕ
	// =======================================================================

	/**
	 * Удаление источника. Только POST.
	 * Запрещаем удаление, если источник используется в текущих значениях
	 * (иначе нарушится ссылочная целостность object_properties.source_id).
	 */
	public function action_delete()
	{
		if ($this->request->method() !== Request::POST)
			throw (new HTTP_Exception_405('Удаление возможно только через POST'))->allowed(array('POST'));

		if ( ! $this->can('mdm.dictionary.edit'))
			throw new HTTP_Exception_403('Нет прав на удаление источников');

		$source = $this->_load_source($this->request->param('id'));

		$usage = (int) Arr::get($this->_usage_counts(), $source->id, 0);
		if ($usage > 0)
		{
			$this->_notice(
				'Нельзя удалить источник «'.$source->id.'»: он используется в '.$usage.' текущих значениях.',
				'error'
			);
			$this->redirect('erp230_sources/view/'.$source->id);
		}

		$id = $source->id;
		$source->delete();

		$this->_invalidate_cache();
		$this->_notice('Источник удалён: '.$id, 'success');

		$this->redirect('erp230_sources');
	}

	// =======================================================================
	//  ВНУТРЕННЕЕ: АНАЛИТИКА ИСПОЛЬЗОВАНИЯ
	// =======================================================================

	/**
	 * Агрегат по всем источникам: число текущих значений на каждый источник.
	 * Ключ — source_id, значение — количество (object_properties, is_current).
	 *
	 * @return array<string,int>
	 */
	protected function _usage_counts()
	{
		try
		{
			$rows = DB::query(Database::SELECT,
				'SELECT source_id, COUNT(*) AS cnt
				   FROM object_properties
				  WHERE is_current = TRUE
				  GROUP BY source_id')
				->execute(Database::instance('mdm'))
				->as_array('source_id', 'cnt');

			return array_map('intval', $rows);
		}
		catch (Exception $e)
		{
			$this->_log_error('MDM Source usage_counts error: '.$e->getMessage());
			return [];
		}
	}

	/**
	 * Детальная аналитика по конкретному источнику:
	 *   - properties_current : число текущих значений от источника
	 *   - objects_touched    : число уникальных объектов
	 *   - top_keys           : самые частые ключи атрибутов от источника
	 *
	 * @param  string $source_id
	 * @return array
	 */
	protected function _usage_stats($source_id)
	{
		$stats = [
			'properties_current' => 0,
			'objects_touched'    => 0,
			'top_keys'           => [],
		];

		try
		{
			$db = Database::instance('mdm');

			$summary = DB::query(Database::SELECT,
				'SELECT COUNT(*) AS props, COUNT(DISTINCT object_id) AS objs
				   FROM object_properties
				  WHERE is_current = TRUE AND source_id = :sid')
				->param(':sid', $source_id)
				->execute($db)
				->current();

			if ($summary)
			{
				$stats['properties_current'] = (int) Arr::get($summary, 'props', 0);
				$stats['objects_touched']    = (int) Arr::get($summary, 'objs', 0);
			}

			$stats['top_keys'] = DB::query(Database::SELECT,
				'SELECT key, COUNT(*) AS cnt
				   FROM object_properties
				  WHERE is_current = TRUE AND source_id = :sid
				  GROUP BY key
				  ORDER BY cnt DESC
				  LIMIT 15')
				->param(':sid', $source_id)
				->execute($db)
				->as_array();
		}
		catch (Exception $e)
		{
			$this->_log_error('MDM Source usage_stats error: '.$e->getMessage());
		}

		return $stats;
	}

	// =======================================================================
	//  ВНУТРЕННЕЕ: ЗАГРУЗКА / СПРАВОЧНИКИ / УТИЛИТЫ
	// =======================================================================

	/** Загружает источник или бросает 404. */
	protected function _load_source($id)
	{
		$source = ORM::factory('Erp230_Source', $id);
		if ( ! $source->loaded())
			throw new HTTP_Exception_404('Источник данных не найден');

		return $source;
	}

	/** Допустимые виды источников (kind) для выпадающего списка формы. */
	protected function _kinds()
	{
		return [
			'manual'   => 'Ручной ввод',
			'external' => 'Внешняя система',
			'plm'      => 'PLM',
			'erp'      => 'ERP',
			'sensor'   => 'Телеметрия/датчик',
			'import'   => 'Импорт/файл',
		];
	}

	/** Сброс кэша справочника источников. */
	protected function _invalidate_cache()
	{
		try
		{
			Cache::instance()->delete(self::CACHE_KEY);
		}
		catch (Exception $e)
		{
			// Кэш не критичен — молча игнорируем.
		}
	}

	/**
	 * Универсальный вывод уведомления, устойчивый к конфигурации ядра.
	 *
	 * @param  string $message
	 * @param  string $type  success|warning|error|info
	 * @return void
	 */
	protected function _notice($message, $type = 'info')
	{
		try
		{
			if (class_exists('Notice') AND method_exists('Notice', 'add'))
			{
				Notice::add($type, $message);
				return;
			}

			if (class_exists('Message') AND method_exists('Message', 'set'))
			{
				Message::set($type, $message);
				return;
			}

			if (class_exists('Session'))
			{
				$session = Session::instance();
				$flash   = (array) $session->get_once('erp230_flash', []);
				$flash[] = ['type' => $type, 'text' => $message];
				$session->set('erp230_flash', $flash);
			}
		}
		catch (Exception $e)
		{
			// Уведомления не критичны — молча игнорируем.
		}
	}

	/** Логирование, устойчивое к версии ядра. */
	protected function _log_error($message)
	{
		if (isset(Kohana::$log) AND Kohana::$log)
			Kohana::$log->add(Log::ERROR, $message);
		elseif (class_exists('Log') AND method_exists('Log', 'instance'))
			Log::instance()->add(Log::ERROR, $message);
	}
}