<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Controller_Erp218_Widget
 * 
 * HMVC контроллер для отрисовки виджетов статистики реестра модулей.
 * Реализует принцип "Просто, Единообразно, Хорошо".
 * 
 * @package Erp218
 * @version 1.0.0
 */
class Controller_Erp218_Widget extends Controller_Base {

    /**
     * Виджет сводной статистики для дашборда.
     * Используется через Request::factory('erp218_widget/summary')->execute();
     */
    public function action_summary()
    {
        // 1. Проверка прав доступа (чтение реестра)
        if ( ! $this->can('module_manager.view'))
        {
            $this->response->body('');
            return;
        }

        // 2. Доступ к сервису
        $service = Service_Erp218_Scanner::instance();

        // 3. Получение данных через сервис (бизнес-логика инкапсулирована)
        // В данном случае получаем агрегированные данные по модулям
        $total = (int) ORM::factory('Erp218_Module')->where('is_deleted', '=', 0)->count_all();
        $stable = (int) ORM::factory('Erp218_Module')
            ->where('status', '=', 'stable')
            ->where('is_deleted', '=', 0)
            ->count_all();

        $stats = [
            'total'          => $total,
            'active_count'   => (int) ORM::factory('Erp218_Module')->where('is_published', '=', 1)->where('is_deleted', '=', 0)->count_all(),
            'stable_count'   => $stable,
            'stable_percent' => $total > 0 ? (int) round($stable / $total * 100) : 0,
            'dev_count'      => (int) ORM::factory('Erp218_Module')
                ->where('status', 'IN', ['alpha', 'beta'])
                ->where('is_deleted', '=', 0)
                ->count_all(),
        ];

        $modules = ORM::factory('Erp218_Module')
            ->where('is_deleted', '=', 0)
            ->order_by('updated_at', 'DESC')
            ->limit(5)
            ->find_all();

        $last_scan = $modules->count() ? $modules[0]->updated_at : NULL;

        // 4. Вывод представления
        $this->response->body(View::factory('erp218/widgets/summary')
            ->set('stats', $stats)
            ->set('modules', $modules)
            ->set('recent_modules', $modules)
            ->set('last_scan', $last_scan)
            ->render());
    }

    /**
     * Виджет статуса LLM анализа.
     */
    public function action_llm_status()
    {
        if ( ! $this->can('module_manager.view'))
        {
            $this->response->body('');
            return;
        }

        $stats = [
            'pending'   => (int) ORM::factory('Erp218_Llm')->where('analysis_status', '=', 'pending')->count_all(),
            'completed' => (int) ORM::factory('Erp218_Llm')->where('analysis_status', '=', 'completed')->count_all(),
            'failed'    => (int) ORM::factory('Erp218_Llm')->where('analysis_status', '=', 'failed')->count_all(),
        ];

        $this->response->body(View::factory('erp218/widgets/llm_status')
            ->set('stats', $stats)
            ->render());
    }

    /**
     * API метод для получения данных виджета в формате JSON (например, для динамического обновления)
     */
    public function action_get_json_stats()
    {
        if ( ! $this->can('module_manager.view'))
        {
            $this->_json_response(['error' => 'Access Denied'], 403);
            return;
        }

        $total = (int) ORM::factory('Erp218_Module')->where('is_deleted', '=', 0)->count_all();
        $published = (int) ORM::factory('Erp218_Module')->where('is_published', '=', 1)->where('is_deleted', '=', 0)->count_all();

        $this->_json_response([
            'status' => 'ok',
            'data'   => [
                'total' => $total,
                'published' => $published,
                'percent' => $total > 0 ? round(($published / $total) * 100, 2) : 0
            ]
        ]);
    }
}