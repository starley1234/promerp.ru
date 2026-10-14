<?php defined('SYSPATH') or die('No direct script access.');

/**
 * UI-контроллер управления иерархией типов объектов (MDM).
 * 
 * Позволяет просматривать дерево типов, редактировать JSON-схемы атрибутов
 * и управлять метаданными типов.
 *
 * @package Erp230
 * @version 1.0.2
 */
class Controller_Erp230_Types extends Controller_Erp230_Main {

	/**
	 * Список всех типов в виде дерева (используя ltree path).
	 */
	public function action_index()
	{
		if ( ! $this->can('mdm.dictionary.view'))
			throw new HTTP_Exception_403('Нет прав на просмотр справочников');

		$types = ORM::factory('Erp230_Type')
			->order_by('path', 'ASC')
			->find_all();

		$this->set_title('Типы объектов MDM');
		$this->template->content = View::factory('erp230/types/list')
			->set('types', $types);
	}

	/**
	 * Просмотр и редактирование карточки типа.
	 */
	public function action_view()
	{
		$id = $this->request->param('id');
		$type = ORM::factory('Erp230_Type', $id);

		if ( ! $type->loaded())
			throw new HTTP_Exception_404('Тип объекта не найден');

		if ($this->request->method() === Request::POST)
		{
			if ( ! $this->can('mdm.dictionary.edit'))
				throw new HTTP_Exception_403('Нет прав на редактирование типов');

			$post = $this->request->post();
			
			// Обработка JSON-схемы
			$schema_raw = Arr::get($post, 'schema');
			if ( ! empty($schema_raw))
			{
				$decoded = json_decode($schema_raw, TRUE);
				if (json_last_error() !== JSON_ERROR_NONE)
					throw new Kohana_Exception('Ошибка в формате JSON схемы');
				
				$type->schema = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
			}

			$type->values($post, ['display_name', 'parent_id']);
			$type->save();

			Cache::instance()->delete('erp230_mdm_types_all');
			$this->redirect('erp230_types/view/'.$type->id);
		}

		$this->set_title('Тип: ' . $type->display_name);
		$this->template->content = View::factory('erp230/types/card')
			->set('type', $type)
			->set('parents', ORM::factory('Erp230_Type')->find_all())
			->set('can_edit', $this->can('mdm.dictionary.edit'));
	}

	/**
	 * Создание нового типа.
	 */
	public function action_create()
	{
		if ( ! $this->can('mdm.dictionary.edit'))
			throw new HTTP_Exception_403('Нет прав на создание типов');

		$type = ORM::factory('Erp230_Type');

		if ($this->request->method() === Request::POST)
		{
			$post = $this->request->post();
			
			// Исправление ошибки Undefined array key 0 в ORM::run_filter
			// Проблема вызвана тем, что в Model_Erp230_Type::filters() 
			// указан ключ TRUE => [['trim' => TRUE]], что некорректно для Kohana ORM.
			// Мы фильтруем данные вручную перед передачей в values().
			
			$data = [
				'id'           => mb_strtolower(trim(Arr::get($post, 'id'))),
				'display_name' => strip_tags(trim(Arr::get($post, 'display_name'))),
				'parent_id'    => trim(Arr::get($post, 'parent_id')) ?: NULL,
				'schema'       => Arr::get($post, 'schema')
			];

			$type->values($data, ['id', 'display_name', 'parent_id', 'schema']);
			$type->save();

			Cache::instance()->delete('erp230_mdm_types_all');
			$this->redirect('erp230_types');
		}

		$this->set_title('Новый тип объекта');
		$this->template->content = View::factory('erp230/types/card')
			->set('type', $type)
			->set('parents', ORM::factory('Erp230_Type')->find_all())
			->set('can_edit', TRUE);
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
			// Уведомления не критичны
		}
	}
}