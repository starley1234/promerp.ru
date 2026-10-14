<?php defined('SYSPATH') or die('No direct script access.');

/**
 * JSON API аналитики (erp801_analytics): `/analytics/api/<action>(/<id>)`.
 *
 * Отдаёт то же, что видит человек на витринах: сводку с KPI, оборачиваемость и
 * дефицит, ABC/XYZ, OEE с Парето простоев, качество с Парето брака, закупки с
 * просрочкой, снимки с сравнением, пороги витрин и выгрузку CSV.
 *
 * Права:
 *   * чтение витрин и порогов — `erp801.view` (пороги видны всем: по ним
 *     считается «плохо/хорошо», и прятать их от цеха странно);
 *   * менять пороги и удалять снимки — `erp801.manage`;
 *   * выгрузка — `erp801.export`.
 *
 * Метод запроса — часть контракта (см. `application/config/api.php`): изменяющие
 * действия вызываются POST-ом, GET на них получает 405 от ядра до тела действия.
 *
 * @package    Erp801
 * @category   Controllers
 */
class Controller_Erp801_Api extends Controller_Base
{
    /** @var Service_Erp801_Snapshots|null */
    protected $_snapshots;

    /** @var Service_Erp801_Settings|null */
    protected $_settings;

    /** @var Service_Erp801_Sources|null */
    protected $_sources;

    // =========================================================================
    //  Чтение
    // =========================================================================

