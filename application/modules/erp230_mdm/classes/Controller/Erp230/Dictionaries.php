<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контроллер управления справочниками MDM.
 *
 * Единая точка входа для ведения справочников модуля Master Data Management:
 *   - Типы изделий (types)         — иерархия ltree + JSON-схема атрибутов
 *   - Единицы измерения (uom)      — код + базовый код + коэффициент + обозначения (ГОСТ/ОКЕИ)
 *   - Организации (org_units)      — иерархия ltree (холдинг/заводы/цеха)
 *
 * Соответствует структуре БД. Контроллер тонкий:
 * вся работа с БД инкапсулирована в ORM-моделях.
 *
 * @package Erp230
 * @version 2.2.0
 */
class Controller_Erp230_Dictionaries extends Controller_Erp230_Main {

	// =======================================================================
	//  ХАБ СПРАВОЧНИКОВ
	// =======================================================================

	/**
	 * Единая точка входа — обзор доступных справочников.
	 */
	public function action_index()
	{
		if ( ! $this->can('mdm.dictionary.view'))
			throw new HTTP_Exception_403('Доступ к справочникам запрещён');

		$sections = [
			[
				'title'       => 'Типы изделий',
				'description' => 'Иерархия типов (ltree) и JSON-схемы атрибутов',
				'href'        => URL::site('erp230_types'),
				'icon'        => 'fas fa-sitemap',
				'count'       => $this->_safe_count('Erp230_Type'),
			],
			[
				'title'       => 'Единицы измерения',
				'description' => 'Коды ОКЕИ, национальные и международные обозначения',
				'href'        => URL::site('erp230_dictionaries/uom'),
				'icon'        => 'fas fa-ruler-combined',
				'count'       => $this->_safe_count('Erp230_Uom'),
			],
			[
				'title'       => 'Организации',
				'description' => 'Организационная структура холдинга (ltree)',
				'href'        => URL::site('erp230_dictionaries/org'),
				'icon'        => 'fas fa-building',
				'count'       => $this->_safe_count('Erp230_OrgUnit'),
			],
			[
				'title'       => 'Источники данных',
				'description' => 'Реестр источников и их Trust Score',
				'href'        => URL::site('erp230_sources'),
				'icon'        => 'fas fa-database',
				'count'       => $this->_safe_count('Erp230_Source'),
			],
		];

		$this->set_title('Справочники MDM');
		$this->set_description('Управление мастер-данными: типы, единицы измерения, организации, источники');

		$this->template->content = View::factory('erp230/dictionaries/index')
			->set('sections', $sections);
	}

	// =======================================================================
	//  ОРГАНИЗАЦИИ (дерево)
	// =======================================================================

	/**
	 * Список организаций в виде дерева.
	 */
	public function action_org()
	{
		if ( ! $this->can('mdm.dictionary.view'))
			throw new HTTP_Exception_403('Доступ запрещён');

		$flat = ORM::factory('Erp230_OrgUnit')
			->order_by('path', 'ASC')
			->find_all();

		$this->set_title('Организационная структура');
		$this->set_description('Иерархия подразделений холдинга (ltree)');

		$this->template->content = View::factory('erp230/dictionaries/org')
			->set('units',    $flat)
			->set('tree',     $this->_build_org_tree($flat))
			->set('can_edit', $this->can('mdm.dictionary.edit'))
			->set('can_del',  $this->can('mdm.admin.all'));
	}

	// =======================================================================
	//  ТИПЫ ИЗДЕЛИЙ (дерево)
	// =======================================================================

	/**
	 * Список типов изделий в виде дерева.
	 */
	public function action_types()
	{
		if ( ! $this->can('mdm.dictionary.view'))
			throw new HTTP_Exception_403('Доступ запрещён');

		$flat = ORM::factory('Erp230_Type')
			->order_by('path', 'ASC')
			->find_all();

		$this->set_title('Справочник типов изделий');
		$this->set_description('Иерархия типов изделий и связанные JSON-схемы атрибутов');

		$this->template->content = View::factory('erp230/dictionaries/types')
			->set('types',    $flat)
			->set('tree',     $this->_build_type_tree($flat))
			->set('can_edit', $this->can('mdm.dictionary.edit'))
			->set('can_del',  $this->can('mdm.admin.all'));
	}

