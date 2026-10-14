<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Controller_Erp218_Index — Основной интерфейс реестра модулей.
 * 
 * Реализует просмотр списка, фильтрацию по статусам и детальный просмотр 
 * метаданных программных компонентов системы.
 * 
 * @package Erp218
 * @version 1.0.2
 */
class Controller_Erp218_Index extends Controller_Erp218_Main {

    /**
     * Выполняется перед вызовом action.
     */
    public function before()
    {
        parent::before();
        // Добавляем стили и скрипты Select2
        $this->template->styles[] = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css';
        $this->template->scripts[] = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js';
    }

    /**
     * Список всех модулей с фильтрацией и сортировкой.
     */
    public function action_index()
    {
        // 1. Проверка прав доступа
        if ( ! $this->can('module_manager.view'))
        {
            throw new HTTP_Exception_403('Access Denied');
        }

        // 2. Получение параметров фильтрации и сортировки
        $query = $this->request->query();
        $status = isset($query['status']) ? (string) $query['status'] : NULL;
        $search = isset($query['search']) ? (string) $query['search'] : NULL;
        $visibility = isset($query['visibility']) ? (string) $query['visibility'] : 'all';
        
        // Параметры сортировки
        $sort_by = isset($query['sort']) ? (string) $query['sort'] : 'sort_order';
        $sort_dir = (isset($query['dir']) AND strtolower($query['dir']) === 'desc') ? 'DESC' : 'ASC';

        // 3. Вызов сервиса для получения данных
        $service = Service_Erp218_ModuleManager::instance();
        
        $count = $this->count_modules($status, $search, $visibility);
        $pagination = Pagination::factory([
            'total_items' => $count,
            'items_per_page' => 20,
        ]);

        // Получаем модули с учетом сортировки и видимости
        $modules = $this->get_modules(
            $status, 
            $search, 
            (int) $pagination->items_per_page, 
            (int) $pagination->offset,
            $sort_by,
            $sort_dir,
            $visibility
        );
        

        // 4. Установка мета-данных и вывод
        $this->set_title(__('Управление программными модулями'));
        $this->template->content = View::factory('erp218/index/list')
            ->set('modules', $modules)
            ->set('pagination', $pagination)
            ->set('filters', [
                'status'     => $status,
                'search'     => $search,
                'sort'       => $sort_by,
                'dir'        => $sort_dir,
                'visibility' => $visibility
            ]);
    }

    /**
     * Возвращает список модулей с возможностью фильтрации, пагинации и сортировки.
     * Перенесено из Service_Erp218_ModuleManager с добавлением фильтра видимости.
     */
    public function get_modules($status = NULL, $search = NULL, $limit = NULL, $offset = NULL, $sort_by = 'sort_order', $sort_dir = 'ASC', $visibility = 'all')
    {
        $query = ORM::factory('Erp218_Module')
            ->where('is_deleted', '=', 0);

        if ($status !== NULL)
        {
            $query->where('status', '=', $status);
        }

        if ($visibility === 'published')
        {
            $query->where('is_published', '=', 1);
        }
        elseif ($visibility === 'hidden')
        {
            $query->where('is_published', '=', 0);
        }

        if ($search !== NULL)
        {
            $query->and_where_open()
                ->where('system_name', 'LIKE', '%' . $search . '%')
                ->or_where('display_name', 'LIKE', '%' . $search . '%')
                ->and_where_close();
        }

        if ($limit !== NULL)
        {
            $query->limit($limit);
        }

        if ($offset !== NULL)
        {
            $query->offset($offset);
        }

        $query->order_by($sort_by, $sort_dir);

        return $query->find_all();
    }

    /**
     * Возвращает общее количество модулей с учетом фильтров.
     */
    public function count_modules($status = NULL, $search = NULL, $visibility = 'all')
    {
        $query = ORM::factory('Erp218_Module')
            ->where('is_deleted', '=', 0);

        if ($status !== NULL)
        {
            $query->where('status', '=', $status);
        }

        if ($visibility === 'published')
        {
            $query->where('is_published', '=', 1);
        }
        elseif ($visibility === 'hidden')
        {
            $query->where('is_published', '=', 0);
        }

        if ($search !== NULL)
        {
            $query->and_where_open()
                ->where('system_name', 'LIKE', '%' . $search . '%')
                ->or_where('display_name', 'LIKE', '%' . $search . '%')
                ->and_where_close();
        }

        return $query->count_all();
    }

