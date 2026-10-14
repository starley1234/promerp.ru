<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Controller_Erp218_Api
 * 
 * Обработка AJAX запросов для управления реестром модулей:
 * обновление статусов, публикация и получение статистики.
 * 
 * @package Erp218
 * @version 1.0.0
 */
class Controller_Erp218_Api extends Controller_Base {

    /**
     * Обновление статуса модуля (alpha, beta, stable, archived)
     * POST: id, status
     */
    public function action_update_status()
    {
        $post = $this->request->post();
        $id = isset($post['id']) ? (int) $post['id'] : 0;
        $status = isset($post['status']) ? (string) $post['status'] : '';

        $module = ORM::factory('Erp218_Module', $id);

        if ( ! $module->loaded())
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Module not found'], 404);
        }

        // Проверка прав на управление статусом
        if ( ! $this->can('module_manager.manage_status', $module))
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Access denied'], 403);
        }

        // Вызов сервиса для смены статуса
        $service = Service_Erp218_Scanner::instance();
        $result = $service->update_status($id, $status);

        if ($result)
        {
            Event::fire('erp218.module.status_changed', $module);
            return $this->_json_response(['status' => 'ok', 'new_status' => $status]);
        }

        return $this->_json_response(['status' => 'error', 'message' => 'Update failed'], 400);
    }

    /**
     * Переключение флага публикации модуля
     * POST: id, is_published
     */
    public function action_toggle_published()
    {
        $post = $this->request->post();
        $id = isset($post['id']) ? (int) $post['id'] : 0;
        $is_published = isset($post['is_published']) ? (int) $post['is_published'] : 0;

        $module = ORM::factory('Erp218_Module', $id);

        if ( ! $module->loaded())
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Module not found'], 404);
        }

        if ( ! $this->can('module_manager.manage_status', $module))
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Access denied'], 403);
        }

        $module->is_published = $is_published ? 1 : 0;
        $module->save();

        Event::fire('erp218.module.updated', $module);

        return $this->_json_response(['status' => 'ok', 'is_published' => $module->is_published]);
    }

    /**
     * Получение краткой статистики для виджетов
     * GET
     */
    public function action_get_stats()
    {
        if ( ! $this->can('module_manager.view'))
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Access denied'], 403);
        }

        $config = Kohana::$config->load('erp218_module_manager');
        
        // Сбор статистики через ORM
        $stats = [
            'total'     => ORM::factory('Erp218_Module')->where('is_deleted', '=', 0)->count_all(),
            'active'    => ORM::factory('Erp218_Module')->where('is_deleted', '=', 0)->where('is_published', '=', 1)->count_all(),
            'stable'    => ORM::factory('Erp218_Module')->where('is_deleted', '=', 0)->where('status', '=', 'stable')->count_all(),
            'last_scan' => $config->get('last_scan_date', 'Never'),
        ];

        return $this->_json_response([
            'status' => 'ok',
            'stats'  => $stats
        ]);
    }

    /**
     * Запуск процесса LLM анализа для конкретного модуля
     * POST: id
     */
    public function action_run_ai_analysis()
    {
        $post = $this->request->post();
        $id = isset($post['id']) ? (int) $post['id'] : 0;

        $module = ORM::factory('Erp218_Module', $id);

        if ( ! $module->loaded())
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Module not found'], 404);
        }

        if ( ! $this->can('module_manager.ai_analyze', $module))
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Access denied'], 403);
        }

        $service = Service_Erp218_LlmProcessor::instance();
        $analysis_id = $service->run_ai_analysis($id);

        if ($analysis_id)
        {
            Event::fire('erp218.module.ai_analyzed', $module);
            return $this->_json_response(['status' => 'ok', 'analysis_id' => $analysis_id]);
        }

        return $this->_json_response(['status' => 'error', 'message' => 'AI Analysis failed to start'], 500);
    }

    /**
     * Обновление примечаний разработчика для модуля.
     * POST: id, developer_notes
     */
    public function action_update_developer_notes()
    {
        $post = $this->request->post();
        $id = isset($post['id']) ? (int) $post['id'] : 0;
        $developer_notes = isset($post['developer_notes']) ? (string) $post['developer_notes'] : '';

        $module = ORM::factory('Erp218_Module', $id);

        if ( ! $module->loaded())
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Module not found'], 404);
        }

        // Проверка прав на редактирование примечаний
        if ( ! $this->can('module_manager.edit_notes', $module))
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Access denied'], 403);
        }

        $module->developer_notes = $developer_notes;
        $module->save();

        Event::fire('erp218.module.notes_updated', $module);

        return $this->_json_response(['status' => 'ok', 'developer_notes' => $module->developer_notes]);
    }

    /**
     * Обновление основных атрибутов модуля (display_name, description, version).
     * POST: id, display_name, description, version, icon, main_page_uri
     */
    public function action_update_attributes()
    {
        $post = $this->request->post();
        $id = isset($post['id']) ? (int) $post['id'] : 0;
        $display_name = isset($post['display_name']) ? (string) $post['display_name'] : null;
        $description = isset($post['description']) ? (string) $post['description'] : null;
        $version = isset($post['version']) ? (string) $post['version'] : null;
        $icon = isset($post['icon']) ? (string) $post['icon'] : null;
        $main_page_uri = isset($post['main_page_uri']) ? (string) $post['main_page_uri'] : null;

        $module = ORM::factory('Erp218_Module', $id);

        if ( ! $module->loaded())
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Module not found'], 404);
        }

        // Проверка прав на редактирование атрибутов
        if ( ! $this->can('module_manager.edit_attributes', $module))
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Access denied'], 403);
        }

        $updated_fields = [];
        if ($display_name !== null) {
            $module->display_name = $display_name;
            $updated_fields['display_name'] = $display_name;
        }
        if ($description !== null) {
            $module->description = $description;
            $updated_fields['description'] = $description;
        }
        if ($version !== null) {
            $module->version = $version;
            $updated_fields['version'] = $version;
        }
        if ($icon !== null) {
            $module->icon = $icon;
            $updated_fields['icon'] = $icon;
        }
        if ($main_page_uri !== null) {
            $module->main_page_uri = $main_page_uri;
            $updated_fields['main_page_uri'] = $main_page_uri;
        }

        try {
            $module->save();
            Event::fire('erp218.module.attributes_updated', $module);
            return $this->_json_response(['status' => 'ok', 'updated_fields' => $updated_fields]);
        } catch (ORM_Validation_Exception $e) {
            return $this->_json_response(['status' => 'error', 'message' => $e->errors('models')], 400);
        } catch (Exception $e) {
            return $this->_json_response(['status' => 'error', 'message' => 'Update failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Запуск сканирования директории модулей.
     * POST
     */
    public function action_scan_directory()
    {
        if ( ! $this->can('module_manager.scan_directory'))
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Access denied'], 403);
        }

        $service = Service_Erp218_Scanner::instance();
        $result = $service->scan_modules_directory();

        if ($result)
        {
            Event::fire('erp218.module.directory_scanned');
            return $this->_json_response(['status' => 'ok', 'message' => 'Directory scanned successfully', 'scan_results' => $result]);
        }

        return $this->_json_response(['status' => 'error', 'message' => 'Directory scan failed'], 500);
    }

    /**
     * Получение детальной информации о модуле.
     * GET: id
     */
    public function action_get_module_details()
    {
        $id = (int) $this->request->query('id');

        $module = ORM::factory('Erp218_Module', $id);

        if ( ! $module->loaded())
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Module not found'], 404);
        }

        if ( ! $this->can('module_manager.view', $module))
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Access denied'], 403);
        }

        // Загрузка связанных данных
        $dependencies = $module->dependencies->find_all()->as_array();
        $history = $module->history->order_by('release_date', 'DESC')->find_all()->as_array();
        $analysis = $module->analysis->loaded() ? $module->analysis->as_array() : null;

        $details = $module->as_array();
        $details['dependencies'] = array_map(function($dep) { return $dep->as_array(); }, $dependencies);
        $details['history'] = array_map(function($hist) { return $hist->as_array(); }, $history);
        $details['analysis'] = $analysis;

        return $this->_json_response(['status' => 'ok', 'module' => $details]);
    }

    /**
     * Удаление модуля (мягкое удаление).
     * POST: id
     */
    public function action_delete_module()
    {
        $post = $this->request->post();
        $id = isset($post['id']) ? (int) $post['id'] : 0;

        $module = ORM::factory('Erp218_Module', $id);

        if ( ! $module->loaded())
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Module not found'], 404);
        }

        if ( ! $this->can('module_manager.delete', $module))
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Access denied'], 403);
        }

        $module->is_deleted = 1;
        $module->save();

        Event::fire('erp218.module.deleted', $module);

        return $this->_json_response(['status' => 'ok', 'message' => 'Module marked as deleted']);
    }

    /**
     * Восстановление модуля из удаленных.
     * POST: id
     */
    public function action_restore_module()
    {
        $post = $this->request->post();
        $id = isset($post['id']) ? (int) $post['id'] : 0;

        $module = ORM::factory('Erp218_Module', $id);

        if ( ! $module->loaded())
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Module not found'], 404);
        }

        if ( ! $this->can('module_manager.restore', $module))
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Access denied'], 403);
        }

        $module->is_deleted = 0;
        $module->save();

        Event::fire('erp218.module.restored', $module);

        return $this->_json_response(['status' => 'ok', 'message' => 'Module restored successfully']);
    }

    /**
     * Назначение зависимости между модулями.
     * POST: module_id, required_module_id, min_version
     */
    public function action_add_dependency()
    {
        $post = $this->request->post();
        $module_id = isset($post['module_id']) ? (int) $post['module_id'] : 0;
        $required_module_id = isset($post['required_module_id']) ? (int) $post['required_module_id'] : 0;
        $min_version = isset($post['min_version']) ? (string) $post['min_version'] : '';

        if ($module_id === $required_module_id)
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Module cannot depend on itself'], 400);
        }

        $module = ORM::factory('Erp218_Module', $module_id);
        if ( ! $module->loaded())
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Source module not found'], 404);
        }

        if ( ! $this->can('module_manager.edit_attributes', $module))
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Access denied'], 403);
        }

        $dependency = ORM::factory('Erp218_Dependency')
            ->where('module_id', '=', $module_id)
            ->where('required_module_id', '=', $required_module_id)
            ->where('is_deleted', '=', 0)
            ->find();

        if ($dependency->loaded())
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Dependency already exists'], 400);
        }

        try {
            $new_dep = ORM::factory('Erp218_Dependency');
            $new_dep->module_id = $module_id;
            $new_dep->required_module_id = $required_module_id;
            $new_dep->min_version = $min_version;
            $new_dep->created_by = Auth::instance()->get_user()->id;
            $new_dep->save();

            Event::fire('erp218.module.dependency_added', $new_dep);

            return $this->_json_response(['status' => 'ok', 'message' => 'Dependency added successfully']);
        } catch (ORM_Validation_Exception $e) {
            return $this->_json_response(['status' => 'error', 'message' => $e->errors('models')], 400);
        }
    }

    /**
     * Установка порядка сортировки модуля.
     * POST: id, sort_order
     */
    public function action_update_sort_order()
    {
        $post = $this->request->post();
        $id = isset($post['id']) ? (int) $post['id'] : 0;
        $sort_order = isset($post['sort_order']) ? (int) $post['sort_order'] : 0;

        $module = ORM::factory('Erp218_Module', $id);

        if ( ! $module->loaded())
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Module not found'], 404);
        }

        if ( ! $this->can('module_manager.edit_attributes', $module))
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Access denied'], 403);
        }

        $module->sort_order = $sort_order;
        $module->save();

        Event::fire('erp218.module.sort_updated', $module);

        return $this->_json_response(['status' => 'ok', 'sort_order' => $module->sort_order]);
    }
}