	/**
	 * Форма создания/редактирования типа.
	 */
	public function action_type_form()
	{
		if ( ! $this->can('mdm.dictionary.edit'))
			throw new HTTP_Exception_403('Недостаточно прав для редактирования');

		$id   = $this->request->param('id');
		$type = ORM::factory('Erp230_Type', $id);
		$mode = $type->loaded() ? 'edit' : 'create';

		$this->set_title($type->loaded()
			? 'Редактирование типа: '.$type->display_name
			: 'Новый тип изделия');

		$this->template->content = View::factory('erp230/dictionaries/type_form')
			->set('type',    $type)
			->set('mode',    $mode)
			->set('parents', $this->_type_options($type->id))
			->set('errors',  []);
	}

	/**
	 * Сохранение/создание типа изделия.
	 */
	public function action_type_save()
	{
		if ( ! $this->can('mdm.dictionary.edit'))
			throw new HTTP_Exception_403('Недостаточно прав для редактирования');

		if ($this->request->method() !== Request::POST)
			throw (new HTTP_Exception_405('Сохранение возможно только через POST'))->allowed(array('POST'));

		$id   = $this->request->param('id');
		$post = $this->request->post();

		$target_id = $id ?: Arr::get($post, 'id');

		$parent = Arr::get($post, 'parent_id');
		if ($parent !== NULL AND $parent !== '' AND $parent === $target_id)
			return $this->_type_error($target_id, $post, ['parent_id' => 'Узел не может быть родителем самого себя']);

		if (array_key_exists('schema', $post))
		{
			$post['schema'] = $this->_normalize_schema($post['schema']);
			if ($post['schema'] === FALSE)
				return $this->_type_error($target_id, $post, ['schema' => 'Некорректный JSON в схеме атрибутов']);
		}

		if (isset($post['parent_id']) AND $post['parent_id'] === '')
			$post['parent_id'] = NULL;

		$type = ORM::factory('Erp230_Type', $target_id);
		$type->values($post, ['id', 'parent_id', 'display_name', 'schema']);

		try
		{
			$type->save();

			Event::fire('mdm.dictionary.type_updated', $type);
			$this->_bust_types_cache();

			if ($this->request->is_ajax())
				return $this->_json_response(['status' => 'ok', 'data' => $type->as_array()]);

			$this->redirect('erp230_dictionaries/types');
		}
		catch (ORM_Validation_Exception $e)
		{
			$errors = $e->errors('models');

			if ($this->request->is_ajax())
				return $this->_json_response(['status' => 'error', 'errors' => $errors], 422);

			return $this->_type_error($target_id, $post, $errors);
		}
	}

	/**
	 * Удаление типа. Разрешено только администратору.
	 */
	public function action_type_delete()
	{
		if ( ! $this->can('mdm.admin.all'))
			throw new HTTP_Exception_403('Удаление разрешено только администратору');

		if ($this->request->method() !== Request::POST)
			throw (new HTTP_Exception_405('Удаление возможно только через POST'))->allowed(array('POST'));

		$id   = $this->request->param('id');
		$type = ORM::factory('Erp230_Type', $id);

		if ( ! $type->loaded())
			throw new HTTP_Exception_404('Тип не найден');

		$children = ORM::factory('Erp230_Type')
			->where('parent_id', '=', $id)
			->count_all();

		if ($children > 0)
		{
			$this->_log_error('MDM: попытка удалить тип "'.$id.'" с '.$children.' дочерними узлами');
			$this->redirect('erp230_dictionaries/types');
			return;
		}

		$used = ORM::factory('Erp230_Object')
			->where('type_id', '=', $id)
			->count_all();

		if ($used > 0)
		{
			$this->_log_error('MDM: попытка удалить тип "'.$id.'", используемый '.$used.' объектами');
			$this->redirect('erp230_dictionaries/types');
			return;
		}

		$type->delete();
		Event::fire('mdm.dictionary.type_deleted', ['id' => $id]);
		$this->_bust_types_cache();

		$this->redirect('erp230_dictionaries/types');
	}

