<?php defined('SYSPATH') or die('No direct script access.');

/**
 * API Контроллер для управления универсальным реестром объектов (Digital Twin Core).
 * Использует сервисы для работы с данными.
 *
 * @package    Erp227
 * @category   Controller
 */
class Controller_Erp227_Api extends Controller {

    /**
     * @var bool  Рендерить шаблон после действия
     */
    public $auto_render = TRUE;


    /**
     * Выполнение гибридного поиска (FTS + Vector)
     * GET /api/erp227/search?q=запрос&type_id=1
     */
    public function action_search()
    {
        $query = $this->request->query('q');
        $filters = [
            'type_id'   => $this->request->query('type_id'),
            'parent_id' => $this->request->query('parent_id'),
            'status'    => $this->request->query('status'),
        ];
        $limit = (int) $this->request->query('limit') ?: 20;

        try 
        {
            $search_service = Service_Erp227_Search::instance();
            $data = $search_service->execute((string)$query, array_filter($filters), $limit);

            $results = [];
            foreach ($data['results'] as $obj)
            {
                $results[] = $this->_serialize_object($obj);
            }

            $this->response->body(json_encode([
                'status' => 'success',
                'total'  => $data['total'],
                'data'   => $results
            ]));
        }
        catch (Exception $e)
        {
            $this->_error($e->getMessage());
        }
    }

    /**
     * Получение коллекции объектов по атрибутам (JSONB фильтрация)
     * GET /api/erp227/collection?attr[system_id]=123&attr[material]=metal
     */
    public function action_collection()
    {
        $attributes = $this->request->query('attr');
        $filters = [
            'type_id'   => $this->request->query('type_id'),
            'parent_id' => $this->request->query('parent_id'),
            'status'    => $this->request->query('status'),
            'kind'      => $this->request->query('kind'),
        ];
        $limit = (int) $this->request->query('limit') ?: 100;

        if ( ! is_array($attributes) OR empty($attributes))
        {
            $this->_error('Attributes for filtering are required (use attr[key]=value)', 400);
        }

        try 
        {
            $search_service = Service_Erp227_Search::instance();
            $objects = $search_service->find_by_attributes($attributes, array_filter($filters), $limit);

            $results = [];
            foreach ($objects as $obj)
            {
                $results[] = $this->_serialize_object($obj);
            }

            $this->response->body(json_encode([
                'status' => 'success',
                'count'  => count($results),
                'data'   => $results
            ]));
        }
        catch (Exception $e)
        {
            $this->_error($e->getMessage());
        }
    }

    /**
     * Получение одного объекта по ID или коду
     * GET /api/erp227/get/:id_or_code
     */
    public function action_get()
    {
        $id_or_code = $this->request->param('id');
        $registry = Service_Erp227_Registry::instance();
        
        if (is_numeric($id_or_code))
        {
            $object = $registry->get_by_id($id_or_code);
        }
        else
        {
            $object = $registry->get_object($id_or_code);
        }

        if ( ! $object->loaded() OR $object->is_deleted)
        {
            $this->_error('Object not found (ID/Code: '.$id_or_code.')', 404);
        }

        $this->response->body(json_encode([
            'status' => 'success',
            'data'   => $this->_serialize_object($object)
        ]));
    }

    /**
     * Создание нового объекта
     * POST /api/erp227/create
     */
    public function action_create()
    {
        $data = json_decode($this->request->body(), TRUE);

        try 
        {
            $registry = Service_Erp227_Registry::instance();
            
            
            /*
            // Если код не указан, пытаемся сгенерировать его или проверяем на пустоту
            if (empty($data['code'])) {
                $data['code'] = NULL;
            }
            */
            
            
            print_r ($data);

            $object = $registry->create_object($data);

            // Если включен векторный поиск, индексируем
            try {
                Service_Erp227_Search::instance()->reindex_object($object);
            } catch (Exception $e) {
                // Ошибка индексации не должна прерывать создание объекта
            }

            $this->response->body(json_encode([
                'status' => 'success',
                'id'     => $object->id,
                'data'   => $this->_serialize_object($object)
            ]));
        }
        catch (Exception $e)
        {
            $this->_error($e->getMessage(), 500);
        }
    }

    /**
     * Обновление объекта (создание новой ревизии)
     * POST /api/erp227/update/:id
     */
    public function action_update()
    {
        $id = $this->request->param('id');
        $data = json_decode($this->request->body(), TRUE);

        try 
        {
            $registry = Service_Erp227_Registry::instance();
            $object = $registry->update_object($id, $data);

            // Переиндексация для поиска
            try {
                Service_Erp227_Search::instance()->reindex_object($object);
            } catch (Exception $e) {}

            $this->response->body(json_encode([
                'status' => 'success',
                'data'   => $this->_serialize_object($object)
            ]));
        }
        catch (Exception $e)
        {
            $this->_error($e->getMessage());
        }
    }

    /**
     * Удаление объекта (мягкое удаление)
     * DELETE /api/erp227/delete/:id
     */
    public function action_delete()
    {
        $id = $this->request->param('id');

        try 
        {
            $registry = Service_Erp227_Registry::instance();
            $success = $registry->delete_object($id);

            if ( ! $success)
            {
                $this->_error('Object not found for deletion', 404);
            }

            $this->response->body(json_encode(['status' => 'success']));
        }
        catch (Exception $e)
        {
            $this->_error($e->getMessage());
        }
    }

