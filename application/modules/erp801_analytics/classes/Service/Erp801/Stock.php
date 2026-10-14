<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Витрина запасов (erp801_analytics).
 *
 * Отвечает на вопросы, которые склад сам себе не задаёт: сколько дней живёт
 * запас, что оборачивается быстро, что лежит мёртвым грузом, чего не хватит до
 * следующей поставки и как разложить позиции по ABC/XYZ.
 *
 * Данные берутся у владельца — `Service_Erp401_Stock` (`flow()` по движениям и
 * `balances()` по остаткам, плюс названия позиций из реестра). Аналитика ничего
 * не досчитывает по чужим таблицам.
 *
 * Честность формул:
 *
 *  - средний остаток за период не считается: истории остатков в системе нет, а
 *    «средний» по двум точкам — это не средний, а фантазия. Поэтому
 *    оборачиваемость считается как «расход за период / остаток сейчас»
 *    (`turns_period`) и всегда сопровождается днями покрытия (`cover_days`),
 *    которые не зависят от этого допущения;
 *  - ABC считается **по количеству**, а не по деньгам: цены живут в закупках
 *    (erp403), и подтягивать их в аналитику значило бы завести вторую правду о
 *    цене. Когда цены появятся в сервисе закупок, сюда добавятся `abc_amount`
 *    без изменения структуры витрины;
 *  - XYZ требует минимум двух месяцев наблюдений: по одному месяцу разброс не
 *    определён, и класс не выставляется (NULL), а не придумывается.
 *
 * @package    Erp801
 * @category   Services
 */
class Service_Erp801_Stock
{
    /** @var Service_Erp801_Stock|null */
    protected static $_instance;

    /** @var Service_Erp801_Sources */
    protected $_sources;

    /** @var Service_Erp801_Settings */
    protected $_settings;

    /**
     * @param   Service_Erp801_Sources|null   $sources
     * @param   Service_Erp801_Settings|null  $settings
     */
    public function __construct(Service_Erp801_Sources $sources = NULL, Service_Erp801_Settings $settings = NULL)
    {
        $this->_sources  = $sources ? $sources : Service_Erp801_Sources::instance();
        $this->_settings = $settings ? $settings : Service_Erp801_Settings::instance();
    }