    /**
     * Детальная карточка модуля.
     */
    public function action_details()
    {
        // 1. Получение ID
        $id = (int) $this->request->param('id');
        
        // 2. Загрузка модели
        $module = ORM::factory('Erp218_Module', $id);

        if ( ! $module->loaded() OR $module->is_deleted)
        {
            throw new HTTP_Exception_404('Module not found');
        }

        // 3. Проверка прав
        if ( ! $this->can('module_manager.view', $module))
        {
            throw new HTTP_Exception_403('Access Denied');
        }

        // 4. Установка мета-данных
        $this->set_title(__('Модуль: :name', [':name' => $module->display_name]));
        $this->set_description($module->description);

        // 5. Вывод представления
        $this->template->content = View::factory('erp218/index/detail')
            ->set('module', $module)
            ->set('dependencies', $module->dependencies->where('is_deleted', '=', 0)->find_all())
            ->set('history', $module->history->where('is_deleted', '=', 0)->order_by('created_at', 'DESC')->find_all())
            ->set('analysis', $module->analysis);
    }

    /**
     * Обновление метаданных модуля (POST).
     */
    public function action_edit()
    {
        $id = (int) $this->request->param('id');
        $module = ORM::factory('Erp218_Module', $id);

        if ( ! $module->loaded())
        {
            throw new HTTP_Exception_404();
        }

        // Проверка прав на редактирование
        if ( ! $this->can('module_manager.edit_metadata', $module))
        {
            throw new HTTP_Exception_403('Access Denied');
        }

        if ($this->request->method() === Request::POST)
        {
            $post = $this->request->post();
            
            // Работа через сервис для соблюдения бизнес-логики и транзакционности
            $service = Service_Erp218_ModuleManager::instance();
            
            // Подготовка данных (безопасное извлечение)
            $data = [
                'display_name'    => isset($post['display_name']) ? $post['display_name'] : $module->display_name,
                'description'     => isset($post['description']) ? $post['description'] : $module->description,
                'developer_notes' => isset($post['developer_notes']) ? $post['developer_notes'] : $module->developer_notes,
                'sort_order'      => isset($post['sort_order']) ? (int) $post['sort_order'] : $module->sort_order,
            ];

            $service->update_module_metadata($module, $data);

            // 5. Вызов события
            Event::fire('erp218.module.updated', $module);

            $this->_json_response(['status' => 'ok', 'message' => __('Данные обновлены')]);
            return;
        }

        $this->template->content = View::factory('erp218/index/edit')
            ->set('module', $module);
    }

    /**
     * Смена статуса модуля.
     */
    public function action_update_status()
    {
        $id = (int) $this->request->param('id');
        $module = ORM::factory('Erp218_Module', $id);

        if ( ! $module->loaded())
        {
            throw new HTTP_Exception_404();
        }

        if ( ! $this->can('module_manager.manage_status', $module))
        {
            throw new HTTP_Exception_403();
        }

        if ($this->request->method() === Request::POST)
        {
            $post = $this->request->post();
            if (isset($post['status']))
            {
                $service = Service_Erp218_ModuleManager::instance();
                $service->update_status($module->id, (string) $post['status']);

                Event::fire('erp218.module.status_changed', $module);
                $this->_json_response(['status' => 'ok', 'message' => __('Статус обновлен')]);
                return;
            }
        }
        throw new HTTP_Exception_400('Invalid request');
    }