	/**
	 * Получение JSON-схемы типа (AJAX).
	 */
	public function action_get_schema()
	{
		if ( ! $this->can('mdm.dictionary.view'))
			return $this->_json_response(['status' => 'error', 'message' => 'Forbidden'], 403);

		$id   = $this->request->query('id');
		$type = ORM::factory('Erp230_Type', $id);

		if ( ! $type->loaded())
			return $this->_json_response(['status' => 'error', 'message' => 'Type not found'], 404);

		$schema = $type->schema;
		if (is_string($schema))
		{
			$decoded = json_decode($schema, TRUE);
			$schema  = (json_last_error() === JSON_ERROR_NONE) ? $decoded : NULL;
		}

		return $this->_json_response([
			'status'  => 'ok',
			'type_id' => $type->id,
			'schema'  => $schema,
		]);
	}

	// =======================================================================
	//  ЕДИНИЦЫ ИЗМЕРЕНИЯ (UOM)
	// =======================================================================

	/**
	 * Список единиц измерения.
	 */
	public function action_uom()
	{
		if ( ! $this->can('mdm.dictionary.view'))
			throw new HTTP_Exception_403('Доступ запрещён');

		$uoms = ORM::factory('Erp230_Uom')
			->order_by('base_code', 'ASC')
			->order_by('code', 'ASC')
			->find_all();

		$this->set_title('Единицы измерения');
		$this->set_description('Справочник единиц измерения (ОКЕИ) с коэффициентами пересчёта и международными кодами');

		$this->template->content = View::factory('erp230/dictionaries/uom')
			->set('uoms',     $uoms)
			->set('can_edit', $this->can('mdm.dictionary.edit'))
			->set('can_del',  $this->can('mdm.admin.all'));
	}

	/**
	 * Сохранение единицы измерения.
	 */
	public function action_uom_save()
	{
		if ( ! $this->can('mdm.dictionary.edit'))
			throw new HTTP_Exception_403('Недостаточно прав');

		if ($this->request->method() !== Request::POST)
			throw (new HTTP_Exception_405('Сохранение возможно только через POST'))->allowed(array('POST'));

		$post = $this->request->post();
		$code = trim((string) Arr::get($post, 'code'));

		if ($code === '')
		{
			if ($this->request->is_ajax())
				return $this->_json_response(['status' => 'error', 'errors' => ['code' => 'Код обязателен']], 422);

			$this->redirect('erp230_dictionaries/uom');
			return;
		}

		if (isset($post['base_code']) AND trim($post['base_code']) === '')
			$post['base_code'] = NULL;

		if ( ! isset($post['factor']) OR $post['factor'] === '')
			$post['factor'] = 1;

		$uom = ORM::factory('Erp230_Uom', $code);
		$uom->values($post, [
			'code', 
			'base_code', 
			'factor', 
			'name', 
			'symbol_nat', 
			'symbol_intl', 
			'code_nat', 
			'code_intl'
		]);

		try
		{
			$uom->save();

			Event::fire('mdm.dictionary.uom_saved', $uom);

			if ($this->request->is_ajax())
				return $this->_json_response(['status' => 'ok', 'data' => $uom->as_array()]);

			$this->redirect('erp230_dictionaries/uom');
		}
		catch (ORM_Validation_Exception $e)
		{
			if ($this->request->is_ajax())
				return $this->_json_response(['status' => 'error', 'errors' => $e->errors('models')], 422);

			$this->redirect('erp230_dictionaries/uom');
		}
	}

	/**
	 * Удаление единицы измерения.
	 */
	public function action_uom_delete()
	{
		if ( ! $this->can('mdm.admin.all'))
			throw new HTTP_Exception_403('Удаление разрешено только администратору');

		if ($this->request->method() !== Request::POST)
			throw (new HTTP_Exception_405('Удаление возможно только через POST'))->allowed(array('POST'));

		$code = $this->request->param('id');
		$uom  = ORM::factory('Erp230_Uom', $code);

		if ($uom->loaded())
		{
			$dependents = ORM::factory('Erp230_Uom')
				->where('base_code', '=', $code)
				->where('code', '!=', $code)
				->count_all();

			if ($dependents > 0)
			{
				$this->_log_error('MDM: попытка удалить базовую единицу "'.$code.'", используемую '.$dependents.' единицами');
				$this->redirect('erp230_dictionaries/uom');
				return;
			}

			$uom->delete();
			Event::fire('mdm.dictionary.uom_deleted', ['code' => $code]);
		}

		$this->redirect('erp230_dictionaries/uom');
	}

