<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Рабочее место: один экран на вопрос «что делать прямо сейчас».
 *
 * Идея из `docs/FRONTEND_REVIEW.md` (§3.1): 34 модуля открываются каждый сам по
 * себе, и чтобы понять, что сегодня требует решения, мастер обходит шесть
 * страниц. Здесь то же самое собрано в один экран: состояние дня, что требует
 * решения, что происходит в цехе, что с заказами и чего не хватает на складе.
 *
 * Собирается из сервисов других модулей (сводка, производство, склад, качество,
 * закупки, заказы, цех) и ничего не считает сам — экран не имеет права
 * расходиться с витринами. Любой блок может быть недоступен (модуль выключен,
 * демо-база без таблиц): тогда он просто не появляется, а остальные работают —
 * демонстрация не должна падать из-за одной отсутствующей витрины.
 *
 * @package    Erp811
 * @category   Services
 */
class Service_Erp811_Workspace
{
    /** @var Service_Erp811_Workspace */
    protected static $_instance;

    /**
     * @return  Service_Erp811_Workspace
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    /**
     * Собрать данные экрана.
     *
     * @param   array   $filters   from, to (по умолчанию — последние 30 дней)
     * @return  array
     */
    public function build(array $filters = array())
    {
        $filters = $filters + array(
            'from' => date('Y-m-d', strtotime('-30 days')),
            'to'   => date('Y-m-d'),
        );

        return array(
            'period'   => array('from' => $filters['from'], 'to' => $filters['to']),
            'kpi'      => $this->_kpi($filters),
            'attention'=> $this->_attention($filters),
            'shop'     => $this->_shop(),
            'orders'   => $this->_orders(),
            'stock'    => $this->_stock($filters),
            'quality'  => $this->_quality($filters),
        );
    }

    /**
     * Состав данных стенда: сколько чего заведено. Робот и посетитель должны
     * видеть одно и то же число, поэтому источник один — витрины.
     *
     * @param   array   $filters
     * @return  array
     */
    protected function _kpi(array $filters)
    {
        $summary = $this->_service('Service_Erp801_Summary', 'build', array($filters));

        return isset($summary['kpi']) ? (array) $summary['kpi'] : array();
    }

    /**
     * Что требует решения: строки внимания из сводки.
     *
     * @param   array   $filters
     * @return  array
     */
    protected function _attention(array $filters)
    {
        $summary = $this->_service('Service_Erp801_Summary', 'build', array($filters));

        return isset($summary['attention']) ? (array) $summary['attention'] : array();
    }

    /**
     * Цех: OEE, доступность, простои и печатные задания.
     *
     * @return  array
     */
    protected function _shop()
    {
        $panel = $this->_service('Service_Erp801_Production', 'panel', array(
            array('from' => date('Y-m-d', strtotime('-30 days')), 'to' => date('Y-m-d')),
        ));

        $operations = $this->_service('Service_Erp408_Operations', 'search', array(
            array('status' => array('in_progress', 'paused')),
            8,
        ));

        $rows = array();

        if (isset($operations['rows']))
        {
            foreach ($operations['rows'] as $row)
            {
                $operation = (array) $row;

                $rows[] = array(
                    'number'    => $this->_value($operation, array('number', 'code'), '—'),
                    'order'     => $this->_value($operation, array('order_number', 'order_code'), '—'),
                    'name'      => $this->_value($operation, array('op_name', 'name'), 'Операция'),
                    'center'    => $this->_value($operation, array('center_name', 'work_center_name'), '—'),
                    'status'    => $this->_value($operation, array('status'), 'in_progress'),
                    'qty_plan'  => (float) $this->_value($operation, array('qty_planned', 'qty_plan'), 0),
                    'qty_ok'    => (float) $this->_value($operation, array('qty_ok'), 0),
                    'started'   => $this->_value($operation, array('actual_start', 'started_at'), NULL),
                );
            }
        }

        $pareto = array();

        if (isset($panel['pareto']))
        {
            foreach (array_slice((array) $panel['pareto'], 0, 4) as $row)
            {
                $row     = (array) $row;
                $pareto[] = array(
                    'label' => $this->_value($row, array('label', 'reason_label', 'reason'), 'Причина'),
                    'hours' => round(((float) $this->_value($row, array('sec', 'seconds', 'downtime_sec'), 0)) / 3600, 1),
                    'share' => (float) $this->_value($row, array('share', 'share_percent'), 0),
                );
            }
        }

        return array(
            'available'    => $panel !== NULL,
            'oee'          => isset($panel['oee']) && $panel['oee'] !== NULL ? round((float) $panel['oee'], 1) : NULL,
            'availability' => isset($panel['availability']) && $panel['availability'] !== NULL ? round((float) $panel['availability'], 1) : NULL,
            'performance'  => isset($panel['performance']) && $panel['performance'] !== NULL ? round((float) $panel['performance'], 1) : NULL,
            'quality'      => isset($panel['quality']) && $panel['quality'] !== NULL ? round((float) $panel['quality'], 1) : NULL,
            'target'       => isset($panel['target']) ? (float) $panel['target'] : NULL,
            'state'        => isset($panel['state']) ? $panel['state'] : 'neutral',
            'plan_fact'    => isset($panel['plan_fact']) ? (array) $panel['plan_fact'] : array(),
            'pareto'       => $pareto,
            'operations'   => $rows,
        );
    }

