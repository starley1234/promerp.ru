<?php defined('SYSPATH') or die('No direct script access.');

/**
 * UI-контроллер управления структурой изделий (BOM / Digital Twin).
 *
 * Тонкий слой представления над доменными методами моделей:
 *   - Model_Erp230_Object::children()    — прямые дочерние связи
 *   - Model_Erp230_Object::bom_tree()    — разузлование вниз (CTE)
 *   - Model_Erp230_Object::where_used()  — обратный обход вверх (CTE)
 *   - Model_Erp230_Link                  — создание/закрытие связей (защита от циклов)
 *
 * Вся работа с БД — в моделях; здесь только загрузка, валидация ввода,
 * сборка представления и PRG-редиректы после мутаций.
 *
 * Маршруты:
 *   GET  /erp230_structure/tree/<id>?type=&depth=       — дерево BOM объекта
 *   GET  /erp230_structure/where_used/<id>?type=&depth= — где используется компонент
 *   POST /erp230_structure/add_link/<id>                — создать связь (parent = id)
 *   POST /erp230_structure/remove_link/<id>?link=       — закрыть связь
 *
 * @package Erp230
 * @version 1.0.0
 */
class Controller_Erp230_Structure extends Controller_Erp230_Main {

	/** Тип связи по умолчанию. */
	const DEFAULT_LINK_TYPE = 'ebom';

	// =======================================================================
	//  ПРОСМОТР: ДЕРЕВО BOM
	// =======================================================================

	/**
	 * Полное дерево структуры объекта (разузлование вниз).
	 * Плоские рёбра из модели собираются в дерево методом _nest().
	 */
	public function action_tree()
	{
		$object = $this->_load_object($this->request->param('id'));

		if ( ! $this->can('mdm.object.view', $object))
			throw new HTTP_Exception_403('Доступ к структуре данного объекта ограничен');

		$type  = $this->_read_type();
		$depth = $this->_read_depth();

		$rows = $object->bom_tree($type, $depth);

		$this->set_title('Структура: '.($object->display_name() ?: $object->master_code));
		$this->set_description('Разузлование структуры изделия (BOM)');

		$this->template->content = View::factory('erp230/structure/tree')
			->set('object',   $object)
			->set('name',     $object->display_name())
			->set('type',     $type)
			->set('depth',    $depth)
			->set('nodes',    count($rows))
			->set('tree',     $this->_nest($rows, $object->id))
			->set('children', $object->children($type))
			->set('can_edit', $this->can('mdm.object.edit', $object))
			->set('link_types', $this->_link_types());
	}

	// =======================================================================
	//  ПРОСМОТР: ГДЕ ИСПОЛЬЗУЕТСЯ
	// =======================================================================

	/**
	 * Обратный обход структуры: в какие изделия входит компонент.
	 */
	public function action_where_used()
	{
		$object = $this->_load_object($this->request->param('id'));

		if ( ! $this->can('mdm.object.view', $object))
			throw new HTTP_Exception_403('Доступ к структуре данного объекта ограничен');

		$type  = $this->_read_type();
		$depth = $this->_read_depth();

		$used_in = $object->where_used($type, $depth);

		$this->set_title('Применяемость: '.($object->display_name() ?: $object->master_code));
		$this->set_description('Обратный обход структуры (where-used)');

		$this->template->content = View::factory('erp230/structure/where_used')
			->set('object',  $object)
			->set('name',    $object->display_name())
			->set('type',    $type)
			->set('depth',   $depth)
			->set('used_in', $used_in);
	}

	// =======================================================================
	//  МУТАЦИИ: СОЗДАНИЕ СВЯЗИ
	// =======================================================================

