<?php defined('SYSPATH') or die('No direct script access.');

/**
 * UI-контроллер реестра Golden Records (MDM).
 *
 * Тонкий слой представления: список, карточка, формы CRUD, семантический поиск.
 * Вся доменная логика — в Service_Erp230_ObjectFactory, Service_Erp230_GoldenRecord,
 * Model_Erp230_Object и Controller_Erp230_Api.
 *
 * @package Erp230
 * @version 3.3.0
 */
class Controller_Erp230_Objects extends Controller_Erp230_Main {

	/** Идентификатор источника для ручного ввода через веб-морду. */
	const MANUAL_SOURCE = 'manual';

	// =======================================================================
	//  РЕЕСТР
	// =======================================================================

	/**
	 * Список золотых записей с фильтрацией и пагинацией.
	 */
	public function action_index()
	{
		if ( ! $this->can('mdm.object.view'))
			throw new HTTP_Exception_403('У вас нет прав на просмотр реестра MDM');

		$filters = $this->_read_filters();

		$data = Service_Erp230_GoldenRecord::instance()->get_registry_list($filters);

		$this->set_title('Реестр Golden Records');
		$this->set_description('Единый перечень верифицированных мастер-данных изделий');

		$this->template->content = View::factory('erp230/objects/list')
			->set('items',   $data['items'])
			->set('total',   $data['total'])
			->set('filters', $filters)
			->set('pages',   (int) ceil($data['total'] / $filters['limit']))
			->set('types',   $this->_dict_types())
			->set('orgs',    $this->_dict_orgs());
	}

	// =======================================================================
	//  КАРТОЧКА
	// =======================================================================

	/**
	 * Детальная карточка объекта. POST action=verify — аудит целостности.
	 */
	public function action_view()
	{
		$object = $this->_load_object($this->request->param('id'));

		if ( ! $this->can('mdm.object.view', $object))
			throw new HTTP_Exception_403('Доступ к данной записи ограничен');

		// Действие проверки целостности (кнопка на карточке).
		if (Arr::get($this->request->post(), 'action') === 'verify')
		{
			if ($this->can('mdm.steward.edit', $object))
			{
				$result = Service_Erp230_GoldenRecord::instance()->verify_integrity($object->id);
				Event::fire('erp230.object.verified', ['object' => $object, 'result' => $result]);
			}

			// PRG: избегаем повторной отправки формы при обновлении страницы.
			$this->redirect('erp230_objects/view/'.$object->id);
		}

		$name = $this->_object_name($object);

		$this->set_title('Объект: '.($name ?: $object->master_code));

		$this->template->content = View::factory('erp230/objects/view')
			->set('object',     $object)
			->set('name',       $name)
			->set('properties', $object->properties->where('is_current', '=', TRUE)->find_all())
			->set('baselines',  Service_Erp230_GoldenRecord::instance()->get_baselines($object->id))
			->set('is_valid',   $object->is_chain_valid())
			->set('can_edit',   $this->can('mdm.object.edit', $object))
			->set('can_verify', $this->can('mdm.steward.edit', $object));
	}

	// =======================================================================
	//  СОЗДАНИЕ
	// =======================================================================

	/**
	 * Форма создания + обработка сохранения через ObjectFactory.
	 */
	public function action_create()
	{
		if ( ! $this->can('mdm.object.create'))
			throw new HTTP_Exception_403('Нет прав на создание объектов MDM');

		$object = ORM::factory('Erp230_Object');
		$errors = [];
		$candidates = [];
		$redirect_url = NULL;

		if ($this->request->method() === Request::POST)
		{
			$post = $this->request->post();
			
			try 
			{
				$org_id = Arr::get($post, 'org_id');
				$payload = [
					'type_id'          => Arr::get($post, 'type_id'),
					'org_id'           => empty($org_id) ? NULL : $org_id,
					'master_code'      => Arr::get($post, 'master_code'),
					'master_code_mode' => Arr::get($post, 'master_code_mode', 'manual'),
					'properties'       => $this->_extract_properties_from_post($post),
					'source_id'        => self::MANUAL_SOURCE,
				];

				$object = Service_Erp230_ObjectFactory::instance()->create($payload, [
					'source_id'      => self::MANUAL_SOURCE,
					'allow_existing' => FALSE,
					'actor_id'       => $this->_user_id(),
				]);

				Cache::instance()->delete('erp230_dashboard_stats');
				$this->_notice('Объект успешно создан в состоянии Draft', 'success');

				$redirect_url = 'erp230_objects/view/'.$object->id;
			}
			catch (ORM_Validation_Exception $e)
			{
				$errors = $e->errors('models/erp230');
			}
			catch (Exception $e)
			{
				$errors['_external'] = $e->getMessage();
			}

			if ($redirect_url)
			{
				$this->redirect($redirect_url);
			}
		}

		$this->set_title('Новый объект MDM');

		$this->template->content = View::factory('erp230/objects/form')
			->set('object',     $object)
			->set('errors',     $errors)
			->set('candidates', $candidates)
			->set('mode',       'create')
			->set('types',      $this->_dict_types())
			->set('orgs',       $this->_dict_orgs())
			->set('uoms',       $this->_dict_uoms())
			->set('properties', [])
			->set('schema',     $this->_type_schema(Arr::get($this->request->post(), 'type_id', $object->type_id)));
	}