    /**
     * Сводка: KPI всех блоков, что требует внимания, состояние источников.
     */
    public function action_summary()
    {
        if ( ! $this->can('erp801.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        try
        {
            $this->_json_ok(Service_Erp801_Summary::instance()->build($this->_filters()));
        }
        catch (Exception_Erp801_Analytics $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
        }
    }

    /**
     * Состояние источников: какие модули подключены и чем они служат витринам.
     */
    public function action_sources()
    {
        if ( ! $this->can('erp801.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $this->_json_ok(array('sources' => $this->_sources()->available()));
    }

    /**
     * Запасы: оборачиваемость и покрытие по позициям.
     */
    public function action_stock()
    {
        if ( ! $this->can('erp801.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        try
        {
            $turnover = Service_Erp801_Stock::instance()->turnover($this->_filters());

            if ($this->_query('class') !== NULL AND $this->_query('class') !== '')
            {
                $class            = (string) $this->_query('class');
                $turnover['rows'] = array_values(array_filter($turnover['rows'], function ($row) use ($class) {
                    return $row['class'] === $class;
                }));
            }

            $this->_json_ok($turnover);
        }
        catch (Exception_Erp801_Analytics $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
        }
    }

    /**
     * Дефицит: чего не хватит до цели покрытия и что ниже минимума.
     */
    public function action_deficit()
    {
        if ( ! $this->can('erp801.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        try
        {
            $this->_json_ok(Service_Erp801_Stock::instance()->deficit($this->_filters()));
        }
        catch (Exception_Erp801_Analytics $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
        }
    }

    /**
     * ABC/XYZ по расходу.
     */
    public function action_abc()
    {
        if ( ! $this->can('erp801.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        try
        {
            $this->_json_ok(Service_Erp801_Stock::instance()->abc_xyz($this->_filters()));
        }
        catch (Exception_Erp801_Analytics $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
        }
    }

    /**
     * Производство: OEE, Парето простоев, план/факт, центры.
     */
    public function action_production()
    {
        if ( ! $this->can('erp801.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        try
        {
            $this->_json_ok(Service_Erp801_Production::instance()->panel($this->_filters()));
        }
        catch (Exception_Erp801_Analytics $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
        }
    }

    /**
     * Качество: доля брака, Парето причин и кодов, карантин.
     */
    public function action_quality()
    {
        if ( ! $this->can('erp801.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        try
        {
            $this->_json_ok(Service_Erp801_Quality::instance()->panel($this->_filters()));
        }
        catch (Exception_Erp801_Analytics $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
        }
    }

    /**
     * Закупки: состояние заказов, просрочка, ближайшие сроки.
     */
    public function action_purchases()
    {
        if ( ! $this->can('erp801.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        try
        {
            $this->_json_ok(Service_Erp801_Purchases::instance()->panel($this->_filters()));
        }
        catch (Exception_Erp801_Analytics $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
        }
    }

    /**
     * Снимки витрин: список.
     */
    public function action_snapshots()
    {
        if ( ! $this->can('erp801.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $code = $this->_query('code');

        $this->_json_ok(array(
            'rows'  => $this->_snapshots()->rows($code, (int) $this->_query('limit', 50), (int) $this->_query('offset', 0)),
            'codes' => Model_Erp801_Snapshot::$codes,
        ));
    }

    /**
     * Снимок целиком: результат и (по флагу) отличия от предыдущего снимка.
     */
    public function action_snapshot()
    {
        if ( ! $this->can('erp801.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $snapshot = $this->_snapshots()->find($this->_query('id', $this->request->param('id')));

        if ( ! $snapshot)
        {
            $this->_json_fail('Снимок не найден', 'not_found', 404);
            return;
        }

        $data = array('snapshot' => $snapshot->as_row(TRUE));

        if ($this->_query('compare') !== NULL AND (string) $this->_query('compare') !== '' AND (string) $this->_query('compare') !== '0')
        {
            $earlier = ORM::factory('Erp801_Snapshot')
                ->where('code', '=', (string) $snapshot->code)
                ->where('id', '<', (int) $snapshot->id)
                ->order_by('id', 'DESC')
                ->find();

            if ($earlier->loaded())
            {
                try
                {
                    $data['compare'] = $this->_snapshots()->compare((int) $earlier->id, (int) $snapshot->id);
                }
                catch (Exception_Erp801_Analytics $e)
                {
                    $data['compare'] = NULL;
                    $data['compare_error'] = array('message' => $e->getMessage(), 'reason' => $e->reason());
                }
            }
        }

        $this->_json_ok($data);
    }

    /**
     * Пороги витрин: описание и значения.
     */
    public function action_settings()
    {
        if ( ! $this->can('erp801.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $this->_json_ok(array(
            'rows'   => $this->_settings()->rows(),
            'values' => $this->_settings()->all(),
        ));
    }

    /**
     * Выгрузка витрины в CSV (текстом — агент сам решит, куда его положить).
     */
    public function action_export()
    {
        if ( ! $this->can('erp801.export'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $code = (string) $this->_query('code', 'summary');

        try
        {
            $file = Service_Erp801_Csv::instance()->render($code, $this->_filters());

            $this->_json_ok(array(
                'code'     => $code,
                'title'    => $file['title'],
                'filename' => $file['filename'],
                'rows'     => $file['rows'],
                'content'  => $file['content'],
            ));
        }
        catch (Exception_Erp801_Analytics $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
        }
    }

    // =========================================================================
    //  Изменения (POST)
    // =========================================================================

    /**
     * Снять снимок витрины.
     */
    public function action_save_snapshot()
    {
        if ( ! $this->can('erp801.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        try
        {
            $snapshot = $this->_snapshots()->save(
                (string) $this->_posted('code', $this->_query('code')),
                $this->_filters(),
                $this->_current_user_id(),
                $this->_posted('title'),
                $this->_posted('note'),
                $this->_posted('day')
            );

            $this->_json_ok(array('snapshot' => $snapshot->as_row()));
        }
        catch (Exception_Erp801_Analytics $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
        }
    }

    /**
     * Удалить снимок.
     */
    public function action_delete_snapshot()
    {
        if ( ! $this->can('erp801.manage'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        try
        {
            $this->_snapshots()->delete($this->_posted('id', $this->request->param('id')), $this->_current_user_id());

            $this->_json_ok(array('deleted' => TRUE));
        }
        catch (Exception_Erp801_Analytics $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
        }
    }

    /**
     * Сохранить пороги витрин.
     */
    public function action_save_settings()
    {
        if ( ! $this->can('erp801.manage'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $values = $this->_posted('values', array());

        if ( ! is_array($values))
        {
            $this->_json_fail('Пороги передаются объектом «values»', 'validation', 400);
            return;
        }

        try
        {
            $saved = $this->_settings()->save($values, $this->_current_user_id());

            $this->_json_ok(array('values' => $saved));
        }
        catch (Exception_Erp801_Analytics $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
        }
    }

    // =========================================================================
    //  Служебное
    // =========================================================================

    /**
     * Фильтры витрины из строки запроса.
     *
     * @return  array
     */
    protected function _filters()
    {
        $filters = array();

        foreach (array('from', 'to', 'warehouse_id', 'center_id', 'limit', 'due_days') as $key)
        {
            $value = $this->_query($key);

            if ($value !== NULL AND $value !== '')
            {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }

    /**
     * Значение из тела POST-запроса или из строки запроса.
     *
     * @param   string  $key
     * @param   mixed   $default
     * @return  mixed
     */
    protected function _posted($key, $default = NULL)
    {
        $post = $this->request->post();

        if (is_array($post) AND array_key_exists($key, $post) AND $post[$key] !== NULL AND $post[$key] !== '')
        {
            return $post[$key];
        }

        $value = $this->_query($key);

        return ($value === NULL OR $value === '') ? $default : $value;
    }

    /**
     * @return  int|null
     */
    protected function _current_user_id()
    {
        try
        {
            $user = Auth::instance()->get_user();

            return ($user AND $user->loaded()) ? (int) $user->id : NULL;
        }
        catch (Exception $e)
        {
            return NULL;
        }
    }

    /**
     * @return  Service_Erp801_Snapshots
     */
    protected function _snapshots()
    {
        if ($this->_snapshots === NULL)
        {
            $this->_snapshots = Service_Erp801_Snapshots::instance();
        }

        return $this->_snapshots;
    }

    /**
     * @return  Service_Erp801_Settings
     */
    protected function _settings()
    {
        if ($this->_settings === NULL)
        {
            $this->_settings = Service_Erp801_Settings::instance();
        }

        return $this->_settings;
    }

    /**
     * @return  Service_Erp801_Sources
     */
    protected function _sources()
    {
        if ($this->_sources === NULL)
        {
            $this->_sources = Service_Erp801_Sources::instance();
        }

        return $this->_sources;
    }
}