	// =======================================================================
	//  ВНУТРЕННЕЕ
	// =======================================================================

	/**
	 * Строит иерархическое дерево организаций.
	 */
	protected function _build_org_tree($flat)
	{
		$nodes  = [];
		$byId   = [];

		foreach ($flat as $u)
		{
			$byId[$u->id] = ['unit' => $u, 'children' => []];
		}

		foreach ($byId as $id => &$node)
		{
			$pid = $node['unit']->parent_id;
			if ($pid !== NULL AND $pid !== '' AND isset($byId[$pid]))
			{
				$byId[$pid]['children'][] =& $node;
			}
			else
			{
				$nodes[] =& $node;
			}
		}
		unset($node);

		return $nodes;
	}

	/**
	 * Строит иерархическое дерево типов.
	 */
	protected function _build_type_tree($flat)
	{
		$nodes  = [];
		$byId   = [];

		foreach ($flat as $t)
		{
			$byId[$t->id] = ['type' => $t, 'children' => []];
		}

		foreach ($byId as $id => &$node)
		{
			$pid = $node['type']->parent_id;
			if ($pid !== NULL AND $pid !== '' AND isset($byId[$pid]))
			{
				$byId[$pid]['children'][] =& $node;
			}
			else
			{
				$nodes[] =& $node;
			}
		}
		unset($node);

		return $nodes;
	}

	/**
	 * Плоский список типов для выпадающего выбора родителя.
	 */
	protected function _type_options($exclude_id = NULL)
	{
		$options = [];

		$rows = ORM::factory('Erp230_Type')
			->order_by('path', 'ASC')
			->find_all();

		foreach ($rows as $t)
		{
			if ($exclude_id !== NULL AND $t->id === $exclude_id)
				continue;

			$depth  = $t->path ? max(0, substr_count((string) $t->path, '.')) : 0;
			$indent = str_repeat('— ', $depth);

			$options[$t->id] = $indent.($t->display_name ?: $t->id).' ('.$t->id.')';
		}

		return $options;
	}

	/**
	 * Нормализует поле schema из формы.
	 */
	protected function _normalize_schema($raw)
	{
		if (is_array($raw))
			return json_encode($raw, JSON_UNESCAPED_UNICODE);

		$raw = trim((string) $raw);

		if ($raw === '')
			return '{}';

		$decoded = json_decode($raw, TRUE);
		if (json_last_error() !== JSON_ERROR_NONE)
			return FALSE;

		return json_encode($decoded, JSON_UNESCAPED_UNICODE);
	}

	/**
	 * Повторно отрисовывает форму типа с ошибками.
	 */
	protected function _type_error($id, array $post, array $errors)
	{
		$type = ORM::factory('Erp230_Type', $id);
		if ( ! $type->loaded())
		{
			$type->values($post, ['id', 'parent_id', 'display_name', 'schema']);
		}

		$mode = $this->request->param('id') ? 'edit' : 'create';

		$this->set_title('Ошибка сохранения типа');

		$this->template->content = View::factory('erp230/dictionaries/type_form')
			->set('type',    $type)
			->set('mode',    $mode)
			->set('parents', $this->_type_options($id))
			->set('errors',  $errors);
	}

	/**
	 * Безопасный подсчёт строк справочника.
	 */
	protected function _safe_count($model)
	{
		try
		{
			return (int) ORM::factory($model)->count_all();
		}
		catch (Exception $e)
		{
			return 0;
		}
	}

	/** Сброс кэша справочника типов. */
	protected function _bust_types_cache()
	{
		try
		{
			Cache::instance()->delete('erp230_mdm_types_root');
		}
		catch (Exception $e)
		{
		}
	}

	/** Логирование. */
	protected function _log_error($message)
	{
		if (isset(Kohana::$log) AND Kohana::$log)
			Kohana::$log->add(Log::ERROR, $message);
		elseif (class_exists('Log') AND method_exists('Log', 'instance'))
			Log::instance()->add(Log::ERROR, $message);
	}
}