	/**
	 * Массовое добавление объектов одного типа (табличный ввод).
	 */
	public function action_batch()
	{
		if ( ! $this->can('mdm.object.create'))
			throw new HTTP_Exception_403('Нет прав на создание объектов MDM');

		$type_id = $this->request->query('type_id') ?: $this->request->post('type_id');
		$org_id  = $this->request->query('org_id')  ?: $this->request->post('org_id');
		$results = ['success' => [], 'errors' => []];

		if ($this->request->method() === Request::POST AND $type_id)
		{
			$rows = (array) $this->request->post('rows');
			$factory = Service_Erp230_ObjectFactory::instance();
			$actor_id = $this->_user_id();

			foreach ($rows as $index => $row)
			{
				$master_code = Arr::get($row, 'master_code');
				if (empty($master_code)) continue;

				try
				{
					// Извлекаем атрибуты строки (динамические колонки)
					$properties = [];
					foreach (Arr::get($row, 'attrs', []) as $key => $val)
					{
						if (trim((string)$val) === '') continue;
						$properties[] = [
							'key'      => $key,
							'value'    => $this->_coerce_value($val),
							'actor_id' => $actor_id
						];
					}

					$payload = [
						'type_id'          => $type_id,
						'org_id'           => empty($org_id) ? NULL : $org_id,
						'master_code'      => $master_code,
						'master_code_mode' => 'manual',
						'properties'       => $properties,
						'source_id'        => self::MANUAL_SOURCE,
					];

					$obj = $factory->create($payload, [
						'source_id'      => self::MANUAL_SOURCE,
						'allow_existing' => FALSE,
						'actor_id'       => $actor_id,
					]);

					$results['success'][] = "Строка #".($index+1).": Объект {$obj->master_code} создан.";
				}
				catch (Exception $e)
				{
					$results['errors'][] = "Строка #".($index+1)." ({$master_code}): " . $e->getMessage();
				}
			}

			if ( ! empty($results['success']))
			{
				Cache::instance()->delete('erp230_dashboard_stats');
				$this->_notice('Успешно обработано записей: ' . count($results['success']), 'success');
			}
			
			if (empty($results['errors']) AND ! empty($results['success']))
			{
				$this->redirect('erp230_objects');
			}
		}

		$this->set_title('Массовое добавление объектов');
		
		$this->template->content = View::factory('erp230/objects/batch')
			->set('types',   $this->_dict_types())
			->set('orgs',    $this->_dict_orgs())
			->set('uoms',    $this->_dict_uoms())
			->set('type_id', $type_id)
			->set('org_id',  $org_id)
			->set('results', $results)
			->set('schema',  $this->_type_schema($type_id));
	}

	// =======================================================================
	//  РЕДАКТИРОВАНИЕ
	// =======================================================================

