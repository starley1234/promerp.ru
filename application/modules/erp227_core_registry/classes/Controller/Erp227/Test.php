<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контроллер для расширенного тестирования функционала API Erp227_Core_Registry.
 * Включает тесты векторного поиска и индексации.
 */
class Controller_Erp227_Test extends Controller {

    /**
     * Диагностический контроллер: в PRODUCTION отключён.
     */
    public function before()
    {
        parent::before();

        if (Kohana::$environment === Kohana::PRODUCTION)
        {
            throw new HTTP_Exception_403('Диагностический контроллер отключён в PRODUCTION');
        }
    }


    public function action_index()
    {
        $this->response->body("<h1>Erp227 API Test Console</h1><ul>
            <li><a href='/erp227_test/run_all'>Запустить все тесты</a></li>
            <li><a href='/erp227_test/run_vector_tests'>Запустить только векторные тесты</a></li>
            <li><a href='/erp227_test/clear'>Очистить тестовые данные</a></li>
        </ul>");
    }

    /**
     * Запуск последовательного тестирования всех методов API
     */
    public function action_run_all()
    {
        $results = [];

        try {
            // 1. Создание типа объекта (erp_227_object_types)
            $type = ORM::factory('Erp227_Type');
            $type->code = 'test_equipment_' . time();
            $type->name = 'Тестовое оборудование';
            $type->schema = json_encode(['power' => 'number', 'vendor' => 'string', 'test_tag' => 'string']);
            $type->save();
            $results['01_create_type'] = "Success (ID: {$type->id})";

            // 2. Тест API: Получение типов
            $request = Request::factory('erp227_api/types');
            $response = $request->execute();
            $results['02_api_types'] = $response->status() == 200 ? "Success" : "Failed (Status: {$response->status()})";

            // 3. Тест API: Создание объекта (POST)
            $post_data = [
                'type_id' => $type->id,
                //'code'    => 'TEST-OBJ-002',
                'name'    => 'Станок лазерной резки',
                'kind'    => 'asset',
                'attributes' => ['power' => 1500, 'vendor' => 'Trumpf', 'test_tag' => 'collection_test_123']
            ];
            
            $request_create = Request::factory('erp227_api/create')
                ->method(Request::POST)
                ->body(json_encode($post_data));
            
            $response_create = $request_create->execute();
            $res_create = json_decode($response_create->body(), TRUE);
            $obj_id = $res_create['id'] ?? null;
            $results['03_api_create_object'] = $obj_id ? "Success (ID: $obj_id)" : "Failed";

            // 4. Тест API: Получение объекта по ID
            if ($obj_id) {
                $request_get = Request::factory('erp227_api/get/' . $obj_id);
                $res_get = json_decode($request_get->execute()->body(), TRUE);
                $results['04_api_get_by_id'] = ($res_get['status'] ?? '') == 'success' ? "Success" : "Failed";
            }

            // 5. Тест API: Обновление объекта (Новая ревизия)
            if ($obj_id) {
                $update_data = [
                    'name' => 'Станок лазерной резки (Модернизированный)',
                    'attributes' => ['power' => 2000, 'vendor' => 'Trumpf', 'test_tag' => 'collection_test_123']
                ];
                $request_update = Request::factory('erp227_api/update/' . $obj_id)
                    ->method(Request::POST)
                    ->body(json_encode($update_data));
                $res_update = json_decode($request_update->execute()->body(), TRUE);
                $results['05_api_update_object'] = ($res_update['status'] ?? '') == 'success' ? "Success" : "Failed";
            }

            // 6. Тест API: Создание дочернего объекта
            if ($obj_id) {
                $child_data = [
                    'type_id'   => $type->id,
                    'parent_id' => $obj_id,
                    'code'      => 'TEST-COMP-001',
                    'name'      => 'Лазерная головка',
                    'kind'      => 'component',
                    'attributes' => ['test_tag' => 'collection_test_123']
                ];
                $request_child = Request::factory('erp227_api/create')
                    ->method(Request::POST)
                    ->body(json_encode($child_data));
                $res_child = json_decode($request_child->execute()->body(), TRUE);
                $child_id = $res_child['id'] ?? null;
                $results['06_api_create_child'] = $child_id ? "Success (ID: $child_id)" : "Failed";

                // 7. Тест API: Получение детей
                $request_get_children = Request::factory('erp227_api/get_children/' . $obj_id);
                $res_children = json_decode($request_get_children->execute()->body(), TRUE);
                $results['07_api_get_children'] = count($res_children['data'] ?? []) > 0 ? "Success" : "Failed";
            }

            // 8. Тест API: Создание связи (Link)
            if (isset($obj_id) && isset($child_id)) {
                $link_data = [
                    'source_id' => $obj_id,
                    'target_id' => $child_id,
                    'link_type' => 'contains',
                    'quantity'  => 1,
                    'data'      => ['critical' => true]
                ];
                $request_link = Request::factory('erp227_api/link')
                    ->method(Request::POST)
                    ->body(json_encode($link_data));
                $res_link = json_decode($request_link->execute()->body(), TRUE);
                $results['08_api_create_link'] = isset($res_link['link_id']) ? "Success" : "Failed";

                // 9. Тест API: Получение связей объекта
                $request_links = Request::factory('erp227_api/links/' . $obj_id);
                $res_links = json_decode($request_links->execute()->body(), TRUE);
                $results['09_api_get_links'] = count($res_links['data'] ?? []) > 0 ? "Success" : "Failed";
            }

            // 10. Тест API: Поиск (FTS/Vector)
            $request_search = Request::factory('erp227_api/search')
                ->query('q', 'лазерной')
                ->query('type_id', (string)$type->id);
            
            $res_search = json_decode($request_search->execute()->body(), TRUE);
            $results['10_api_search'] = !empty($res_search['data']) ? "Success (Found: " . count($res_search['data']) . ")" : "Failed or Empty";

            // 11. Тест API: Поиск коллекции (JSONB фильтрация)
            $request_collection = Request::factory('erp227_api/collection')
                ->query('attr', ['test_tag' => 'collection_test_123'])
                ->query('type_id', (string)$type->id);
            
            $res_collection = json_decode($request_collection->execute()->body(), TRUE);
            $results['11_api_collection_search'] = (!empty($res_collection['data']) && $res_collection['count'] >= 2) 
                ? "Success (Found: " . $res_collection['count'] . ")" 
                : "Failed (Found: " . ($res_collection['count'] ?? 0) . ")";

            // 12. Тест Векторного функционала
            if ($obj_id) {
                $results['12_vector_indexing'] = $this->_test_vector_indexing($obj_id);
                $results['13_vector_search'] = $this->_test_vector_search('оборудование для резки');
            }

            // 14. Тест API: Удаление объекта
            if ($child_id) {
                $request_del = Request::factory('erp227_api/delete/' . $child_id)
                    ->method(Request::DELETE);
                $res_del = json_decode($request_del->execute()->body(), TRUE);
                $results['14_api_delete_object'] = ($res_del['status'] ?? '') == 'success' ? "Success" : "Failed";
            }

        } catch (Exception $e) {
            $results['error'] = $e->getMessage();
        }

        $this->response->body("<h3>Final Results</h3><pre>" . print_r($results, true) . "</pre><a href='/erp227_test'>Назад</a>");
    }

    /**
     * Отдельный запуск тестов векторного поиска
     */
    public function action_run_vector_tests()
    {
        $results = [];
        
        // Создаем тестовый объект для индексации
        $type = ORM::factory('Erp227_Type')->where('code', 'LIKE', 'test_%')->find();
        if (!$type->loaded()) {
            $type = Service_Erp227_Registry::instance()->create_type([
                'code' => 'test_vector_type',
                'name' => 'Тип для векторных тестов'
            ]);
        }

        $obj = Service_Erp227_Registry::instance()->create_object([
            'type_id' => $type->id,
            'code' => 'TEST-VEC-' . time(),
            'name' => 'Высокоточный промышленный манипулятор',
            'attributes' => ['precision' => '0.01mm', 'axis' => 6]
        ]);

        $results['create_object'] = "ID: " . $obj->id;
        $results['indexing'] = $this->_test_vector_indexing($obj->id);
        $results['search_semantic'] = $this->_test_vector_search('Линия монтажа');
        $results['search_exact'] = $this->_test_vector_search('манипулятор');

        $this->response->body("<h3>Vector Search Results</h3><pre>" . print_r($results, true) . "</pre><a href='/erp227_test'>Назад</a>");
    }

    /**
     * Тест индексации (генерации эмбеддинга)
     */
    private function _test_vector_indexing($id)
    {
        $object = ORM::factory('Erp227_Object', $id);
        if (!$object->loaded()) return "Failed: Object not loaded";

        $success = Service_Erp227_Search::instance()->reindex_object($object);
        
        if ($success) {
            $count = DB::select(array(DB::expr('COUNT(*)'), 'cnt'))
                ->from('erp_227_embeddings')
                ->where('object_id', '=', $id)
                ->execute()
                ->get('cnt');
            return $count > 0 ? "Success (Embedding created)" : "Failed (Record not found in DB)";
        }

        return "Failed (Service returned false - check LLM/Config)";
    }

    /**
     * Тест векторного поиска
     */
    private function _test_vector_search($query)
    {
        $search_service = Service_Erp227_Search::instance();
        $results = $search_service->execute($query, [], 5);

        if (!empty($results['results'])) {
            $found_names = [];
            foreach ($results['results'] as $obj) {
                $found_names[] = $obj->name . " (ID: {$obj->id})";
            }
            return "Success. Found " . $results['total'] . " items: " . implode(', ', $found_names);
        }

        return "No results found for query: " . $query;
    }

    /**
     * Очистка тестовых данных
     */
    public function action_clear()
    {
        $db = Database::instance();
        // Удаляем связи, где источником или целью являются тестовые объекты
        $db->query(Database::DELETE, "DELETE FROM erp_227_object_links WHERE source_id IN (SELECT id FROM erp_227_objects WHERE code LIKE 'TEST-%') OR target_id IN (SELECT id FROM erp_227_objects WHERE code LIKE 'TEST-%')");
        // Удаляем эмбеддинги
        $db->query(Database::DELETE, "DELETE FROM erp_227_embeddings WHERE object_id IN (SELECT id FROM erp_227_objects WHERE code LIKE 'TEST-%')");
        // Удаляем объекты
        $db->query(Database::DELETE, "DELETE FROM erp_227_objects WHERE code LIKE 'TEST-%'");
        // Удаляем типы
        $db->query(Database::DELETE, "DELETE FROM erp_227_object_types WHERE code LIKE 'test_%'");
        
        $this->response->body("Тестовые данные удалены. <a href='/erp227_test'>Назад</a>");
    }
}