    /**
     * Получение списка типов объектов и их схем
     * GET /api/erp227/types
     */
    public function action_types()
    {
        $registry = Service_Erp227_Registry::instance();
        $types = $registry->get_types();
        $result = [];
        
        foreach ($types as $type)
        {
            $result[] = [
                'id'        => $type->id,
                'code'      => $type->code,
                'name'      => $type->name,
                'schema'    => is_string($type->schema) ? json_decode($type->schema) : $type->schema,
                'ui_config' => is_string($type->ui_config) ? json_decode($type->ui_config) : $type->ui_config,
                'icon'      => $type->icon
            ];
        }

        $this->response->body(json_encode(['status' => 'success', 'data' => $result]));
    }

    /**
     * Получение дочерних объектов (дерево)
     * GET /api/erp227/get_children/:id
     */
    public function action_get_children()
    {
        $id = $this->request->param('id');
        $registry = Service_Erp227_Registry::instance();
        
        $children = $registry->get_children($id);

        $result = [];
        foreach ($children as $child)
        {
            $result[] = $this->_serialize_object($child);
        }

        $this->response->body(json_encode(['status' => 'success', 'data' => $result]));
    }

    /**
     * Добавление связи между объектами
     * POST /api/erp227/link
     */
    public function action_link()
    {
        $data = json_decode($this->request->body(), TRUE);
        
        try 
        {
            $registry = Service_Erp227_Registry::instance();
            $link = $registry->add_link(
                $data['source_id'],
                $data['target_id'],
                $data['link_type'],
                $data['quantity'] ?? 1.0,
                [
                    'unit' => $data['unit'] ?? NULL,
                    'data' => $data['data'] ?? []
                ]
            );

            $this->response->body(json_encode([
                'status' => 'success',
                'link_id' => $link->id
            ]));
        }
        catch (Exception $e)
        {
            $this->_error($e->getMessage());
        }
    }

    /**
     * Получение всех связей объекта
     * GET /api/erp227/links/:id
     */
    public function action_links()
    {
        $id = $this->request->param('id');
        $registry = Service_Erp227_Registry::instance();
        
        $outgoing = $registry->get_links($id, 'outgoing');
        $incoming = $registry->get_links($id, 'incoming');
        
        $result = [];
        foreach ([$outgoing, $incoming] as $collection)
        {
            foreach ($collection as $link)
            {
                $result[] = [
                    'id'        => $link->id,
                    'source_id' => $link->source_id,
                    'target_id' => $link->target_id,
                    'link_type' => $link->link_type,
                    'quantity'  => $link->quantity,
                    'unit'      => $link->unit,
                    'data'      => is_string($link->data) ? json_decode($link->data) : $link->data
                ];
            }
        }

        $this->response->body(json_encode(['status' => 'success', 'data' => $result]));
    }

    /**
     * Вспомогательный метод сериализации объекта
     */
    protected function _serialize_object($obj)
    {
        return [
            'id'            => $obj->id,
            'external_uuid' => $obj->external_uuid,
            'type_id'       => $obj->type_id,
            'type_name'     => $obj->type->name,
            'parent_id'     => $obj->parent_id,
            'code'          => $obj->code,
            'name'          => $obj->name,
            'kind'          => $obj->kind,
            'revision'      => $obj->revision,
            'status'        => $obj->status,
            'path'          => $obj->path,
            'attributes'    => is_string($obj->attributes) ? json_decode($obj->attributes) : $obj->attributes,
            'current_state' => is_string($obj->current_state) ? json_decode($obj->current_state) : $obj->current_state,
            'updated_at'    => $obj->updated_at
        ];
    }

    /**
     * Формирование JSON ответа с ошибкой.
     *
     * Раньше метод заканчивался `send_headers(); echo …; exit;` — это убивало
     * вызывающего: в браузере заголовки и тело уходили мимо конвейера фреймворка,
     * а внутри процесса (дымовой прогон, тесты, MCP-мост, внутренний вызов
     * сервиса или планировщик) `exit` прекращал работу **всей программы**.
     * Именно так дымовой прогон молча обрывался на первом же неверном параметре
     * API (см. `docs/IMPROVEMENTS.md` B16).
     *
     * Теперь ответ кладётся в `$this->response` (его отдаёт фреймворк), а
     * выполнение действия останавливает исключение — его перехватывает
     * `execute()` ниже. Смысл для вызывающего тот же: дальше действия не идёт.
     */
    protected function _error($message, $code = 400, $errors = [])
    {
        Kohana::$log->add(Log::ERROR, 'API Error :code: :message. Errors: :errors', [
            ':code' => $code,
            ':message' => $message,
            ':errors' => json_encode($errors)
        ]);

        $this->auto_render = FALSE;

        $this->response
            ->status($code)
            ->headers('Content-Type', 'application/json')
            ->body(json_encode([
                'status' => 'error',
                'message' => $message,
                'errors' => $errors
            ]));

        throw new Exception_Erp227_Api_Response($message, $code);
    }

    /**
     * Выполнение действия с остановкой на ошибке API.
     *
     * `_error()` выбрасывает `Exception_Erp227_Api_Response` — ответ уже
     * сформирован, поэтому исключение здесь только прекращает действие
     * (аналог прежнего `exit`, но без убийства процесса вызывающего).
     *
     * @return  Response
     */
    public function execute()
    {
        try
        {
            return parent::execute();
        }
        catch (Exception_Erp227_Api_Response $e)
        {
            return $this->response;
        }
    }
}