	/**
	 * Форма редактирования + обработка сохранения.
	 */
	public function action_edit()
	{
		$object = $this->_load_object($this->request->param('id'));

		if ( ! $this->can('mdm.object.edit', $object))
			throw new HTTP_Exception_403('Нет прав на редактирование этого объекта');

		$errors = [];
		$redirect_url = NULL;

		if ($this->request->method() === Request::POST)
		{
			$post = $this->request->post();
			try
			{
				// 1. Проверка уникальности кода при смене (если не admin)
				$new_code = Service_Erp230_CodeGenerator::instance()->normalize(Arr::get($post, 'master_code'));
				if ($new_code !== $object->master_code)
				{
					Service_Erp230_DuplicateGuard::instance()->assert_unique_code(
						$new_code, $object->type_id, $object->org_id, $object->id
					);
				}

				// 2. Сохранение базовых полей
				$object->values($post, ['master_code', 'org_id', 'type_id']);
				$object->save();

				// 3. Пакетная запись атрибутов через GoldenRecord
				$props = $this->_extract_properties_from_post($post);
				$attr_result = Service_Erp230_GoldenRecord::instance()->update_attributes_batch(
					$object->id, 
					$props, 
					self::MANUAL_SOURCE, 
					['actor_id' => $this->_user_id()]
				);

				Event::fire('erp230.object.updated', ['object' => $object]);
				Cache::instance()->delete('erp230_dashboard_stats');

				$this->_flash_attribute_result($attr_result);

				$redirect_url = 'erp230_objects/view/'.$object->id;
			}
			catch (Kohana_Exception $e)
			{
				$errors['master_code'] = $e->getMessage();
			}
			catch (ORM_Validation_Exception $e)
			{
				$errors = $e->errors('models/erp230');
			}
			catch (Exception $e)
			{
				$errors['_external'] = $e->getMessage();
			}

			if ($redirect_url)
			{
				$this->redirect($redirect_url);
			}
           
		}

		$this->set_title('Редактирование: '.$object->master_code);

		$this->template->content = View::factory('erp230/objects/form')
			->set('object',     $object)
			->set('errors',     $errors)
			->set('mode',       'edit')
			->set('types',      $this->_dict_types())
			->set('orgs',       $this->_dict_orgs())
			->set('uoms',       $this->_dict_uoms())
			->set('properties', $object->properties->where('is_current', '=', TRUE)->find_all())
			->set('schema',     $this->_type_schema($object->type_id));
	}

	// =======================================================================
	//  СМЕНА СОСТОЯНИЯ (контролируемый переход)
	// =======================================================================

	/**
	 * Контролируемая смена состояния объекта через transition_to().
	 */
	public function action_state()
	{
		if ($this->request->method() !== Request::POST)
			throw (new HTTP_Exception_405('Смена состояния возможна только через POST'))->allowed(array('POST'));

		$object = $this->_load_object($this->request->param('id'));

		if ( ! $this->can('mdm.object.edit', $object))
			throw new HTTP_Exception_403('Нет прав на смену состояния этого объекта');

		$next = (string) Arr::get($this->request->post(), 'state');

		try
		{
			$object->transition_to($next, $this->_user_id());

			Event::fire('erp230.object.updated', ['object' => $object]);
			Cache::instance()->delete('erp230_dashboard_stats');
			$this->_notice('Состояние успешно изменено на ' . $next, 'success');
		}
		catch (Exception $e)
		{
			$this->_log_error('MDM Transition Error: '.$e->getMessage());
			$this->_notice('Ошибка перехода: ' . $e->getMessage(), 'error');
		}

		$this->redirect('erp230_objects/view/'.$object->id);
	}

	// =======================================================================
	//  УДАЛЕНИЕ (мягкое)
	// =======================================================================

	/**
	 * Мягкое удаление объекта (deleted_at + state=archived).
	 */
	public function action_delete()
	{
		if ($this->request->method() !== Request::POST)
			throw (new HTTP_Exception_405('Удаление возможно только через POST'))->allowed(array('POST'));

		$object = $this->_load_object($this->request->param('id'));

		if ( ! $this->can('mdm.object.delete', $object))
			throw new HTTP_Exception_403('Нет прав на удаление этого объекта');

		$object->deleted_at = date('Y-m-d H:i:s');
		$object->state      = 'archived';
		$object->save();

		Event::fire('erp230.object.deleted', ['object' => $object]);
		Cache::instance()->delete('erp230_dashboard_stats');

		$this->redirect('erp230_objects');
	}

	// =======================================================================
	//  ВЕРСИОНИРОВАНИЕ (Snapshots)
	// =======================================================================

