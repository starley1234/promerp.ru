<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контроллер управления справочником единиц измерения.
 * Реализует CRUD операции для erp_227_units с поддержкой пагинации и фильтрации.
 *
 * @package Erp227
 * @version 1.3.0
 */
class Controller_Erp227_Units extends Controller_Erp227_Main {

    /**
     * Список всех единиц измерения с фильтрацией и пагинацией
     */
    public function action_index()
    {
        // 1. Проверка прав
        if ( ! $this->can('core_registry.units.view'))
        {
            throw new HTTP_Exception_403('Access Denied');
        }

        // 2. Инициализация ORM
        $units = ORM::factory('Erp227_Unit');

        // 3. Фильтрация
        $filter = [
            'query'      => $this->request->query('query'),
            'is_decimal' => $this->request->query('is_decimal'),
        ];

        if ( ! empty($filter['query']))
        {
            $q = '%' . $filter['query'] . '%';
            $units->where_open()
                ->where('unit', 'ILIKE', $q)
                ->or_where('name', 'ILIKE', $q)
            ->where_close();
        }

        if ($filter['is_decimal'] !== NULL && $filter['is_decimal'] !== '')
        {
            $units->where('is_decimal', '=', (bool) $filter['is_decimal']);
        }

        // 4. Пагинация
        $total_count = clone $units;
        $count = $total_count->count_all();

        $pagination = Pagination::factory([
            'total_items'    => $count,
            'items_per_page' => 20,
        ]);

        $units = $units
            ->limit($pagination->items_per_page)
            ->offset($pagination->offset)
            ->order_by('unit', 'ASC')
            ->find_all();

        // 5. Вывод
        $this->set_title('Справочник единиц измерения');
        $this->template->content = View::factory('erp227/units/index')
            ->set('units', $units)
            ->set('pagination', $pagination)
            ->set('filter', $filter);
    }

    /**
     * Создание или редактирование единицы измерения
     */
    public function action_edit()
    {
        // 1. Получение ID (код единицы, например 'pcs')
        $id = $this->request->param('id');
        $unit = ORM::factory('Erp227_Unit', $id);

        // 2. Проверка прав
        $ability = $unit->loaded() ? 'core_registry.units.edit' : 'core_registry.units.create';
        if ( ! $this->can($ability, $unit))
        {
            throw new HTTP_Exception_403('Access Denied');
        }

        // 3. Обработка POST
        $post = $this->request->post();
        if ( ! empty($post))
        {
            // Подготовка данных согласно структуре таблицы erp_227_units
            $data = [
                'unit'       => isset($post['unit']) ? (string) $post['unit'] : $unit->unit,
                'name'       => isset($post['name']) ? (string) $post['name'] : $unit->name,
                'is_decimal' => isset($post['is_decimal']) ? (bool) $post['is_decimal'] : FALSE,
            ];
            
            print_r ($data);
            print_r ($post);
            
            $unit->values($data);
            $unit->save();

            // 4. Вызов события
            Event::fire('erp227.unit.saved', $unit);

            // Редирект или JSON ответ
            if ($this->request->is_ajax())
            {
                return $this->_json_response(['status' => 'ok', 'unit' => $unit->unit]);
            }
            
            //$this->redirect('/erp227_units');
        }

        // 5. Вывод формы
        $this->set_title($unit->loaded() ? 'Редактирование единицы' : 'Новая единица измерения');
        $this->template->content = View::factory('erp227/units/edit')
            ->set('unit', $unit);
    }

    /**
     * Удаление единицы измерения
     */
    public function action_delete()
    {
        // 1. Получение ID
        $id = $this->request->param('id');
        $unit = ORM::factory('Erp227_Unit', $id);

        if ( ! $unit->loaded())
        {
            throw new HTTP_Exception_404('Unit not found');
        }

        // 2. Проверка прав
        if ( ! $this->can('core_registry.units.delete', $unit))
        {
            throw new HTTP_Exception_403('Access Denied');
        }

        // 3. Проверка возможности удаления (использование в erp_227_object_links)
        $links_count = ORM::factory('Erp227_Object_Link')
            ->where('unit', '=', $unit->unit)
            ->where('is_deleted', '=', FALSE)
            ->count_all();

        if ($links_count > 0)
        {
             if ($this->request->is_ajax())
             {
                 return $this->_json_response(['status' => 'error', 'message' => 'Нельзя удалить единицу, которая используется в связях объектов'], 400);
             }
             throw new Kohana_Exception('Unit is in use');
        }

        $unit_code = $unit->unit;
        $unit->delete();

        // 4. Вызов события
        Event::fire('erp227.unit.deleted', ['unit' => $unit_code]);

        if ($this->request->is_ajax())
        {
            return $this->_json_response(['status' => 'ok']);
        }

        $this->redirect('erp227_units');
    }
}