	/**
	 * Создание связи (parent = <id> из маршрута).
	 * Только POST. Циклы и самоссылки отсекаются моделью Model_Erp230_Link.
	 *
	 * Тело формы: child_id, link_type?, qty?, designator?
	 */
	public function action_add_link()
	{
		if ($this->request->method() !== Request::POST)
			throw (new HTTP_Exception_405('Создание связи возможно только через POST'))->allowed(array('POST'));

		$object = $this->_load_object($this->request->param('id'));

		if ( ! $this->can('mdm.object.edit', $object))
			throw new HTTP_Exception_403('Нет прав на изменение структуры этого объекта');

		$post  = $this->request->post();
		$child = trim((string) Arr::get($post, 'child_id'));

		if ($child === '')
		{
			$this->_notice('Не указан дочерний объект связи', 'error');
			$this->redirect('erp230_structure/tree/'.$object->id);
		}

		// Валидируем существование дочернего объекта (устойчивая обратная связь для UI).
		$child_obj = ORM::factory('Erp230_Object', $child);
		if ( ! $child_obj->loaded())
		{
			$this->_notice('Дочерний объект не найден', 'error');
			$this->redirect('erp230_structure/tree/'.$object->id);
		}

		try
		{
			$link = ORM::factory('Erp230_Link');
			$link->values([
				'parent_id'  => $object->id,
				'child_id'   => $child,
				'link_type'  => $this->_read_type($post),
				'qty'        => $this->_coerce_qty(Arr::get($post, 'qty', 1)),
				'designator' => $this->_clean_designator(Arr::get($post, 'designator')),
			])->save();

			Event::fire('erp230.link.created', ['link' => $link, 'parent' => $object]);
			Cache::instance()->delete('erp230_dashboard_stats');

			$this->_notice('Связь добавлена', 'success');
		}
		catch (ORM_Validation_Exception $e)
		{
			$errors = $e->errors('models/erp230');
			$this->_notice($this->_format_link_error($errors), 'warning');
		}
		catch (Exception $e)
		{
			$this->_log_error('MDM Structure add_link error: '.$e->getMessage());
			$this->_notice('Не удалось создать связь: '.$e->getMessage(), 'error');
		}

		$this->redirect('erp230_structure/tree/'.$object->id);
	}

	// =======================================================================
	//  МУТАЦИИ: ЗАКРЫТИЕ СВЯЗИ
	// =======================================================================