    /**
     * Заказы в работе и сводка по ним.
     *
     * @return  array
     */
    protected function _orders()
    {
        $result = $this->_service('Service_Erp402_Orders', 'search', array(
            array('status' => array('released', 'in_progress', 'planned')),
            8,
        ));

        $rows = array();

        if (isset($result['rows']))
        {
            foreach ($result['rows'] as $row)
            {
                $order = (array) $row;

                $planned = (float) $this->_value($order, array('qty'), 0);
                $done    = (float) $this->_value($order, array('qty_done'), 0);

                $rows[] = array(
                    'number'   => $this->_value($order, array('number'), '—'),
                    'item'     => $this->_value($order, array('item_name', 'name'), '—'),
                    'status'   => $this->_value($order, array('status'), 'planned'),
                    'planned'  => $planned,
                    'done'     => $done,
                    'progress' => $planned > 0 ? min(100, round($done / $planned * 100)) : 0,
                    'due'      => $this->_value($order, array('target_date', 'due_date'), NULL),
                    'uom'      => $this->_value($order, array('uom_code'), 'шт.'),
                );
            }
        }

        $stats = $this->_service('Service_Erp402_Orders', 'stats', array());

        return array(
            'rows'  => $rows,
            'stats' => is_array($stats) ? $stats : array(),
        );
    }

    /**
     * Склад: чего не хватает и на сколько дней хватает запаса.
     *
     * @param   array   $filters
     * @return  array
     */
    protected function _stock(array $filters)
    {
        $deficit = $this->_service('Service_Erp801_Stock', 'deficit', array($filters));
        $turnover = $this->_service('Service_Erp801_Stock', 'turnover', array($filters + array('limit' => 200)));

        $rows = array();

        if (isset($deficit['rows']))
        {
            foreach (array_slice((array) $deficit['rows'], 0, 10) as $row)
            {
                $row = (array) $row;

                $reasons = array();

                foreach ((array) $this->_value($row, array('reasons'), array()) as $reason)
                {
                    $labels = array(
                        'out'       => 'нет запаса',
                        'below_min' => 'ниже минимума',
                        'low_cover' => 'хватит ненадолго',
                    );

                    $reasons[] = isset($labels[$reason]) ? $labels[$reason] : $reason;
                }

                $rows[] = array(
                    'code'      => $this->_value($row, array('code'), '—'),
                    'name'      => $this->_value($row, array('name'), '—'),
                    'stock'     => (float) $this->_value($row, array('stock'), 0),
                    'min_qty'   => (float) $this->_value($row, array('min_qty'), 0),
                    'cover'     => $this->_value($row, array('cover_days'), NULL),
                    'shortage'  => (float) $this->_value($row, array('shortage'), 0),
                    'reasons'   => implode(', ', $reasons),
                );
            }
        }

        return array(
            'items'        => isset($deficit['items']) ? (int) $deficit['items'] : count($rows),
            // Сумма «докупить» не показывается: в дефицит попадают и материалы
            // (кг), и готовые детали (шт.), а складывать их нельзя — это тот же
            // дефект интерфейса, из-за которого «приход 7101.7» ничего не значит
            // (docs/FRONTEND_REVIEW.md, P1-5).
            'shortage_sum' => isset($deficit['shortage_sum']) ? (float) $deficit['shortage_sum'] : 0.0,
            'mixed_units'  => TRUE,
            'rows'         => $rows,
            'totals'       => isset($turnover['totals']) ? (array) $turnover['totals'] : array(),
        );
    }

    /**
     * Качество: доля брака и что чаще всего бракуют.
     *
     * @param   array   $filters
     * @return  array
     */
    protected function _quality(array $filters)
    {
        $panel = $this->_service('Service_Erp801_Quality', 'panel', array($filters));

        $causes = array();

        if (isset($panel['cause_pareto']))
        {
            foreach (array_slice((array) $panel['cause_pareto'], 0, 4) as $row)
            {
                $row      = (array) $row;
                $causes[] = array(
                    'label' => $this->_value($row, array('label'), 'Причина'),
                    'qty'   => (float) $this->_value($row, array('qty'), 0),
                );
            }
        }

        return array(
            'available' => $panel !== NULL,
            'share'     => isset($panel['defect_share']) && $panel['defect_share'] !== NULL ? round((float) $panel['defect_share'], 1) : NULL,
            'target'    => isset($panel['target']) ? (float) $panel['target'] : NULL,
            'state'     => isset($panel['state']) ? $panel['state'] : 'neutral',
            'summary'   => isset($panel['summary']) ? (array) $panel['summary'] : array(),
            'causes'    => $causes,
        );
    }

    /**
     * Вызвать сервис другого модуля и не упасть, если его нет.
     *
     * Экран стенда собирается из шести модулей: в демо-базе может не быть
     * таблиц части из них, а на рабочем стенде модуль может быть выключен.
     * Отсутствие витрины — это пустой блок, а не ошибка страницы.
     *
     * @param   string  $class
     * @param   string  $method
     * @param   array   $arguments
     * @return  array|null
     */
    protected function _service($class, $method, array $arguments = array())
    {
        if ( ! class_exists($class))
        {
            return NULL;
        }

        try
        {
            $service = call_user_func(array($class, 'instance'));

            if ( ! is_object($service) OR ! method_exists($service, $method))
            {
                return NULL;
            }

            $result = call_user_func_array(array($service, $method), $arguments);

            return is_array($result) ? $result : (is_object($result) ? (array) $result : NULL);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp811: рабочее место — :class:::method недоступно: :error', array(
                ':class'  => $class,
                ':method' => $method,
                ':error'  => $e->getMessage(),
            ));

            return NULL;
        }
    }

    /**
     * Первое непустое значение из массива по списку возможных ключей.
     *
     * @param   array   $row
     * @param   array   $keys
     * @param   mixed   $default
     * @return  mixed
     */
    protected function _value(array $row, array $keys, $default = NULL)
    {
        foreach ($keys as $key)
        {
            if (isset($row[$key]) AND $row[$key] !== '' AND $row[$key] !== NULL)
            {
                return $row[$key];
            }
        }

        return $default;
    }
}
