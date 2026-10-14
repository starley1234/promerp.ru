<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Сервис управления «Золотой записью» (Golden Record).
 * Инкапсулирует логику формирования эталонных данных на основе приоритетов источников.
 * 
 * @package Erp230
 * @version 1.3.0
 */
class Service_Erp230_GoldenRecord {

    /**
     * @var Service_Erp230_GoldenRecord
     */
    protected static $_instance;

    /**
     * Singleton instance
     * 
     * @return Service_Erp230_GoldenRecord
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Приватный конструктор
     */
    protected function __construct() {}

	/**
	 * Список объектов реестра с фильтрацией и пагинацией.
	 * total и items считаются на отдельных инстансах ORM,
	 * т.к. count_all() сбрасывает query builder.
	 *
	 * @param  array $filters
	 * @return array ['items' => Collection, 'total' => int]
	 */
	public function get_registry_list(array $filters)
	{
		// --- total: отдельный запрос (count_all() всё равно отбросит select) ---
		$count_query = ORM::factory('Erp230_Object');
		$this->_apply_registry_filters($count_query, $filters);
		$total = $count_query->count_all();

		// --- items: свой инстанс с денормализованным состоянием ---
		$data_query = ORM::factory('Erp230_Object')
			->select(['object_state.attributes', 'state_data']);
		$this->_apply_registry_filters($data_query, $filters);

		$limit  = isset($filters['limit']) ? max((int) $filters['limit'], 1) : 20;
		$page   = isset($filters['page'])  ? max((int) $filters['page'], 1)  : 1;
		$offset = ($page - 1) * $limit;

		$items = $data_query
			->limit($limit)
			->offset($offset)
			->order_by('erp230_object.updated_at', 'DESC')   // явный алиас: updated_at есть и в object_state
			->find_all();

		return [
			'items' => $items,
			'total' => (int) $total,
		];
	}

	/**
	 * Общие фильтры реестра. Применяются к обоим запросам (count и data),
	 * чтобы total и выборка были согласованы.
	 */
	protected function _apply_registry_filters(ORM $query, array $filters)
	{
		$query
			->join('object_state', 'LEFT')
				->on('erp230_object.id', '=', 'object_state.object_id')
			->where('erp230_object.deleted_at', 'IS', NULL);

		// Тип с учётом иерархии LTree.
		if ( ! empty($filters['type_id']))
		{
			$type = ORM::factory('Erp230_Dictionary')
				->where('id', '=', $filters['type_id'])->find();

			if ($type->loaded())
			{
				$query->where('erp230_object.type_id', 'IN',
					DB::expr("(SELECT id FROM types WHERE path <@ '{$type->path}')"));
			}
		}

		// Организация с учётом иерархии LTree.
		if ( ! empty($filters['org_id']))
		{
			$org = ORM::factory('Erp230_Dictionary')
				->set_table_name('org_units')
				->where('id', '=', $filters['org_id'])->find();

			if ($org->loaded())
			{
				$query->where('erp230_object.org_path', '<@', $org->path);
			}
		}

		if ( ! empty($filters['state']))
			$query->where('erp230_object.state', '=', $filters['state']);

		if (isset($filters['is_dirty']) AND $filters['is_dirty'] !== NULL)
			$query->where('erp230_object.is_dirty', '=', (bool) $filters['is_dirty']);

		if ( ! empty($filters['search']))
		{
			$search = strtoupper($filters['search']);
			$query->and_where_open()
				->where(DB::expr('UPPER(erp230_object.master_code)'), 'LIKE', "%{$search}%")
				->or_where('object_state.search', '@@',
					DB::expr("plainto_tsquery('russian', ".Database::instance('mdm')->quote($filters['search']).")"))
				->and_where_close();
		}

		return $query;
	}

    /**
     * Обновление или создание атрибута объекта с проверкой Trust Score.
     * 
     * @param  uuid   $object_id  ID мастер-объекта
     * @param  string $key        Ключ атрибута (snake_case)
     * @param  mixed  $value      Значение (массив или примитив)
     * @param  string $source_id  ID источника (plm, erp, ai и т.д.)
     * @param  string $uom_code   Код единицы измерения (опционально)
     * @param  string $actor_id   ID пользователя (опционально)
     * @return Model_Erp230_Property|null
     * @throws Exception|Validation_Exception
     */
    public function update_attribute($object_id, $key, $value, $source_id, $uom_code = NULL, $actor_id = NULL)
    {
        $result = $this->update_attributes_batch($object_id, [
            $key => ['value' => $value, 'uom' => $uom_code, 'actor_id' => $actor_id]
        ], $source_id, ['use_transaction' => TRUE]);

        if (empty($result['ok_keys']) OR ! in_array($key, $result['ok_keys']))
        {
            return NULL;
        }

        return ORM::factory('Erp230_Property')
            ->where('object_id', '=', $object_id)
            ->where('key', '=', $key)
            ->where('is_current', '=', TRUE)
            ->order_by('created_at', 'DESC')
            ->find();
    }