	/**
	 * Создание неизменяемого снимка состояния (Baseline).
	 */
	public function action_snapshot()
	{
		if ($this->request->method() !== Request::POST)
			throw (new HTTP_Exception_405('Создание снимка возможно только через POST'))->allowed(array('POST'));

		$object = $this->_load_object($this->request->param('id'));

		if ( ! $this->can('mdm.baseline.create', $object))
			throw new HTTP_Exception_403('Нет прав на создание снимков для этого объекта');

		try
		{
			$code = Arr::get($this->request->post(), 'code');
			$baseline = Service_Erp230_Consolidation::instance()->create_snapshot(
				$object->id, 
				$this->_user_id(), 
				$code
			);

			$this->_notice('Снимок версии ' . $baseline->code . ' успешно создан', 'success');
		}
		catch (Exception $e)
		{
			$this->_log_error('MDM Snapshot Error: '.$e->getMessage());
			$this->_notice('Ошибка создания снимка: ' . $e->getMessage(), 'error');
		}

		$this->redirect('erp230_objects/view/'.$object->id);
	}

	// =======================================================================
	//  СЕМАНТИЧЕСКИЙ ПОИСК (AJAX/JSON)
	// =======================================================================

	/**
	 * GET /erp230/search?q=...&limit=... — векторный поиск через LLM.
	 */
	public function action_search()
	{
		if ( ! $this->can('mdm.object.view'))
			return $this->_json_response(['status' => 'error', 'message' => 'Forbidden'], 403);

		$text  = trim((string) $this->request->query('q'));
		$limit = min(max((int) $this->request->query('limit') ?: 10, 1), 50);

		if ($text === '')
			return $this->_json_response(['status' => 'ok', 'query' => '', 'results' => []]);

		try
		{
			$vector = Llm::get_embedding($text);
			if (empty($vector))
				throw new Exception('Не удалось получить эмбеддинг запроса');

			$rows   = Model_Erp230_Search::instance()->semantic_search(json_encode($vector), $limit);
			$output = [];

			foreach ($rows as $row)
			{
				$obj = ORM::factory('Erp230_Object', $row['object_id']);
				if ($obj->loaded())
				{
					$output[] = [
						'id'       => $obj->id,
						'code'     => $obj->master_code,
						'type'     => $obj->type->loaded() ? $obj->type->display_name : $obj->type_id,
						'distance' => round((float) $row['distance'], 4),
					];
				}
			}

			return $this->_json_response([
				'status'  => 'ok',
				'query'   => $text,
				'count'   => count($output),
				'results' => $output,
			]);
		}
		catch (Exception $e)
		{
			$this->_log_error('MDM Semantic Search Error: '.$e->getMessage());
			return $this->_json_response([
				'status'  => 'error',
				'message' => 'Сервис семантического поиска временно недоступен',
			], 503);
		}
	}

	// =======================================================================
	//  ВНУТРЕННЕЕ: АТРИБУТЫ
	// =======================================================================

	/**
	 * Извлечение и нормализация атрибутов из POST-массивов формы.
	 */
	protected function _extract_properties_from_post(array $post)
	{
		$properties = [];
		$keys   = (array) Arr::get($post, 'attr_key',   []);
		$values = (array) Arr::get($post, 'attr_value', []);
		$uoms   = (array) Arr::get($post, 'attr_uom',   []);
		$user_id = $this->_user_id();

		foreach ($keys as $i => $rawKey)
		{
			$key = trim((string) $rawKey);
			if ($key === '') continue;

			$rawVal = isset($values[$i]) ? $values[$i] : NULL;
			if ($rawVal === NULL OR trim((string) $rawVal) === '') continue;

			$uom = isset($uoms[$i]) ? trim((string) $uoms[$i]) : NULL;

			$properties[] = [
				'key'      => $key,
				'value'    => $this->_coerce_value($rawVal),
				'uom'      => ($uom === '') ? NULL : $uom,
				'actor_id' => $user_id
			];
		}

		return $properties;
	}

	/**
	 * Приведение строкового значения формы к скаляру для jsonb.
	 */
	protected function _coerce_value($raw)
	{
		if ( ! is_string($raw)) return $raw;
		$v = trim($raw);
		$low = strtolower($v);
		if ($low === 'true')  return TRUE;
		if ($low === 'false') return FALSE;
		if (preg_match('/^-?(0|[1-9]\d*)(\.\d+)?$/', $v))
		{
			return (strpos($v, '.') === FALSE) ? (int) $v : (float) $v;
		}
		return $v;
	}