    /**
     * @return  Service_Erp801_Stock
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
     * Период витрины: по умолчанию — 90 дней (квартал: и оборачиваемость видно,
     * и сезонность не размазана).
     *
     * @param   array   $filters
     * @return  array   {from, to, days, warehouse_id, item_id, limit}
     */
    public function period(array $filters = array())
    {
        $to   = Erp::day(isset($filters['to']) ? $filters['to'] : NULL, date('Y-m-d'));
        $from = Erp::day(isset($filters['from']) ? $filters['from'] : NULL, date('Y-m-d', strtotime($to.' -89 days')));

        if ($from > $to)
        {
            throw new Exception_Erp801_Analytics(
                'Начало периода :from позже окончания :to',
                array(':from' => $from, ':to' => $to),
                'invalid_argument'
            );
        }

        $days = (int) ((strtotime($to) - strtotime($from)) / 86400) + 1;

        return array(
            'from'         => $from,
            'to'           => $to,
            'days'         => max(1, $days),
            'warehouse_id' => ! empty($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : NULL,
            'item_id'      => ! empty($filters['item_id']) ? (int) $filters['item_id'] : NULL,
            'limit'        => ! empty($filters['limit']) ? min(2000, max(1, (int) $filters['limit'])) : 200,
        );
    }

    /**
     * Оборачиваемость и покрытие по позициям.
     *
     * @param   array   $filters  from, to, warehouse_id, item_id, limit
     * @return  array
     */
    public function turnover(array $filters = array())
    {
        $period = $this->period($filters);
        $stock  = $this->_sources->stock();

        if ( ! $stock OR ! method_exists($stock, 'flow'))
        {
            throw $this->_sources->missing('stock');
        }

        $flow_filters = array(
            'date_from' => $period['from'],
            'date_to'   => $period['to'],
        );

        if ($period['warehouse_id'])
        {
            $flow_filters['warehouse_id'] = $period['warehouse_id'];
        }

        if ($period['item_id'])
        {
            $flow_filters['item_id'] = $period['item_id'];
        }

        $flow = $stock->flow($flow_filters, 'item');
        $balances = $this->_balances($period);

        $rows = array();

        foreach ($flow as $row)
        {
            $item_id = (int) $row['item_id'];

            $rows[$item_id] = array(
                'item_id'   => $item_id,
                'qty_in'    => round((float) $row['qty_in'], 6),
                'qty_out'   => round((float) $row['qty_out'], 6),
                'movements' => (int) $row['movements'],
                'first_at'  => $row['first_at'],
                'last_at'   => $row['last_at'],
                'stock'     => 0.0,
                'reserved'  => 0.0,
                'min_qty'   => 0.0,
                'available' => 0.0,
                'below_min' => FALSE,
                'warehouses'=> 0,
            );
        }

        foreach ($balances as $balance)
        {
            $item_id = (int) $balance['item_id'];

            if ( ! isset($rows[$item_id]))
            {
                $rows[$item_id] = array(
                    'item_id'   => $item_id,
                    'qty_in'    => 0.0,
                    'qty_out'   => 0.0,
                    'movements' => 0,
                    'first_at'  => NULL,
                    'last_at'   => NULL,
                    'stock'     => 0.0,
                    'reserved'  => 0.0,
                    'min_qty'   => 0.0,
                    'available' => 0.0,
                    'below_min' => FALSE,
                    'warehouses'=> 0,
                );
            }

            $rows[$item_id]['stock']      = round($rows[$item_id]['stock'] + (float) $balance['qty'], 6);
            $rows[$item_id]['reserved']   = round($rows[$item_id]['reserved'] + (float) $balance['reserved'], 6);
            $rows[$item_id]['available']  = round($rows[$item_id]['available'] + (float) $balance['qty'] - (float) $balance['reserved'], 6);
            $rows[$item_id]['warehouses'] = $rows[$item_id]['warehouses'] + 1;

            if ((float) $balance['min_qty'] > 0)
            {
                $rows[$item_id]['min_qty'] = max($rows[$item_id]['min_qty'], (float) $balance['min_qty']);
            }

            if ( ! empty($balance['is_low']))
            {
                $rows[$item_id]['below_min'] = TRUE;
            }
        }

        $items = $this->_sources->items(array_keys($rows));

        $dead_days = (int) $this->_settings->get('dead_stock_days', 90);
        $result    = array();
        $totals    = array(
            'items'           => 0,
            'qty_in'          => 0.0,
            'qty_out'         => 0.0,
            'stock'           => 0.0,
            'reserved'        => 0.0,
            'dead_stock'      => 0.0,
            'dead_items'      => 0,
            'out_items'       => 0,
            'below_min_items' => 0,
            'cover_days'      => NULL,
        );

        foreach ($rows as $item_id => $row)
        {
            $row['code'] = isset($items[$item_id]['code']) ? $items[$item_id]['code'] : '#'.$item_id;
            $row['name'] = isset($items[$item_id]['name']) ? $items[$item_id]['name'] : 'Объект #'.$item_id;

            $covered = $this->_cover($row['stock'], $row['qty_out'], $period['days']);

            $row['cover_days']    = $covered;
            $row['daily_out']     = round($row['qty_out'] / $period['days'], 6);
            $row['turns_period']  = $row['stock'] > 0 ? round($row['qty_out'] / $row['stock'], 4) : NULL;
            $row['is_dead']       = ($row['stock'] > 0 AND $row['qty_out'] <= 0 AND $this->_days_since($row['last_at']) >= $dead_days);
            $row['is_out']        = ($row['stock'] <= 0 AND $row['qty_out'] > 0);
            $row['class']         = $this->_movement_class($row);

            $totals['items']      = $totals['items'] + 1;
            $totals['qty_in']     = round($totals['qty_in'] + $row['qty_in'], 6);
            $totals['qty_out']    = round($totals['qty_out'] + $row['qty_out'], 6);
            $totals['stock']      = round($totals['stock'] + $row['stock'], 6);
            $totals['reserved']   = round($totals['reserved'] + $row['reserved'], 6);

            if ($row['is_dead'])
            {
                $totals['dead_stock'] = round($totals['dead_stock'] + $row['stock'], 6);
                $totals['dead_items'] = $totals['dead_items'] + 1;
            }

            if ($row['is_out'])
            {
                $totals['out_items'] = $totals['out_items'] + 1;
            }

            if ($row['below_min'])
            {
                $totals['below_min_items'] = $totals['below_min_items'] + 1;
            }

            $result[] = $row;
        }

        // Сортировка: сначала то, что двигается (по расходу), потом остальное.
        usort($result, function ($a, $b) {
            if ($a['qty_out'] == $b['qty_out'])
            {
                return strcmp((string) $a['name'], (string) $b['name']);
            }

            return ($a['qty_out'] < $b['qty_out']) ? 1 : -1;
        });

        // Покрытие запасов в сводке — типичное по позициям, у которых есть
        // расход, а не «весь склад на весь расход»: в одной сумме лежат
        // килограммы филамента, штуки деталей и сопла, поэтому суммировать их
        // нельзя (непотребляемый остаток виден отдельно как мёртвый запас).
        $covers = array();

        foreach ($result as $row)
        {
            if ($row['qty_out'] > 0 AND $row['cover_days'] !== NULL)
            {
                $covers[] = (float) $row['cover_days'];
            }
        }

        if ($covers)
        {
            sort($covers);
            $middle = (int) floor(count($covers) / 2);

            $totals['cover_days'] = count($covers) % 2
                ? round($covers[$middle], 1)
                : round(($covers[$middle - 1] + $covers[$middle]) / 2, 1);
        }
        else
        {
            $totals['cover_days'] = NULL;
        }

        return array(
            'from'    => $period['from'],
            'to'      => $period['to'],
            'days'    => $period['days'],
            'totals'  => $totals,
            'rows'    => array_slice($result, 0, $period['limit']),
            'matched' => count($result),
        );
    }

    /**
     * ABC/XYZ: что двигает склад и насколько ровно.
     *
     * Считается по месячному расходу: ABC — по накопительной доле расхода за
     * период, XYZ — по коэффициенту вариации месячных расходов.
     *
     * @param   array   $filters  from, to, warehouse_id, item_id, limit
     * @return  array
     */
    public function abc_xyz(array $filters = array())
    {
        $period = $this->period($filters);
        $stock  = $this->_sources->stock();

        if ( ! $stock OR ! method_exists($stock, 'flow'))
        {
            throw $this->_sources->missing('stock');
        }

        $flow_filters = array(
            'date_from' => $period['from'],
            'date_to'   => $period['to'],
        );

        if ($period['warehouse_id'])
        {
            $flow_filters['warehouse_id'] = $period['warehouse_id'];
        }

        if ($period['item_id'])
        {
            $flow_filters['item_id'] = $period['item_id'];
        }

        $months = $this->_months($period['from'], $period['to']);

        $items = array();

        foreach ($stock->flow($flow_filters, 'month') as $row)
        {
            $item_id = (int) $row['item_id'];
            $month   = (string) $row['month'];

            if ( ! isset($items[$item_id]))
            {
                $items[$item_id] = array('total' => 0.0, 'months' => array_fill_keys($months, 0.0));
            }

            if ( ! isset($items[$item_id]['months'][$month]))
            {
                continue; // месяц вне периода: flow() уже отфильтрован, это страховка
            }

            $items[$item_id]['months'][$month] = round((float) $row['qty_out'], 6);
            $items[$item_id]['total']          = round($items[$item_id]['total'] + (float) $row['qty_out'], 6);
        }

        if ( ! $items)
        {
            return array(
                'from'    => $period['from'],
                'to'      => $period['to'],
                'months'  => $months,
                'totals'  => array('items' => 0, 'qty_out' => 0.0),
                'borders' => $this->_abc_borders(),
                'matrix'  => $this->_abc_matrix(array()),
                'rows'    => array(),
            );
        }

        // ABC: чем больше расход, тем важнее позиция.
        uasort($items, function ($a, $b) {
            if ($a['total'] == $b['total'])
            {
                return 0;
            }

            return ($a['total'] < $b['total']) ? 1 : -1;
        });

        $total_qty       = 0.0;
        $total_qty_share = 0.0;

        foreach ($items as $item)
        {
            $total_qty += $item['total'];
        }

        $borders = $this->_abc_borders();
        $names   = $this->_sources->items(array_keys($items));
        $classes = array();
        $rows    = array();

        foreach ($items as $item_id => $item)
        {
            $share = $total_qty > 0 ? $item['total'] / $total_qty * 100 : 0.0;

            $before_share = $total_qty_share;
            $total_qty_share += $share;

            // Класс — по накопительной доле: A — то, что набирает первые 80 %
            // расхода, B — до 95 %, хвост — C. Решение принимается по доле,
            // накопленной ДО этой позиции: иначе единственный артикул, дающий
            // 100 % расхода, попал бы в C — «перешагнув» все границы сразу.
            $abc = ($before_share < $borders['a'])
                ? 'A'
                : (($before_share < $borders['b']) ? 'B' : 'C');

            $cv = $this->_cv(array_values($item['months']));

            $xyz = NULL;

            if ($cv !== NULL)
            {
                $xyz = ($cv <= $this->_settings->get('xyz_x_cv', 25))
                    ? 'X'
                    : (($cv <= $this->_settings->get('xyz_y_cv', 50)) ? 'Y' : 'Z');
            }

            $row = array(
                'item_id'      => (int) $item_id,
                'code'         => isset($names[$item_id]['code']) ? $names[$item_id]['code'] : '#'.$item_id,
                'name'         => isset($names[$item_id]['name']) ? $names[$item_id]['name'] : 'Объект #'.$item_id,
                'qty_out'      => round($item['total'], 6),
                'share'        => round($share, 2),
                'share_total'  => round($total_qty_share, 2),
                'abc'          => $abc,
                'cv'           => $cv,
                'xyz'          => $xyz,
                'months'       => $item['months'],
                'months_count' => count($months),
            );

            $classes[] = $row;
            $rows[]    = $row;
        }

        return array(
            'from'    => $period['from'],
            'to'      => $period['to'],
            'months'  => $months,
            'totals'  => array(
                'items'   => count($classes),
                'qty_out' => round($total_qty, 6),
            ),
            'borders' => $borders,
            'matrix'  => $this->_abc_matrix($classes),
            'rows'    => array_slice($rows, 0, $period['limit']),
        );
    }

    /**
     * Дефицит: чего не хватит до цели по покрытию или что уже ниже минимума.
     *
     * `shortage_to_target` — прикидка, сколько докупить, чтобы дожить до цели
     * покрытия по текущему темпу расхода. Это не заказ: заказ считает
     * планирование (erp407) с учётом сроков поставки.
     *
     * @param   array   $filters
     * @return  array
     */
    public function deficit(array $filters = array())
    {
        $turnover = $this->turnover($filters + array('limit' => 2000));
        $target   = (float) $this->_settings->get('cover_target_days', 30);

        $rows = array();

        foreach ($turnover['rows'] as $row)
        {
            $reasons = array();

            if ($row['is_out'])
            {
                $reasons[] = 'out';
            }

            if ($row['below_min'])
            {
                $reasons[] = 'below_min';
            }

            if ($row['cover_days'] !== NULL AND $row['cover_days'] < $target AND $row['qty_out'] > 0)
            {
                $reasons[] = 'low_cover';
            }

            if ( ! $reasons)
            {
                continue;
            }

            $shortage = 0.0;

            if ($row['qty_out'] > 0)
            {
                $shortage = max(0, round(($target - (float) $row['cover_days']) * $row['daily_out'], 6));
            }

            $rows[] = array(
                'item_id'            => $row['item_id'],
                'code'               => $row['code'],
                'name'               => $row['name'],
                'stock'              => $row['stock'],
                'reserved'           => $row['reserved'],
                'available'          => $row['available'],
                'min_qty'            => $row['min_qty'],
                'qty_out'            => $row['qty_out'],
                'daily_out'          => $row['daily_out'],
                'cover_days'         => $row['cover_days'],
                'reasons'            => $reasons,
                'is_out'             => $row['is_out'],
                'shortage_to_target' => $shortage,
            );
        }

        // Сначала то, чего нет совсем, затем близкое к нулю покрытие.
        usort($rows, function ($a, $b) {
            $left  = $a['cover_days'] === NULL ? 99999 : (float) $a['cover_days'];
            $right = $b['cover_days'] === NULL ? 99999 : (float) $b['cover_days'];

            if ($left == $right)
            {
                return $a['shortage_to_target'] < $b['shortage_to_target'] ? 1 : -1;
            }

            return ($left < $right) ? -1 : 1;
        });

        return array(
            'from'         => $turnover['from'],
            'to'           => $turnover['to'],
            'days'         => $turnover['days'],
            'target_days'  => $target,
            'rows'         => $rows,
            'items'        => count($rows),
            'out_items'    => count(array_filter($rows, function ($row) { return $row['is_out']; })),
            'shortage_sum' => round(array_sum(array_column($rows, 'shortage_to_target')), 6),
        );
    }

    /**
     * KPI блока запасов для сводки и дашборда.
     *
     * @param   array   $filters
     * @return  array
     */
    public function summary(array $filters = array())
    {
        $turnover = $this->turnover($filters);
        $target   = (float) $this->_settings->get('cover_target_days', 30);
        $totals   = $turnover['totals'];

        $cover = $totals['cover_days'];

        return array(
            'from'        => $turnover['from'],
            'to'          => $turnover['to'],
            'days'        => $turnover['days'],
            'items'       => $totals['items'],
            'qty_out'     => $totals['qty_out'],
            'qty_in'      => $totals['qty_in'],
            'stock'       => $totals['stock'],
            'reserved'    => $totals['reserved'],
            'dead_stock'  => $totals['dead_stock'],
            'dead_items'  => $totals['dead_items'],
            'out_items'   => $totals['out_items'],
            'below_min'   => $totals['below_min_items'],
            'cover_days'  => $cover,
            'cover_target'=> $target,
            'cover_state' => $this->_state($cover, $target, TRUE),
        );
    }

    /**
     * Остатки по фильтру периода — страницами, чтобы не упереться в лимит.
     *
     * @param   array   $period
     * @return  array
     */
    protected function _balances(array $period)
    {
        $stock = $this->_sources->stock();

        if ( ! $stock OR ! method_exists($stock, 'balances'))
        {
            throw $this->_sources->missing('stock');
        }

        $filters = array();

        if ($period['warehouse_id'])
        {
            $filters['warehouse_id'] = $period['warehouse_id'];
        }

        if ($period['item_id'])
        {
            $filters['item_id'] = $period['item_id'];
        }

        $rows  = array();
        $page  = 1000;
        $guard = 0;

        do
        {
            $chunk = $stock->balances($filters, $page, count($rows));

            foreach ($chunk['rows'] as $row)
            {
                $rows[] = $row;
            }

            $received = count($chunk['rows']);
            $guard++;
        }
        while ($received === $page AND $guard < 50 AND count($rows) < (int) $chunk['total']);

        return $rows;
    }

    /**
     * Дни покрытия: на сколько хватит остатка при текущем темпе расхода.
     *
     * @param   float   $stock
     * @param   float   $qty_out
     * @param   int     $days
     * @return  float|null
     */
    protected function _cover($stock, $qty_out, $days)
    {
        $daily = (float) $qty_out / max(1, (int) $days);

        if ($daily <= 0)
        {
            return NULL; // расхода нет — покрытие не определено
        }

        return round((float) $stock / $daily, 1);
    }

    /**
     * Класс движения позиции.
     *
     * @param   array   $row
     * @return  string
     */
    protected function _movement_class(array $row)
    {
        if ($row['is_out'])
        {
            return 'out';
        }

        if ($row['is_dead'])
        {
            return 'dead';
        }

        if ($row['cover_days'] === NULL)
        {
            return 'idle';
        }

        if ($row['cover_days'] <= 15)
        {
            return 'fast';
        }

        if ($row['cover_days'] > (float) $this->_settings->get('dead_stock_days', 90))
        {
            return 'slow';
        }

        return 'medium';
    }

    /**
     * Сколько дней прошло с даты последнего движения.
     *
     * @param   string|null $at
     * @return  int
     */
    protected function _days_since($at)
    {
        if ( ! $at)
        {
            return 99999;
        }

        $time = strtotime((string) $at);

        if ( ! $time)
        {
            return 99999;
        }

        return (int) floor((time() - $time) / 86400);
    }

    /**
     * Месяцы периода (для XYZ — важно, чтобы пропущенный месяц был нулём, а не
     * отсутствующей точкой: «приходили раз в два месяца» это не «ровный расход»).
     *
     * @param   string  $from
     * @param   string  $to
     * @return  array
     */
    protected function _months($from, $to)
    {
        $months = array();
        $cursor = strtotime(date('Y-m-01', strtotime($from)));
        $last   = strtotime(date('Y-m-01', strtotime($to)));

        while ($cursor <= $last)
        {
            $months[] = date('Y-m', $cursor);
            $cursor   = strtotime('+1 month', $cursor);
        }

        return $months;
    }

    /**
     * Коэффициент вариации месячного расхода, %.
     *
     * @param   array   $values
     * @return  float|null
     */
    protected function _cv(array $values)
    {
        $count = count($values);

        if ($count < 2)
        {
            return NULL;
        }

        $mean = array_sum($values) / $count;

        if ($mean <= 0)
        {
            return NULL;
        }

        $sum = 0.0;

        foreach ($values as $value)
        {
            $sum += pow($value - $mean, 2);
        }

        $deviation = sqrt($sum / ($count - 1));

        return round($deviation / $mean * 100, 2);
    }

    /**
     * Границы ABC из настроек.
     *
     * @return  array
     */
    protected function _abc_borders()
    {
        $a = (float) $this->_settings->get('abc_a_share', 80);
        $b = (float) $this->_settings->get('abc_b_share', 95);

        return array('a' => $a, 'b' => $b);
    }

    /**
     * Матрица ABC × XYZ: сколько позиций в каждой из девяти клеток.
     *
     * @param   array   $classes
     * @return  array
     */
    protected function _abc_matrix(array $classes)
    {
        $matrix = array();

        foreach (array('A', 'B', 'C') as $abc)
        {
            foreach (array('X', 'Y', 'Z', NULL) as $xyz)
            {
                $matrix[$abc.($xyz === NULL ? '' : $xyz)] = 0;
            }
        }

        foreach ($classes as $row)
        {
            $key = $row['abc'].($row['xyz'] === NULL ? '' : $row['xyz']);

            if (isset($matrix[$key]))
            {
                $matrix[$key]++;
            }
        }

        return $matrix;
    }

    /**
     * Состояние показателя относительно цели.
     *
     * @param   float|null  $value
     * @param   float       $target
     * @param   bool        $higher_is_better
     * @return  string      ok|warn|bad|unknown
     */
    protected function _state($value, $target, $higher_is_better = TRUE)
    {
        if ($value === NULL)
        {
            return 'unknown';
        }

        $value = (float) $value;

        if ($higher_is_better)
        {
            if ($value >= $target)
            {
                return 'ok';
            }

            return ($value >= $target * 0.5) ? 'warn' : 'bad';
        }

        if ($value <= $target)
        {
            return 'ok';
        }

        return ($value <= $target * 1.5) ? 'warn' : 'bad';
    }
}
