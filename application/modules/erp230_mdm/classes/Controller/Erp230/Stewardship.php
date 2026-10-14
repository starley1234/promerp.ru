<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контроллер для арбитража данных (Data Stewardship).
 * Интерфейс ручного разрешения конфликтов между источниками мастер-данных.
 * 
 * @package Erp230
 * @version 1.2.0
 */
class Controller_Erp230_Stewardship extends Controller_Erp230_Main {

    /**
     * Список объектов, требующих ручного вмешательства (is_dirty = true).
     */
    public function action_index()
    {
        // 1. Проверка прав
        if ( ! $this->can('mdm.steward.edit'))
        {
            throw new HTTP_Exception_403('Доступ к арбитражу данных запрещен');
        }

        // 2. Получение параметров пагинации и фильтрации
        $query = $this->request->query();
        $page = isset($query['page']) ? (int) $query['page'] : 1;
        $limit = 20;

        // 3. Вызов сервиса для получения "грязных" записей через GoldenRecord
        $service = Service_Erp230_GoldenRecord::instance();
        $result = $service->get_registry_list([
            'is_dirty' => TRUE,
            'page'     => $page,
            'limit'    => $limit,
            'search'   => Arr::get($query, 'search')
        ]);

        // 4. Мета-данные
        $this->set_title('Арбитраж данных: Конфликты');

        // 5. Вывод
        $this->template->content = View::factory('erp230/stewardship/conflict_list')
            ->set('objects', $result['items'])
            ->set('total', $result['total'])
            ->set('page', $page);
    }

    /**
     * Интерфейс сравнения данных и разрешения конфликта для конкретного объекта.
     */
    public function action_resolve()
    {
        // 1. Получить ID
        $id = $this->request->param('id');
        $object = ORM::factory('Erp230_Object', $id);

        if ( ! $object->loaded())
        {
            throw new HTTP_Exception_404('Объект не найден');
        }

        // 2. Проверить права
        if ( ! $this->can('mdm.steward.edit', $object))
        {
            throw new HTTP_Exception_403('Недостаточно прав для арбитража этого объекта');
        }

        // 3. Обработка POST (Принятие решения стюардом)
        $post = $this->request->post();
        if ($this->request->method() === Request::POST AND isset($post['resolve_action']))
        {
            $service = Service_Erp230_GoldenRecord::instance();
            
            if ($post['resolve_action'] === 'apply_manual' && isset($post['properties']))
            {
                // Ручное переопределение выбранных атрибутов
                $props_to_update = [];
                foreach ($post['properties'] as $key => $data)
                {
                    if (isset($data['selected']) && isset($data['value']))
                    {
                        $props_to_update[$key] = [
                            'value'    => $data['value'],
                            'uom'      => Arr::get($data, 'uom'),
                            'actor_id' => $this->_current_user->id
                        ];
                    }
                }

                if ( ! empty($props_to_update))
                {
                    $service->update_attributes_batch(
                        $object->id,
                        $props_to_update,
                        'steward', // Специальный источник для ручных правок
                        ['use_transaction' => TRUE]
                    );

                    Audit::log('MDM_STEWARD', "User {$this->_current_user->id} manually resolved conflicts for object {$object->id}", $object->id, $props_to_update);
                }
            }

            // Пересборка золотой записи и сброс флага dirty
            $service->rebuild_golden_record($object->id);

            // 4. Вызов события
            if (class_exists('Event'))
            {
                Event::fire('erp230.stewardship.resolved', $object);
            }

            $this->redirect('erp230_stewardship/index');
        }

        // 5. Подготовка данных для View (сравнение источников)
        // Получаем все версии свойств для анализа конфликтов
        $all_properties = ORM::factory('Erp230_Property')
            ->where('object_id', '=', $object->id)
            ->order_by('key', 'ASC')
            ->order_by('created_at', 'DESC')
            ->find_all();

        $this->set_title('Разрешение конфликтов: ' . $object->master_code);
        
        $this->template->content = View::factory('erp230/stewardship/resolve')
            ->set('object', $object)
            ->set('properties', $all_properties);
    }

    /**
     * Массовый сброс флага dirty для выбранных объектов.
     */
    public function action_bulk_resolve()
    {
        if ( ! $this->can('mdm.steward.edit'))
        {
            throw new HTTP_Exception_403();
        }

        $ids = $this->request->post('ids');
        if (is_array($ids) AND ! empty($ids))
        {
            $service = Service_Erp230_GoldenRecord::instance();
            foreach ($ids as $id)
            {
                // Сбрасываем флаг через пересборку золотой записи
                $service->rebuild_golden_record($id);
                Audit::log('MDM_STEWARD', "Bulk resolve for object {$id}", $id);
            }
        }

        $this->redirect('erp230_stewardship/index');
    }

    /**
     * API метод для массовой проверки целостности хешей (Baseline Integrity).
     */
    public function action_verify_integrity()
    {
        // 1. Получить ID
        $id = $this->request->param('id');
        
        // 2. Проверить права
        if ( ! $this->can('mdm.audit.integrity'))
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        // 3. Вызов сервиса
        $service = Service_Erp230_GoldenRecord::instance();
        $report = $service->verify_integrity($id);

        // 4. Логирование факта проверки
        Audit::log('MDM', "Integrity check performed for object {$id}", $id, $report);

        // 5. JSON Ответ
        $this->_json_response([
            'status' => 'ok',
            'object_id' => $id,
            'is_valid' => empty(array_filter($report, function($r) { return $r['status'] !== 'OK'; })),
            'report' => $report
        ]);
    }

    /**
     * Создание снимка (Baseline) после успешного арбитража.
     */
    public function action_create_baseline()
    {
        $id = $this->request->param('id');
        $post = $this->request->post();

        if ( ! $this->can('mdm.steward.edit'))
        {
            throw new HTTP_Exception_403();
        }

        if ($this->request->method() === Request::POST AND isset($post['version_code']))
        {
            $service = Service_Erp230_GoldenRecord::instance();
            $baseline = $service->create_snapshot(
                $id, 
                $this->_current_user->id, 
                $post['version_code']
            );

            if (class_exists('Event'))
            {
                Event::fire('erp230.stewardship.baseline_created', $baseline);
            }

            $this->redirect('erp230_objects/view/' . $id);
        }
    }

} // End Controller_Erp230_Stewardship