	/**
	 * Формирует flash-сообщение по результату записи атрибутов.
	 */
	protected function _flash_attribute_result(array $result)
	{
		if (empty($result['total'])) return;

		if ( ! empty($result['rejected_keys']))
		{
			$msg = 'Часть атрибутов не принята (более доверенный источник): '
				.implode(', ', $result['rejected_keys']);
			$this->_notice($msg, 'warning');
		}
		elseif (count($result['ok_keys']) > 0)
		{
			$this->_notice('Атрибуты сохранены: '.count($result['ok_keys']), 'success');
		}
	}

	/**
	 * Универсальный вывод уведомления.
	 */
	protected function _notice($message, $type = 'info')
	{
		try
		{
			if (class_exists('Notice'))
			{
				Notice::add($type, $message);
			}
			elseif (class_exists('Session'))
			{
				$session = Session::instance();
				$flash   = (array) $session->get_once('erp230_flash', []);
				$flash[] = ['type' => $type, 'text' => $message];
				$session->set('erp230_flash', $flash);
			}
		}
		catch (Exception $e) {}
	}

	// =======================================================================
	//  ВНУТРЕННЕЕ: ФИЛЬТРЫ / ЗАГРУЗКА / СПРАВОЧНИКИ
	// =======================================================================

	protected function _read_filters()
	{
		$q = $this->request->query();
		return [
			'type_id'  => isset($q['type_id']) ? (string) $q['type_id'] : NULL,
			'org_id'   => isset($q['org_id'])  ? (string) $q['org_id']  : NULL,
			'state'    => isset($q['state'])   ? (string) $q['state']   : 'active',
			'search'   => isset($q['search'])  ? trim((string) $q['search']) : NULL,
			'is_dirty' => isset($q['is_dirty']) ? (bool) $q['is_dirty'] : NULL,
			'page'     => isset($q['page'])    ? max((int) $q['page'], 1) : 1,
			'limit'    => isset($q['limit'])   ? min(max((int) $q['limit'], 1), 100) : 20,
		];
	}

	protected function _load_object($id)
	{
		$object = ORM::factory('Erp230_Object', $id);
		if ( ! $object->loaded())
			throw new HTTP_Exception_404('Объект MDM не найден');
		return $object;
	}

	protected function _object_name($object)
	{
		return ($object AND $object->loaded()) ? $object->display_name() : NULL;
	}

	protected function _type_schema($type_id)
	{
		if (empty($type_id)) return [];
		try
		{
			$type = ORM::factory('Erp230_Type', $type_id);
			return $type->loaded() ? $type->schema_array() : [];
		}
		catch (Exception $e)
		{
			$this->_log_error('MDM Type schema load error: '.$e->getMessage());
		}
		return [];
	}

	protected function _dict_types()
	{
		$cache = Cache::instance();
		if ($types = $cache->get('erp230_mdm_types_all')) return $types;
		$types = ORM::factory('Erp230_Type')->order_by('path', 'ASC')->find_all()->as_array();
		$cache->set('erp230_mdm_types_all', $types, Date::HOUR);
		return $types;
	}

	protected function _dict_orgs()
	{
		$cache = Cache::instance();
		if ($orgs = $cache->get('erp230_mdm_orgs')) return $orgs;
		$orgs = ORM::factory('Erp230_OrgUnit')->find_all()->as_array();
		$cache->set('erp230_mdm_orgs', $orgs, Date::HOUR);
		return $orgs;
	}

	protected function _dict_uoms()
	{
		$cache = Cache::instance();
		if ($uoms = $cache->get('erp230_mdm_uoms')) return $uoms;
		$uoms = ORM::factory('Erp230_Uom')->find_all()->as_array();
		$cache->set('erp230_mdm_uoms', $uoms, Date::HOUR);
		return $uoms;
	}

	protected function _user_id()
	{
		return (class_exists('Auth') AND Auth::instance()->get_user()) 
			? Auth::instance()->get_user()->id 
			: NULL;
	}

	protected function _log_error($message)
	{
		if (class_exists('Log'))
			Log::instance()->add(Log::ERROR, $message);
	}
}