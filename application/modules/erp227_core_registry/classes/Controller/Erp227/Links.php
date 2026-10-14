<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контроллер управления связями между объектами (BOM, назначения, локации).
 * 
 * @package Erp227
 * @version 1.2.1
 */
class Controller_Erp227_Links extends Controller_Erp227_Main {

    /**
     * Главная страница списка связей (общий реестр)
     */
    public function action_index()
    {
        if ( ! $this->can('core_registry.view'))
        {
            throw new HTTP_Exception_403('Access denied');
        }

        // 1. Создаем базовый объект ORM
        $query = ORM::factory('Erp227_Link')
            ->where('is_deleted', '=', FALSE);

        // 2. Фильтрация
        $filters = [
            'link_type' => $this->request->query('link_type'),
            'source_id' => $this->request->query('source_id'),
            'target_id' => $this->request->query('target_id'),
        ];

        if ( ! empty($filters['link_type']))
        {
            $query->where('link_type', '=', $filters['link_type']);
        }

        if ( ! empty($filters['source_id']))
        {
            $query->where('source_id', '=', (int) $filters['source_id']);
        }

        if ( ! empty($filters['target_id']))
        {
            $query->where('target_id', '=', (int) $filters['target_id']);
        }

        // 3. Считаем общее количество для пагинации
        $count = (clone $query)->count_all();

        $pagination = Pagination::factory([
            'total_items'    => $count,
            'items_per_page' => 20,
        ]);

        // 4. Получение данных с сортировкой и лимитами
        $results = $query
            ->order_by('id', 'DESC')
            ->limit($pagination->items_per_page)
            ->offset($pagination->offset)
            ->find_all();

        $this->set_title(__('Links Registry'));

        $this->template->content = View::factory('erp227/links/index')
            ->set('links', $results)
            ->set('pagination', $pagination)
            ->set('filters', $filters);
    }

    /**
     * Добавление новой связи между объектами
     */
    public function action_add()
    {
        $post = $this->request->post();
        
        if (isset($post['source_id']) AND isset($post['target_id']))
        {
            $source_id = (int) $post['source_id'];
            $target_id = (int) $post['target_id'];
            
            $source = ORM::factory('Erp227_Object', $source_id);
            
            // Проверка прав на редактирование исходного объекта (создание связей)
            if ( ! $this->can('core_registry.link', $source))
            {
                $this->_json_response(['status' => 'error', 'message' => __('Access denied')], 403);
                return;
            }

            $service = Service_Erp227_Registry::instance();
            
            $link_type = isset($post['link_type']) ? (string) $post['link_type'] : 'bom';
            $quantity  = isset($post['quantity']) ? (float) $post['quantity'] : 1.0;
            $unit      = isset($post['unit']) ? (string) $post['unit'] : NULL;
            $data      = isset($post['data']) ? $post['data'] : [];

            try 
            {
                // Проверка на существование уникальной связи (source_id, target_id, link_type)
                $existing = ORM::factory('Erp227_Link')
                    ->where('source_id', '=', $source_id)
                    ->where('target_id', '=', $target_id)
                    ->where('link_type', '=', $link_type)
                    ->where('is_deleted', '=', FALSE)
                    ->find();

                if ($existing->loaded())
                {
                    $this->_json_response(['status' => 'error', 'message' => __('Link already exists')], 400);
                    return;
                }

                $link = $service->add_link($source_id, $target_id, $link_type, $quantity, $unit, $data);

                if ($link->loaded())
                {
                    $this->_json_response(['status' => 'ok', 'link_id' => $link->id]);
                }
                else
                {
                    $this->_json_response(['status' => 'error', 'message' => __('Failed to create link')], 400);
                }
            }
            catch (Exception $e)
            {
                $this->_json_response(['status' => 'error', 'message' => $e->getMessage()], 400);
            }
        }
    } 

    /**
     * Удаление связи (мягкое удаление)
     */
    public function action_delete()
    {
        $id = (int) $this->request->param('id');
        $link = ORM::factory('Erp227_Link', $id);

        if ( ! $link->loaded())
        {
            $this->_json_response(['status' => 'error', 'message' => __('Link not found')], 404);
            return;
        }

        // Проверка прав: может ли пользователь управлять связями этого объекта
        if ( ! $this->can('core_registry.link', $link->source))
        {
            $this->_json_response(['status' => 'error', 'message' => __('Access denied')], 403);
            return;
        }

        $service = Service_Erp227_Registry::instance();
        if ($service->delete_link($id))
        {
            $this->_json_response(['status' => 'ok']);
        }
        else
        {
            $this->_json_response(['status' => 'error', 'message' => __('Failed to delete link')], 400);
        }
    }

    /**
     * Обновление параметров связи (количество, порядок, доп. данные)
     */
    public function action_update()
    {
        $id = (int) $this->request->param('id');
        $link = ORM::factory('Erp227_Link', $id);
        $post = $this->request->post();

        if ( ! $link->loaded())
        {
            $this->_json_response(['status' => 'error', 'message' => __('Link not found')], 404);
            return;
        }

        if ( ! $this->can('core_registry.link', $link->source))
        {
            $this->_json_response(['status' => 'error', 'message' => __('Access denied')], 403);
            return;
        }

        $service = Service_Erp227_Registry::instance();
        
        try 
        {
            $service->update_link($id, $post);
            $this->_json_response(['status' => 'ok']);
        }
        catch (Exception $e)
        {
            $this->_json_response(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Список связей конкретного объекта (например, для вкладки BOM)
     */
    public function action_for_object()
    {
        $source_id = (int) $this->request->param('id');
        $source = ORM::factory('Erp227_Object', $source_id);

        if ( ! $source->loaded())
        {
            throw new HTTP_Exception_404('Object not found');
        }

        if ( ! $this->can('core_registry.view', $source))
        {
            throw new HTTP_Exception_403('Access denied');
        }

        $query = ORM::factory('Erp227_Link')
            ->where('source_id', '=', $source_id)
            ->where('is_deleted', '=', FALSE);

        // Пагинация для связей конкретного объекта
        $count = (clone $query)->count_all();
        $pagination = Pagination::factory([
            'total_items'    => $count,
            'items_per_page' => 50,
        ]);

        $links = $query
            ->order_by('sort_order', 'ASC')
            ->limit($pagination->items_per_page)
            ->offset($pagination->offset)
            ->find_all();

        $this->set_title(__('Links for :name', [':name' => $source->name]));
        
        $this->template->content = View::factory('erp227/links/list')
            ->set('source', $source)
            ->set('links', $links)
            ->set('pagination', $pagination);
    }

    /**
     * Массовое обновление порядка сортировки (Drag & Drop)
     */
    public function action_reorder()
    {
        $post = $this->request->post();
        $service = Service_Erp227_Registry::instance();
        
        if (isset($post['order']) AND is_array($post['order']))
        {
            foreach ($post['order'] as $index => $link_id)
            {
                $link = ORM::factory('Erp227_Link', (int) $link_id);
                if ($link->loaded() AND $this->can('core_registry.link', $link->source))
                {
                    $service->update_link($link->id, ['sort_order' => (int) $index]);
                }
            }
            
            $this->_json_response(['status' => 'ok']);
        }
    }
}