	/**
	 * Логическое закрытие связи (простановка верхней границы valid_period).
	 * Только POST. Идентификатор связи — в параметре link (query или POST).
	 */
	public function action_remove_link()
	{
		if ($this->request->method() !== Request::POST)
			throw (new HTTP_Exception_405('Удаление связи возможно только через POST'))->allowed(array('POST'));

		$object = $this->_load_object($this->request->param('id'));

		if ( ! $this->can('mdm.object.edit', $object))
			throw new HTTP_Exception_403('Нет прав на изменение структуры этого объекта');

		$link_id = trim((string) Arr::get($this->request->post(), 'link',
			$this->request->query('link')));

		if ($link_id === '')
		{
			$this->_notice('Не указан идентификатор связи', 'error');
			$this->redirect('erp230_structure/tree/'.$object->id);
		}

		try
		{
			$n = DB::query(Database::UPDATE,
				"UPDATE object_links
				    SET valid_period = tstzrange(lower(valid_period), now(), '[)')
				  WHERE id = :id AND upper(valid_period) IS NULL")
				->param(':id', $link_id)
				->execute(Database::instance('mdm'));

			if ($n === 0)
			{
				$this->_notice('Активная связь не найдена (уже закрыта?)', 'warning');
			}
			else
			{
				Event::fire('erp230.link.closed', ['link_id' => $link_id, 'parent' => $object]);
				Cache::instance()->delete('erp230_dashboard_stats');
				$this->_notice('Связь закрыта', 'success');
			}
		}
		catch (Exception $e)
		{
			$this->_log_error('MDM Structure remove_link error: '.$e->getMessage());
			$this->_notice('Не удалось закрыть связь: '.$e->getMessage(), 'error');
		}

		$this->redirect('erp230_structure/tree/'.$object->id);
	}

	// =======================================================================
	//  ВНУТРЕННЕЕ: СБОРКА ДЕРЕВА
	// =======================================================================

	/**
	 * Собирает плоский список рёбер (из bom_tree()) в дерево по parent_id.
	 * Логика идентична Controller_Erp230_Api::_nest() — единый формат узла.
	 *
	 * @param  array  $rows Плоские рёбра
	 * @param  string $root UUID корневого объекта
	 * @return array
	 */
	protected function _nest(array $rows, $root)
	{
		$byParent = [];
		foreach ($rows as $r)
		{
			$byParent[$r['parent_id']][] = $r;
		}

		$build = function ($parent) use (&$build, $byParent) {
			$children = [];
			if (empty($byParent[$parent]))
				return $children;

			foreach ($byParent[$parent] as $r)
			{
				$children[] = [
					'link_id'     => Arr::get($r, 'id'),
					'id'          => $r['child_id'],
					'master_code' => $r['master_code'],
					'type_id'     => $r['type_id'],
					'state'       => $r['state'],
					'qty'         => (float) $r['qty'],
					'designator'  => $r['designator'],
					'level'       => (int) Arr::get($r, 'lvl', 0),
					'children'    => $build($r['child_id']),
				];
			}
			return $children;
		};

		return $build($root);
	}

	// =======================================================================
	//  ВНУТРЕННЕЕ: НОРМАЛИЗАЦИЯ ВВОДА
	// =======================================================================

	/**
	 * Тип связи из запроса (query по умолчанию, либо переданный массив).
	 * Ограничиваем известными значениями, иначе — дефолт.
	 *
	 * @param  array|NULL $source
	 * @return string
	 */
	protected function _read_type($source = NULL)
	{
		$raw = ($source !== NULL)
			? Arr::get($source, 'link_type', Arr::get($source, 'type'))
			: $this->request->query('type');

		$type = strtolower(trim((string) $raw));

		return array_key_exists($type, $this->_link_types())
			? $type
			: self::DEFAULT_LINK_TYPE;
	}

	/**
	 * Глубина обхода из query (1..50).
	 *
	 * @return int
	 */
	protected function _read_depth()
	{
		$depth = (int) $this->request->query('depth');
		return min(max($depth ?: 20, 1), 50);
	}

	/**
	 * Приведение количества к положительному числу (float).
	 *
	 * @param  mixed $raw
	 * @return float
	 */
	protected function _coerce_qty($raw)
	{
		$qty = is_numeric($raw) ? (float) $raw : 1.0;
		return ($qty > 0) ? $qty : 1.0;
	}

	/**
	 * Очистка обозначения (designator).
	 *
	 * @param  mixed $raw
	 * @return string|NULL
	 */
	protected function _clean_designator($raw)
	{
		$d = trim(strip_tags((string) $raw));
		return ($d === '') ? NULL : $d;
	}

	/**
	 * Словарь поддерживаемых типов связей (для форм и валидации).
	 *
	 * @return array code => label
	 */
	protected function _link_types()
	{
		return [
			'ebom' => 'Конструкторская (EBOM)',
			'mbom' => 'Технологическая (MBOM)',
		];
	}

	/**
	 * Человекочитаемое сообщение по ошибкам валидации связи.
	 *
	 * @param  array $errors
	 * @return string
	 */
	protected function _format_link_error(array $errors)
	{
		$child = Arr::get($errors, 'child_id');

		if ($child === 'circular_dependency')
			return 'Связь отклонена: возникнет циклическая зависимость';

		if ($child === 'self_reference')
			return 'Связь отклонена: объект не может ссылаться сам на себя';

		return $child
			? 'Связь отклонена: '.$child
			: 'Связь отклонена: не пройдена валидация';
	}

	// =======================================================================
	//  ВНУТРЕННЕЕ: ЗАГРУЗКА / УВЕДОМЛЕНИЯ / ЛОГ
	// =======================================================================

	/**
	 * Загружает объект или бросает 404.
	 *
	 * @param  string $id
	 * @return Model_Erp230_Object
	 */
	protected function _load_object($id)
	{
		$object = ORM::factory('Erp230_Object', $id);
		if ( ! $object->loaded())
			throw new HTTP_Exception_404('Объект MDM не найден');

		return $object;
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

	/**
	 * Логирование, устойчивое к версии ядра.
	 *
	 * @param  string $message
	 * @return void
	 */
	protected function _log_error($message)
	{
		if (isset(Kohana::$log) AND Kohana::$log)
			Kohana::$log->add(Log::ERROR, $message);
		elseif (class_exists('Log') AND method_exists('Log', 'instance'))
			Log::instance()->add(Log::ERROR, $message);
	}
}