    /**
     * Пакетное обновление атрибутов объекта.
     * 
     * @param  uuid   $object_id
     * @param  array  $props      Список ['key' => ['value'=>, 'uom'=>, 'actor_id'=>]]
     * @param  string $source_id
     * @param  array  $options    [use_transaction => bool]
     * @return array  ['ok_keys' => [], 'rejected_keys' => [], 'total' => int]
     */
    public function update_attributes_batch($object_id, array $props, $source_id, array $options = [])
    {
        $use_txn = Arr::get($options, 'use_transaction', TRUE);
        $db = Database::instance('mdm');
        
        if ($use_txn) $db->begin();

        $results = [
            'ok_keys'       => [],
            'rejected_keys' => [],
            'total'         => 0
        ];

        try 
        {
            $prop_model = ORM::factory('Erp230_Property');
            $has_changes = FALSE;

            foreach ($props as $k => $v)
            {
                $key      = is_array($v) ? Arr::get($v, 'key', $k) : $k;
                $value    = is_array($v) ? Arr::get($v, 'value') : $v;
                $uom      = is_array($v) ? Arr::get($v, 'uom') : NULL;
                $actor_id = is_array($v) ? Arr::get($v, 'actor_id') : NULL;

                $results['total']++;
				
                /*
                // Минимальная валидация ключа
                // кириллицу нужно разрешить
                if ( ! preg_match('/^[a-z][a-z0-9_.]*$/', $key))
                {
                    $results['rejected_keys'][] = $key;
                    continue;
                }
                */
                

                // Вызов upsert_safe с поддержкой actor_id
                $status = $prop_model->upsert_safe($object_id, $key, $value, $source_id, $uom, $actor_id);

                if ($status === 'ok')
                {
                    $results['ok_keys'][] = $key;
                    $has_changes = TRUE;
                }
                else
                {
                    $results['rejected_keys'][] = $key;
                }
            }

            if ($has_changes)
            {
                DB::query(Database::SELECT, 'SELECT refresh_object_state(:obj)')
                    ->param(':obj', $object_id)
                    ->execute($db);

                Audit::log("MDM: Batch updated properties for object [{$object_id}] from source [{$source_id}]. OK: " . count($results['ok_keys']));
            }

            if ($use_txn) $db->commit();
            return $results;
        }
        catch (Exception $e)
        {
            if ($use_txn) $db->rollback();
            throw $e;
        }
    }

    /**
     * Проверка объекта на соответствие схеме перед публикацией.
     * 
     * @param  uuid $object_id
     * @return array ['ok' => bool, 'errors' => []]
     */
    public function validate_for_publish($object_id)
    {
        return Service_Erp230_SchemaValidator::instance()->validate_object($object_id);
    }

    /**
     * Принудительное формирование "Золотой записи" для всего объекта.
     * 
     * @param  uuid $object_id
     * @return bool
     * @throws Exception
     */
    public function rebuild_golden_record($object_id)
    {
        $db = Database::instance('mdm');
        $db->begin();

        try 
        {
            DB::query(Database::SELECT, 'SELECT refresh_object_state(:obj)')
                ->param(':obj', $object_id)
                ->execute($db);

            $object = ORM::factory('Erp230_Object', $object_id);
            if ($object->loaded())
            {
                $object->is_dirty = FALSE;
                $object->updated_at = date('Y-m-d H:i:s');
                $object->save();
            }

            $db->commit();
            return TRUE;
        }
        catch (ORM_Validation_Exception $e)
        {
            $db->rollback();

            $errors = $e->errors('models');
            Kohana::$log->add(Log::ERROR, 'MDM: rebuild_golden_record validation failed for object [:obj]: :errors', [
                ':obj'    => $object_id,
                ':errors' => json_encode($errors),
            ]);

            throw new Kohana_Exception('Failed to rebuild golden record for object [:obj]: :errors', [
                ':obj'    => $object_id,
                ':errors' => json_encode($errors),
            ], 0, $e);
        }
        catch (Exception $e)
        {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Создание неизменяемого снимка (Baseline) текущей золотой записи.
     * Прокси к каноническому методу Service_Erp230_Consolidation::create_snapshot(),
     * чтобы устранить дублирование логики (Hash-chain, dirty-flag и т.д.).
     * 
     * @param  uuid   $object_id
     * @param  string $actor_id  ID пользователя
     * @param  string $code      Код версии
     * @return Model_Erp230_Baseline
     */
    public function create_snapshot($object_id, $actor_id, $code)
    {
        return Service_Erp230_Consolidation::instance()
            ->create_snapshot($object_id, $actor_id, $code);
    }

	/**
	 * Получение истории снимков (Baselines) конкретного объекта.
	 *
	 * @param  uuid $object_id
	 * @return Database_Result
	 */
	public function get_baselines($object_id)
	{
		return ORM::factory('Erp230_Baseline')
			->where('object_id', '=', $object_id)
			->order_by('seq', 'ASC')
			->find_all();
	}

    /**
     * Проверка целостности цепочки изменений объекта.
     * 
     * @param  uuid $object_id
     * @return array
     */
    public function verify_integrity($object_id)
    {
        return DB::query(Database::SELECT, 'SELECT * FROM verify_baseline_chain(:obj)')
            ->param(':obj', $object_id)
            ->execute(Database::instance('mdm'))
            ->as_array();
    }
}