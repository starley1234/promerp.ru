<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контроллер управления типами объектов и их динамическими схемами.
 * 
 * @package Erp227
 * @version 1.3.0
 */
class Controller_Erp227_Types extends Controller_Erp227_Main {

	/**
	 * Список всех типов объектов с пагинацией и фильтрацией
	 */
	public function action_index()
	{
		// 1. Проверка прав
		if ( ! $this->can('core_registry.manage_types'))
		{
			throw new HTTP_Exception_403('Access Denied');
		}

		// 2. Инициализация ORM
		$types = ORM::factory('Erp227_Type');

		// 3. Фильтрация
		$filter_name = $this->request->query('name');
		$filter_code = $this->request->query('code');

		if ( ! empty($filter_name))
		{
			$types->where('name', 'LIKE', '%' . $filter_name . '%');
		}

		if ( ! empty($filter_code))
		{
			$types->where('code', 'LIKE', '%' . $filter_code . '%');
		}

		// 4. Пагинация
		$total_count = $types->reset(FALSE)->count_all();
		
		$pagination = Pagination::factory(array(
			'total_items'    => $total_count,
			'items_per_page' => 20,
			'view'           => 'pagination/floating',
		));

		$types = $types
			->limit($pagination->items_per_page)
			->offset($pagination->offset)
			->find_all();

		// 5. Вывод
		$this->set_title('Типы объектов реестра');
		$this->template->content = View::factory('erp227/types/index')
			->set('types', $types)
			->set('pagination', $pagination)
			->set('filters', array(
				'name' => $filter_name,
				'code' => $filter_code
			));
	}

	/**
	 * Создание нового типа
	 */
	public function action_create()
	{
		// 1. Проверить can()
		if ( ! $this->can('core_registry.manage_types'))
		{
			throw new HTTP_Exception_403('Access Denied');
		}

		$type = ORM::factory('Erp227_Type');

		// 2. Обработать POST
		if ($this->request->method() === Request::POST)
		{
			try
			{
				$post = $this->request->post();
				$type->values($post, array('code', 'name', 'icon', 'schema', 'ui_config'));
				$type->save();

				Event::fire('erp227.type.created', $type);

				$this->redirect('/erp227_types');
			}
			catch (ORM_Validation_Exception $e)
			{
				$this->template->errors = $e->errors('models');
			}
		}

		// 3. Передать данные в View
		$this->set_title('Создание нового типа');
		$this->template->content = View::factory('erp227/types/edit')
			->set('type', $type);
	}

	/**
	 * Редактирование типа
	 */
	public function action_edit()
	{
		// 1. Получить ID
		$id = (int) $this->request->param('id');
		$type = ORM::factory('Erp227_Type', $id);

		if ( ! $type->loaded())
		{
			throw new HTTP_Exception_404('Type not found');
		}

		// 2. Проверить can()
		if ( ! $this->can('core_registry.manage_types', $type))
		{
			throw new HTTP_Exception_403('Access Denied');
		}

		// 3. Обработать POST
		if ($this->request->method() === Request::POST)
		{
			try
			{
				$post = $this->request->post();
				
				$data = array(
					'code'      => isset($post['code']) ? (string) $post['code'] : $type->code,
					'name'      => isset($post['name']) ? (string) $post['name'] : $type->name,
					'icon'      => isset($post['icon']) ? (string) $post['icon'] : $type->icon,
					'schema'    => isset($post['schema']) ? $post['schema'] : $type->schema,
					'ui_config' => isset($post['ui_config']) ? $post['ui_config'] : $type->ui_config,
				);

				$type->values($data);
				$type->save();

				// 4. Вызвать Event
				Event::fire('erp227.type.updated', $type);

				$this->redirect('erp227_types');
			}
			catch (ORM_Validation_Exception $e)
			{
				$this->template->errors = $e->errors('models');
			}
		}

		// 5. Передать данные в View
		$this->set_title('Редактирование типа: ' . $type->name);
		$this->template->content = View::factory('erp227/types/edit')
			->set('type', $type);
	}

	/**
	 * Конструктор схемы атрибутов типа
	 */
	public function action_schema()
	{
		$id = (int) $this->request->param('id');
		$type = ORM::factory('Erp227_Type', $id);

		if ( ! $type->loaded())
		{
			throw new HTTP_Exception_404('Type not found');
		}

		if ( ! $this->can('core_registry.manage_types', $type))
		{
			throw new HTTP_Exception_403('Access Denied');
		}

		if ($this->request->method() === Request::POST)
		{
			$schema = $this->request->post('schema');
			$type->schema = $schema;
			$type->save();

			Event::fire('erp227.type.schema_updated', $type);
			
			if ($this->request->is_ajax())
			{
				return $this->_json_response(['status' => 'ok']);
			}
			
			$this->redirect('erp227_types');
		}

		$this->set_title('Конструктор атрибутов: ' . $type->name);
		$this->template->content = View::factory('erp227/types/schema')
			->set('type', $type);
	}

	/**
	 * Удаление типа (только если нет связанных объектов)
	 */
	public function action_delete()
	{
		// 1. Получить ID
		$id = (int) $this->request->param('id');
		$type = ORM::factory('Erp227_Type', $id);

		// 2. Проверить can()
		if ( ! $this->can('core_registry.manage_types', $type))
		{
			return $this->_json_response(['status' => 'error', 'message' => 'Forbidden'], 403);
		}

		if ($type->loaded())
		{
			// Проверка на наличие объектов этого типа
			$count = $type->objects->count_all();
			if ($count > 0)
			{
				return $this->_json_response(['status' => 'error', 'message' => 'Нельзя удалить тип, у которого есть объекты'], 400);
			}

			$type->delete();

			// 3. Вызвать Event
			Event::fire('erp227.type.deleted', $id);

			return $this->_json_response(['status' => 'ok']);
		}

		return $this->_json_response(['status' => 'error', 'message' => 'Not found'], 404);
	}

	/**
	 * Получение схемы типа в формате JSON (для динамических форм)
	 */
	public function action_get_schema()
	{
		$id = (int) $this->request->param('id');
		$type = ORM::factory('Erp227_Type', $id);

		if ( ! $type->loaded())
		{
			return $this->_json_response(['status' => 'error'], 404);
		}

		// Проверка прав на чтение реестра
		if ( ! $this->can('core_registry.view'))
		{
			return $this->_json_response(['status' => 'error'], 403);
		}

		$this->_json_response([
			'id'        => $type->id,
			'code'      => $type->code,
			'schema'    => json_decode($type->schema, TRUE),
			'ui_config' => json_decode($type->ui_config, TRUE)
		]);
	}
}