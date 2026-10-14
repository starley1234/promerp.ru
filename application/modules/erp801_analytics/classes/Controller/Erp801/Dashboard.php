<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Витрины аналитики (erp801_analytics): страницы.
 *
 * Каждая страница — один вопрос: «обзор» (что вообще происходит), «запасы»
 * (что лежит и чего не хватит), «ABC/XYZ» (что важное и насколько ровно),
 * «производство» (OEE и простои), «качество» (где теряем годное), «закупки»
 * (что висит с просрочкой), «снимки» (что было тогда), «пороги» (что считать
 * плохим).
 *
 * Страница не считает сама: сбор и формулы — в сервисах модуля, здесь разбор
 * адреса, права и вывод.
 *
 * @package    Erp801
 * @category   Controllers
 */
class Controller_Erp801_Dashboard extends Controller_Erp801_Main
{
    /**
     * Обзор: KPI всех блоков и что требует внимания.
     *
     * @return  void
     */
    public function action_index()
    {
        if ( ! $this->can('erp801.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $filters = $this->_filters();
        $summary = NULL;
        $error   = NULL;

        try
        {
            $summary = $this->_summary()->build($filters);
        }
        catch (Exception $e)
        {
            $this->_flash_error($e);
            $error = $e->getMessage();
        }

        $this->template->content = View::factory('erp801/dashboard')
            ->set('summary', $summary)
            ->set('error', $error)
            ->set('filters', $filters)
            ->set('states', $this->_state_labels())
            ->set('can_export', $this->can('erp801.export'))
            ->set('can_manage', $this->can('erp801.manage'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Запасы: оборачиваемость, покрытие, дефицит.
     *
     * @return  void
     */
    public function action_stock()
    {
        if ( ! $this->can('erp801.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $filters  = $this->_filters();
        $stock    = Service_Erp801_Stock::instance();
        $turnover = NULL;
        $deficit  = array('rows' => array(), 'items' => 0, 'out_items' => 0, 'shortage_sum' => 0.0, 'target_days' => 0);
        $error    = NULL;

        try
        {
            $turnover = $stock->turnover($filters);
            $deficit  = $stock->deficit($filters + array('limit' => 2000));
        }
        catch (Exception $e)
        {
            $this->_flash_error($e);
            $error = $e->getMessage();
        }

        $class = isset($filters['class']) ? (string) $filters['class'] : NULL;

        if ($class AND $turnover)
        {
            $turnover['rows'] = array_values(array_filter($turnover['rows'], function ($row) use ($class) {
                return $row['class'] === $class;
            }));
        }

        $this->template->content = View::factory('erp801/stock')
            ->set('turnover', $turnover)
            ->set('deficit', $deficit)
            ->set('filters', $filters)
            ->set('class_filter', $class)
            ->set('tab', $this->_query('tab', 'turnover'))
            ->set('class_labels', Service_Erp801_Csv::instance()->class_labels())
            ->set('can_export', $this->can('erp801.export'))
            ->set('sources', $this->_sources()->available())
            ->set('messages', $this->_flash_messages());
    }

    /**
     * ABC/XYZ: что двигает склад и насколько ровно.
     *
     * @return  void
     */
    public function action_abc()
    {
        if ( ! $this->can('erp801.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $filters = $this->_filters();
        $analysis = NULL;
        $error    = NULL;

        try
        {
            $analysis = Service_Erp801_Stock::instance()->abc_xyz($filters);
        }
        catch (Exception $e)
        {
            $this->_flash_error($e);
            $error = $e->getMessage();
        }

        $this->template->content = View::factory('erp801/abc')
            ->set('analysis', $analysis)
            ->set('filters', $filters)
            ->set('settings', $this->_settings()->all())
            ->set('can_export', $this->can('erp801.export'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Производство: OEE, Парето простоев, план/факт, центры.
     *
     * @return  void
     */
    public function action_production()
    {
        if ( ! $this->can('erp801.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $filters = $this->_filters();
        $panel   = NULL;
        $error   = NULL;

        try
        {
            $panel = Service_Erp801_Production::instance()->panel($filters);
        }
        catch (Exception $e)
        {
            $this->_flash_error($e);
            $error = $e->getMessage();
        }

        $this->template->content = View::factory('erp801/production')
            ->set('panel', $panel)
            ->set('filters', $filters)
            ->set('states', $this->_state_labels())
            ->set('can_export', $this->can('erp801.export'))
            ->set('sources', $this->_sources()->available())
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Качество: доля брака и Парето причин.
     *
     * @return  void
     */
    public function action_quality()
    {
        if ( ! $this->can('erp801.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $filters = $this->_filters();
        $panel   = NULL;
        $error   = NULL;

        try
        {
            $panel = Service_Erp801_Quality::instance()->panel($filters);
        }
        catch (Exception $e)
        {
            $this->_flash_error($e);
            $error = $e->getMessage();
        }

        $this->template->content = View::factory('erp801/quality')
            ->set('panel', $panel)
            ->set('filters', $filters)
            ->set('states', $this->_state_labels())
            ->set('can_export', $this->can('erp801.export'))
            ->set('sources', $this->_sources()->available())
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Закупки: открытые заказы, просрочка, ближайшие сроки.
     *
     * @return  void
     */
    public function action_purchases()
    {
        if ( ! $this->can('erp801.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $filters = $this->_filters();
        $panel   = NULL;
        $error   = NULL;

        try
        {
            $panel = Service_Erp801_Purchases::instance()->panel($filters);
        }
        catch (Exception $e)
        {
            $this->_flash_error($e);
            $error = $e->getMessage();
        }

        $this->template->content = View::factory('erp801/purchases')
            ->set('panel', $panel)
            ->set('filters', $filters)
            ->set('states', $this->_state_labels())
            ->set('can_export', $this->can('erp801.export'))
            ->set('sources', $this->_sources()->available())
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Снимки витрин: список, снятие нового, сравнение.
     *
     * @return  void
     */
    public function action_snapshots()
    {
        if ( ! $this->can('erp801.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $snapshots = Service_Erp801_Snapshots::instance();
        $code      = $this->_query('code');

        $this->template->content = View::factory('erp801/snapshots')
            ->set('rows', $snapshots->rows($code, 100, 0))
            ->set('code', $code)
            ->set('codes', Model_Erp801_Snapshot::$codes)
            ->set('filters', $this->_filters())
            ->set('can_delete', $this->can('erp801.manage'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Карточка снимка: результат и отличия от предыдущего снимка этой витрины.
     *
     * @return  void
     */
    public function action_snapshot_view()
    {
        if ( ! $this->can('erp801.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $snapshots = Service_Erp801_Snapshots::instance();
        $snapshot  = $snapshots->find($this->request->param('id'));

        if ( ! $snapshot)
        {
            throw new HTTP_Exception_404('Снимок не найден');
        }

        $previous = NULL;
        $diff     = NULL;

        $earlier = ORM::factory('Erp801_Snapshot')
            ->where('code', '=', (string) $snapshot->code)
            ->where('id', '<', (int) $snapshot->id)
            ->order_by('id', 'DESC')
            ->find();

        if ($earlier->loaded())
        {
            $previous = $earlier;

            try
            {
                $diff = $snapshots->compare((int) $earlier->id, (int) $snapshot->id);
            }
            catch (Exception $e)
            {
                $this->_flash_error($e);
            }
        }

        $this->template->content = View::factory('erp801/snapshot')
            ->set('snapshot', $snapshot)
            ->set('previous', $previous)
            ->set('diff', $diff)
            ->set('can_delete', $this->can('erp801.manage'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Снять снимок витрины (POST).
     *
     * @return  void
     */
    public function action_snapshot_save()
    {
        if ( ! $this->can('erp801.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $this->_require_post();

        $code    = (string) $this->_posted('code');
        $filters = array();

        foreach (array('from', 'to', 'warehouse_id', 'center_id') as $key)
        {
            $value = $this->_posted('filters_'.$key);

            if ($value !== NULL AND $value !== '')
            {
                $filters[$key] = $value;
            }
        }

        try
        {
            $snapshot = Service_Erp801_Snapshots::instance()->save(
                $code,
                $filters,
                $this->_current_user_id(),
                (string) $this->_posted('title'),
                (string) $this->_posted('note'),
                (string) $this->_posted('day')
            );

            $this->_flash('success', 'Снимок «'.$snapshot->title.'» сохранён');

            $this->redirect('/analytics/snapshots/view/'.(int) $snapshot->id);
        }
        catch (Exception $e)
        {
            $this->_flash_error($e, 'Не удалось снять снимок');
            $this->redirect('/analytics/snapshots');
        }
    }

    /**
     * Удалить снимок (POST).
     *
     * @return  void
     */
    public function action_snapshot_delete()
    {
        if ( ! $this->can('erp801.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $this->_require_post();

        try
        {
            Service_Erp801_Snapshots::instance()->delete($this->request->param('id'), $this->_current_user_id());
            $this->_flash('success', 'Снимок удалён');
        }
        catch (Exception $e)
        {
            $this->_flash_error($e, 'Не удалось удалить снимок');
        }

        $this->redirect('/analytics/snapshots');
    }

    /**
     * Пороги витрин: форма и сохранение (POST).
     *
     * @return  void
     */
    public function action_settings()
    {
        if ( ! $this->can('erp801.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $settings = $this->_settings();

        if (strtoupper((string) $this->request->method()) === 'POST')
        {
            $values = $this->_posted('values');
            $reset  = $this->_posted('reset');

            try
            {
                if (is_array($reset))
                {
                    foreach (array_keys($reset) as $code)
                    {
                        $settings->reset($code);
                    }
                }

                if (is_array($values))
                {
                    $settings->save($values, $this->_current_user_id());
                }

                $this->_flash('success', 'Пороги сохранены');
                $this->redirect('/analytics/settings');
            }
            catch (Exception $e)
            {
                $this->_flash_error($e, 'Не удалось сохранить пороги');
            }
        }

        $this->template->content = View::factory('erp801/settings')
            ->set('rows', $settings->rows())
            ->set('values', $settings->all())
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Выгрузка витрины в CSV.
     *
     * @return  void
     */
    public function action_export()
    {
        if ( ! $this->can('erp801.export'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $code = (string) $this->request->param('code');

        if ($code === '')
        {
            $code = (string) $this->_query('code');
        }

        try
        {
            $file = Service_Erp801_Csv::instance()->render($code, $this->_filters());
        }
        catch (Exception $e)
        {
            $this->_flash_error($e, 'Не удалось выгрузить витрину');
            $this->redirect('/analytics');

            return;
        }

        $this->auto_render = FALSE;

        $this->response
            ->headers('Content-Type', 'text/csv; charset=utf-8')
            ->headers('Content-Disposition', 'attachment; filename="'.$file['filename'].'"')
            ->body($file['content']);
    }

    /**
     * Подписи состояний для интерфейса.
     *
     * @return  array
     */
    protected function _state_labels()
    {
        return array(
            'ok'      => array('label' => 'в норме',   'class' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'),
            'warn'    => array('label' => 'внимание',  'class' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'),
            'bad'     => array('label' => 'плохо',     'class' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'),
            'unknown' => array('label' => 'нет данных','class' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'),
        );
    }
}
