<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Основной интерфейс универсального реестра объектов.
 * Реализует дерево ltree, фильтрацию и таблицу сущностей.
 * 
 * @package Erp227
 * @version 1.2.0
 */
class Controller_Erp227_Objects extends Controller_Erp227_Main {

    /**
     * Главная страница реестра: Дерево + Таблица
     */
    public function action_index()
    {
        // 1. Проверка прав доступа
        if ( ! $this->can('core_registry.view'))
        {
            throw new HTTP_Exception_403('У вас нет прав на просмотр реестра');
        }

        // 2. Получение параметров фильтрации
        $query_params = $this->request->query();
        $type_id   = isset($query_params['type_id'])   ? (int) $query_params['type_id']   : NULL;
        $parent_id = isset($query_params['parent_id']) ? (int) $query_params['parent_id'] : NULL;
        $page      = isset($query_params['page'])      ? (int) $query_params['page']      : 1;
        $search    = isset($query_params['q'])         ? trim($query_params['q'])         : '';

        // 3. Загрузка конфигурации
        $config = Kohana::$config->load('erp227_core_registry');
        $limit  = $config['items_per_page'] ?? 20;

        // 4. Работа с данными через Сервисы и ORM
        $search_service   = Service_Erp227_Search::instance();

        $objects_orm = ORM::factory('Erp227_Object')
            ->active()
            ->latest();

        // Применение фильтров
        if ($search)
        {
            // Если есть поисковый запрос, используем гибридный поиск
            $search_data = $search_service->execute($search, [
                'type_id'   => $type_id,
                'parent_id' => $parent_id,
                'status'    => 'active'
            ], $limit);
            
            $objects = $search_data['results'];
            $total_count = $search_data['total'];
        }
        else
        {
            // Обычный листинг с фильтрами
            if ($type_id)   $objects_orm->where('type_id', '=', $type_id);
            if ($parent_id) $objects_orm->where('parent_id', '=', $parent_id);
            else            $objects_orm->where('parent_id', 'IS', NULL); // Корень по умолчанию

            $total_count = $objects_orm->reset(FALSE)->count_all();
            $objects = $objects_orm
                ->limit($limit)
                ->offset(($page - 1) * $limit)
                ->order_by('name', 'ASC')
                ->find_all();
        }

        // 5. Мета-данные и UI
        $this->set_title('Универсальный реестр объектов');
        $this->set_description('Единая база данных сущностей, оборудования и персонала');

        // 6. Вывод в View
        $this->template->content = View::factory('erp227/objects/list')
            ->set('objects', $objects)
            ->set('types', ORM::factory('Erp227_Type')->find_all())
            ->set('total_count', $total_count)
            ->set('current_filters', [
                'type_id'   => $type_id,
                'parent_id' => $parent_id,
                'q'         => $search,
                'page'      => $page
            ]);
    }

    /**
     * Просмотр детальной карточки объекта
     */
    public function action_view()
    {
        $id = (int) $this->request->param('id');
        $object = ORM::factory('Erp227_Object', $id);

        if ( ! $object->loaded() OR $object->is_deleted)
        {
            throw new HTTP_Exception_404('Объект не найден');
        }

        if ( ! $this->can('core_registry.view', $object))
        {
            throw new HTTP_Exception_403('Нет прав на просмотр этого объекта');
        }

        $this->set_title($object->code . ' | ' . $object->name);
        
        $this->template->content = View::factory('erp227/objects/view')
            ->set('object', $object)
            ->set('links', $object->links->where('is_deleted', '=', false)->find_all());
    }

    /**
     * Обработка создания объекта (POST)
     */
    public function action_create()
    {
        if ( ! $this->can('core_registry.create'))
        {
            throw new HTTP_Exception_403('Нет прав на создание объектов');
        }

        $post = $this->request->post();
        
        if (isset($post['type_id'], $post['name']))
        {
            $registry_service = Service_Erp227_Registry::instance();
            
            // Подготовка данных для сервиса
            $data = [
                'type_id'         => (int) $post['type_id'],
                'parent_id'       => isset($post['parent_id']) ? (int) $post['parent_id'] : NULL,
                'code'            => $post['code'],
                'name'            => $post['name'],
                'kind'            => $post['kind'] ?? 'definition',
                'attributes'      => $post['attributes'] ?? [],
                'status'          => 'active'
            ];

            $object = $registry_service->create_object($data);
            
            // Если объект загружен, перенаправляем на просмотр
            if ($object->loaded()) 
            {
                if ($this->request->is_ajax())
                {
                    return $this->_json_response([
                        'status' => 'ok',
                        'id'     => $object->id,
                        'path'   => $object->path
                    ]);
                }

                $this->redirect('/erp227_objects/view/' . $object->id);
            }
        }

        $this->template->content = View::factory('erp227/objects/edit')
            ->set('types', ORM::factory('Erp227_Type')->find_all());
    }

    /**
     * Редактирование объекта (создание новой ревизии)
     */
    public function action_edit()
    {
        $id = (int) $this->request->param('id');
        $object = ORM::factory('Erp227_Object', $id);

        if ( ! $object->loaded() OR $object->is_deleted)
        {
            throw new HTTP_Exception_404('Объект не найден');
        }

        if ( ! $this->can('core_registry.edit', $object))
        {
            throw new HTTP_Exception_403('Нет прав на редактирование этого объекта');
        }

        $error = NULL;

        if ($this->request->method() === Request::POST)
        {
            $post = $this->request->post();
            $registry_service = Service_Erp227_Registry::instance();

            try
            {
                // Подготовка изменений для создания ревизии
                $changes = [
                    'name'            => $post['name'] ?? $object->name,
                    'parent_id'       => isset($post['parent_id']) ? (int) $post['parent_id'] : $object->parent_id,
                    'attributes'      => $post['attributes'] ?? $object->attributes,
                    'status'          => $post['status'] ?? $object->status
                ];

                $new_revision = $registry_service->create_revision($object->id, $changes);

                if ($this->request->is_ajax())
                {
                    return $this->_json_response([
                        'status' => 'ok',
                        'id'     => $new_revision->id,
                        'revision' => $new_revision->revision
                    ]);
                }

                $this->redirect('/erp227_objects/view/' . $new_revision->id);
            
            }
            catch (HTTP_Exception_Redirect $e)
            {
                // Пробрасываем редирект дальше, чтобы Kohana его обработала
                throw $e;
            }
            catch (Exception $e)
            {
                if ($this->request->is_ajax())
                {
                    return $this->_json_response(['status' => 'error', 'message' => $e->getMessage()], 400);
                }
                
                $error = $e->getMessage();
            }
        }

        $this->set_title('Редактирование: ' . $object->name);

        $this->template->content = View::factory('erp227/objects/edit')
            ->set('object', $object)
            ->set('types', ORM::factory('Erp227_Type')->find_all())
            ->set('error', $error);
    }

    /**
     * Удаление объекта (мягкое через сервис)
     */
    public function action_delete()
    {
        $id = (int) $this->request->param('id');
        $object = ORM::factory('Erp227_Object', $id);

        if ( ! $object->loaded())
        {
            throw new HTTP_Exception_404();
        }

        if ( ! $this->can('core_registry.delete', $object))
        {
            throw new HTTP_Exception_403();
        }

        $registry_service = Service_Erp227_Registry::instance();
        $registry_service->delete_object($id);

        if ($this->request->is_ajax())
        {
            return $this->_json_response(['status' => 'ok']);
        }

        $this->redirect('/erp227_objects');
    }
}