    /**
     * Добавление новой записи в историю версий модуля.
     */
    public function action_add_version_history()
    {
        $id = (int) $this->request->param('id');
        $module = ORM::factory('Erp218_Module', $id);

        if ( ! $module->loaded())
        {
            throw new HTTP_Exception_404();
        }

        if ( ! $this->can('module_manager.add_version_history', $module))
        {
            throw new HTTP_Exception_403();
        }

        if ($this->request->method() === Request::POST)
        {
            $post = $this->request->post();
            
            $version = Arr::get($post, 'version');
            $change_log = Arr::get($post, 'change_log');
            $release_date = Arr::get($post, 'release_date');

            if (empty($version) || empty($change_log))
            {
                $this->_json_response(['status' => 'error', 'message' => __('Необходимо указать версию и описание изменений.')], 400);
                return;
            }

            try
            {
                $service = Service_Erp218_ModuleManager::instance();
                $service->add_version_history($module->id, $version, $change_log, $this->_current_user->id, $release_date);
                $this->_json_response(['status' => 'ok', 'message' => __('Запись в историю версий добавлена.')]);
            }
            catch (Exception $e)
            {
                $this->_json_response(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
            return;
        }

        $this->template->content = View::factory('erp218/index/add_version_history')
            ->set('module', $module);
    }

    /**
     * Удаление модуля (мягкое удаление).
     */
    public function action_delete()
    {
        $id = (int) $this->request->param('id');
        $module = ORM::factory('Erp218_Module', $id);

        if ( ! $module->loaded())
        {
            throw new HTTP_Exception_404();
        }

        if ( ! $this->can('module_manager.delete', $module))
        {
            throw new HTTP_Exception_403();
        }

        if ($this->request->method() === Request::POST)
        {
            try
            {
                $service = Service_Erp218_ModuleManager::instance();
                $service->delete_module($module->id);
                $this->_json_response(['status' => 'ok', 'message' => __('Модуль успешно удален.')]);
            }
            catch (Exception $e)
            {
                $this->_json_response(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
            return;
        }
        
        $this->template->content = View::factory('erp218/index/delete_confirm')
            ->set('module', $module);
    }

    /**
     * Восстановление модуля после мягкого удаления.
     */
    public function action_restore()
    {
        $id = (int) $this->request->param('id');
        $module = ORM::factory('Erp218_Module', $id);

        if ( ! $module->loaded())
        {
            throw new HTTP_Exception_404();
        }

        if ( ! $this->can('module_manager.restore', $module))
        {
            throw new HTTP_Exception_403();
        }

        if ($this->request->method() === Request::POST)
        {
            try
            {
                $service = Service_Erp218_ModuleManager::instance();
                $service->restore_module($module->id);
                $this->_json_response(['status' => 'ok', 'message' => __('Модуль успешно восстановлен.')]);
            }
            catch (Exception $e)
            {
                $this->_json_response(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
            return;
        }
        throw new HTTP_Exception_400('Invalid request');
    }

    /**
     * Запуск LLM анализа для модуля.
     */
    public function action_analyze_llm()
    {
        $id = (int) $this->request->param('id');
        $module = ORM::factory('Erp218_Module', $id);

        if ( ! $module->loaded())
        {
            throw new HTTP_Exception_404();
        }

        if ( ! $this->can('module_manager.analyze_llm', $module))
        {
            throw new HTTP_Exception_403();
        }

        if ($this->request->method() === Request::POST)
        {
            try
            {
                $content_to_analyze = '';
                if ($module->analysis->loaded() && !empty($module->analysis->raw_content)) {
                    $content_to_analyze = $module->analysis->raw_content;
                } elseif (file_exists($module->path . DIRECTORY_SEPARATOR . 'README.md')) {
                    $content_to_analyze = file_get_contents($module->path . DIRECTORY_SEPARATOR . 'README.md');
                } else {
                    throw new Exception('No content available for LLM analysis.');
                }

                $llm_processor = Service_Erp218_LlmProcessor::instance();
                $llm_processor->analyze_module_content($module->id, $content_to_analyze, $this->_current_user->id);
                $this->_json_response(['status' => 'ok', 'message' => __('LLM анализ запущен/обновлен.')]);
            }
            catch (Exception $e)
            {
                $this->_json_response(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
            return;
        }
        throw new HTTP_Exception_400('Invalid request');
    }

    /**
     * Сброс LLM анализа для модуля.
     */
    public function action_reset_llm_analysis()
    {
        $id = (int) $this->request->param('id');
        $module = ORM::factory('Erp218_Module', $id);

        if ( ! $module->loaded())
        {
            throw new HTTP_Exception_404();
        }

        if ( ! $this->can('module_manager.reset_llm_analysis', $module))
        {
            throw new HTTP_Exception_403();
        }

        if ($this->request->method() === Request::POST)
        {
            try
            {
                $llm_processor = Service_Erp218_LlmProcessor::instance();
                $llm_processor->reset_analysis($module->id);
                $this->_json_response(['status' => 'ok', 'message' => __('LLM анализ сброшен.')]);
            }
            catch (Exception $e)
            {
                $this->_json_response(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
            return;
        }
        throw new HTTP_Exception_400('Invalid request');
    }

    /**
     * Отображение страницы управления зависимостями.
     */
    public function action_dependencies()
    {
        if ( ! $this->can('module_manager.view'))
        {
            throw new HTTP_Exception_403('Access Denied');
        }

        $all_modules = ORM::factory('Erp218_Module')
            ->where('is_deleted', '=', 0)
            ->order_by('sort_order', 'ASC')
            ->find_all();
            
        $dependencies = ORM::factory('Erp218_Dependency')
            ->where('is_deleted', '=', 0)
            ->find_all();

        $this->set_title(__('Управление зависимостями модулей'));
        $this->template->content = View::factory('erp218/index/dependencies')
            ->set('all_modules', $all_modules)
            ->set('dependencies', $dependencies);
    }

    /**
     * Добавление зависимости между модулями (POST).
     */
    public function action_add_dependency()
    {
        if ( ! $this->can('module_manager.manage_dependencies'))
        {
            throw new HTTP_Exception_403('Access Denied');
        }

        if ($this->request->method() === Request::POST)
        {
            $post = $this->request->post();
            
            try
            {
                $service = Service_Erp218_Scanner::instance();
                $service->add_dependency($post, $this->_current_user->id);
                $this->_json_response(['status' => 'ok', 'message' => __('Зависимость успешно добавлена.')]);
            }
            catch (Validation_Exception $e)
            {
                $this->_json_response(['status' => 'error', 'message' => $e->errors('validation')], 400);
            }
            catch (Exception $e)
            {
                $this->_json_response(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
            return;
        }
        throw new HTTP_Exception_400('Invalid request');
    }

    /**
     * Удаление зависимости между модулями (POST).
     */
    public function action_delete_dependency()
    {
        if ( ! $this->can('module_manager.manage_dependencies'))
        {
            throw new HTTP_Exception_403('Access Denied');
        }

        if ($this->request->method() === Request::POST)
        {
            $dependency_id = (int) $this->request->post('dependency_id');
            $dependency = ORM::factory('Erp218_Dependency', $dependency_id);

            if ( ! $dependency->loaded())
            {
                $this->_json_response(['status' => 'error', 'message' => __('Зависимость не найдена.')], 404);
                return;
            }

            try
            {
                $dependency->delete();
                Event::fire('erp218.dependency.deleted', $dependency);
                $this->_json_response(['status' => 'ok', 'message' => __('Зависимость успешно удалена.')]);
            }
            catch (Exception $e)
            {
                $this->_json_response(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
            return;
        }
        throw new HTTP_Exception_400('Invalid request');
    }

    /**
     * Отображение страницы настроек модуля.
     */
    public function action_settings()
    {
        if ( ! $this->can('module_manager.manage_settings'))
        {
            throw new HTTP_Exception_403('Access Denied');
        }

        $config = Kohana::$config->load('erp218_module_manager');

        $this->set_title(__('Настройки реестра модулей'));
        $this->template->content = View::factory('erp218/index/settings')
            ->set('config', $config);
    }

    /**
     * Сохранение настроек модуля (POST).
     */
    public function action_save_settings()
    {
        if ( ! $this->can('module_manager.manage_settings'))
        {
            throw new HTTP_Exception_403('Access Denied');
        }

        if ($this->request->method() === Request::POST)
        {
            $post = $this->request->post();
            $this->_json_response(['status' => 'ok', 'message' => __('Настройки успешно сохранены.')]);
            return;
        }
        throw new HTTP_Exception_400('Invalid request');
    }
}