<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Сервис гибридного поиска Core Registry.
 * Объединяет полнотекстовый поиск (TSVector) и семантическое сходство (pgvector).
 * 
 * @package Erp227
 * @version 1.2.2
 */
class Service_Erp227_Search {

    /**
     * @var Service_Erp227_Search
     */
    protected static $_instance;

    /**
     * @return Service_Erp227_Search
     */
    public static function instance()
    {
        if (self::$_instance === NULL) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    protected function __construct() {}

    /**
     * Выполняет гибридный поиск по реестру объектов.
     * 
     * @param string $query  Поисковый запрос
     * @param array  $filters Фильтры (type_id, parent_id, status)
     * @param int    $limit   Лимит результатов
     * @return array [results => Model_Erp227_Object[], total => int]
     */
    public function execute(string $query, array $filters = [], int $limit = 20): array
    {
        $config = Kohana::$config->load('erp227_core_registry');
        $query = trim($query);

        if (empty($query)) {
            return ['results' => [], 'total' => 0];
        }

        // 1. Полнотекстовый поиск (TSVector)
        $ts_results = $this->_search_fulltext($query, $filters, $limit);

        // 2. Векторный поиск (Semantic), если включен
        $vector_results = [];
        if ($config->get('vector_search.enabled')) {
            $vector_results = $this->_search_vector($query, $filters, $limit);
        }

        // 3. Слияние и ранжирование
        return $this->_merge_results($ts_results, $vector_results, $limit);
    }

    /**
     * Получает коллекцию объектов по набору атрибутов в JSONB поле attributes.
     */
    public function find_by_attributes(array $attributes, array $filters = [], int $limit = 100): array
    {
        $orm = ORM::factory('Erp227_Object')
            ->where('is_deleted', '=', false)
            ->where('is_latest', '=', true);

        $this->_apply_filters($orm, $filters);

        foreach ($attributes as $key => $value) {
            $json_criteria = json_encode([$key => $value]);
            $orm->where(DB::expr('attributes'), '@>', DB::expr(Database::instance()->quote($json_criteria)));
        }

        return $orm->limit($limit)->find_all()->as_array();
    }

    /**
     * Полнотекстовый поиск с использованием весов из БД (search_vector)
     */
    protected function _search_fulltext(string $query, array $filters, int $limit): array
    {
        $orm = ORM::factory('Erp227_Object')
            ->where('is_deleted', '=', false)
            ->where('is_latest', '=', true);

        $this->_apply_filters($orm, $filters);

        $db = Database::instance();
        $quoted_query = $db->quote($query);

        $orm->where(DB::expr('search_vector'), '@@', DB::expr("plainto_tsquery('russian', $quoted_query)"));
        $orm->order_by(DB::expr("ts_rank(search_vector, plainto_tsquery('russian', $quoted_query))"), 'DESC');

        return $orm->limit($limit)->find_all()->as_array();
    }

    /**
     * Семантический поиск через эмбеддинги (pgvector)
     */
    protected function _search_vector(string $query, array $filters, int $limit): array
    {
        try {
            $config = Kohana::$config->load('erp227_core_registry');
            $model_name = $config->get('vector_search.model_name') ?: 'text-embedding-qwen3-embedding-0.6b';
            
            // Получаем вектор через системный класс LLM (как в Erp222)
            $embedding = Llm::model($model_name)->get_embedding($query);
        
        	
            
            if (empty($embedding) OR ! is_array($embedding)) {
                return [];
            }

            $vector_str = '[' . implode(',', $embedding) . ']';
            $db = Database::instance();
            
            // Поиск по косинусному расстоянию (<=>)
            // Используем подзапрос для получения ID, чтобы сохранить производительность
            $sql = "SELECT object_id FROM erp_227_embeddings 
                    ORDER BY embedding <=> :vector ASC 
                    LIMIT :limit";

            $query_res = DB::query(Database::SELECT, $sql)
                ->parameters([
                    ':vector' => $vector_str,
                    ':limit'  => $limit * 2
                ])->execute($db);

            $ids = [];
            foreach ($query_res as $row) {
                $ids[] = (int) $row['object_id'];
            }

            if (empty($ids)) return [];

            $orm = ORM::factory('Erp227_Object')
                ->where('id', 'IN', $ids)
                ->where('is_deleted', '=', false)
                ->where('is_latest', '=', true);
            
            $this->_apply_filters($orm, $filters);

            // Сохраняем порядок релевантности вектора через функцию idx или CASE
            $ids_ordered = implode(',', $ids);
            $orm->order_by(DB::expr("idx(ARRAY[{$ids_ordered}]::bigint[], id)"));

            return $orm->limit($limit)->find_all()->as_array();
		
        } catch (Exception $e) {
            Kohana::$log->add(Log::ERROR, 'Erp227 Search Vector Error: :msg', [':msg' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Применение базовых фильтров к ORM
     */
    protected function _apply_filters(ORM $orm, array $filters): void
    {
        if (!empty($filters['type_id'])) {
            $orm->where('type_id', '=', (int)$filters['type_id']);
        }
        if (!empty($filters['parent_id'])) {
            $orm->where('parent_id', '=', (int)$filters['parent_id']);
        }
        if (!empty($filters['status'])) {
            $orm->where('status', '=', $filters['status']);
        }
        if (!empty($filters['kind'])) {
            $orm->where('kind', '=', $filters['kind']);
        }
    }

    /**
     * Слияние результатов двух типов поиска
     */
    protected function _merge_results(array $ts, array $vec, int $limit): array
    {
        $merged = [];
        $seen_ids = [];

        foreach ($ts as $obj) {
            $merged[] = $obj;
            $seen_ids[] = $obj->id;
        }

        foreach ($vec as $obj) {
            if (!in_array($obj->id, $seen_ids)) {
                $merged[] = $obj;
                $seen_ids[] = $obj->id;
            }
        }

        $result_slice = array_slice($merged, 0, $limit);

        return [
            'results' => $result_slice,
            'total'   => count($merged)
        ];
    }

    /**
     * Пересборка эмбеддинга для конкретного объекта
     */
    public function reindex_object(Model_Erp227_Object $object): bool
    {
        $config = Kohana::$config->load('erp227_core_registry');
        if (!$config->get('vector_search')['enabled']) return false;

        $db = Database::instance();
        
        try {
            $model_name = $config->get('vector_search.model_name') ?: 'text-embedding-3-small';
            
            // Формируем текст для индексации
            $text_to_embed = "Name: {$object->name}. Code: {$object->code}.";
            if ($object->attributes) {
                $attrs = is_array($object->attributes) ? $object->attributes : json_decode($object->attributes, TRUE);
                if ($attrs) {
                    $text_to_embed .= " Attributes: " . json_encode($attrs, JSON_UNESCAPED_UNICODE);
                }
            }
            
            $vector = Llm::model($model_name)->get_embedding($text_to_embed);
            if (!$vector OR !is_array($vector)) return false;
            
            $vector_str = '[' . implode(',', $vector) . ']';

            $db->begin();

            // Удаляем старые эмбеддинги объекта
            DB::delete('erp_227_embeddings')
                ->where('object_id', '=', $object->id)
                ->execute($db);

            // Вставляем новый
            DB::insert('erp_227_embeddings', ['object_id', 'model_name', 'embedding', 'created_at'])
                ->values([
                    $object->id, 
                    $model_name, 
                    DB::expr("'$vector_str'::vector"), 
                    date('Y-m-d H:i:s')
                ])
                ->execute($db);

            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db) $db->rollback();
            Kohana::$log->add(Log::ERROR, 'Erp227 Reindex Error: :msg', [':msg' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Находит и индексирует один непроиндексированный объект (от новых к старым).
     * Используется в фоновых задачах.
     * 
     * @return bool True если объект был найден и проиндексирован, False если нечего индексировать
     */
    public function index_next_unindexed(): bool
    {
        // Ищем объект, для которого нет записи в erp_227_embeddings
        // Сортируем по ID DESC, чтобы сначала обрабатывать последние созданные объекты
        $object = ORM::factory('Erp227_Object')
            ->alias('obj')
            ->join(['erp_227_embeddings', 'emb'], 'LEFT')
                ->on('obj.id', '=', 'emb.object_id')
            ->where('emb.object_id', 'IS', NULL)
            ->where('obj.is_deleted', '=', false)
            ->where('obj.is_latest', '=', true)
            ->order_by('obj.id', 'DESC')
            ->limit(1)
            ->find();

        if (!$object->loaded()) {
            return false;
        }

        return $this->reindex_object($object);
    }
}