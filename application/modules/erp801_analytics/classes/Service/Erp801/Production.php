<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Витрина производства: OEE, простои, план/факт (erp801_analytics).
 *
 * Сырьё — `Service_Erp408_Operations::production_stats()`: плановые часы,
 * отработанное время, время простоев, план и факт по количеству. Формулы живут
 * здесь, потому что это предмет аналитики: методику OEE меняют, не трогая цех.
 *
 * Как считается OEE (и почему именно так):
 *
 *   Доступность  = работа / (работа + простои)
 *       В знаменателе именно учтённое время операций, а не календарь: система
 *       знает, сколько цех работал и сколько стоял, но не знает, что было
 *       запланировано в нерабочие смены. Показывать «доступность» от календаря
 *       значило бы придумывать данные о плановом фонде времени.
 *   Производительность = (плановое время / план выпуска) × выпуск / работа
 *       Идеальный такт берётся из нормы: плановые часы делятся на плановое
 *       количество. Так производительность сравнивается с нормой, а не с
 *       чьим-то ожиданием.
 *   Качество = годное / (годное + брак)
 *
 * OEE = Доступность × Производительность × Качество. Значение выше 100 %
 * означает, что норма оказалась пессимистичнее факта — это не «отличная
 * работа», а повод пересмотреть норму, и на витрине такая клетка подписана.
 *
 * @package    Erp801
 * @category   Services
 */
class Service_Erp801_Production
{
    /** @var Service_Erp801_Production|null */
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
     * @return  Service_Erp801_Production
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
     * Период витрины: по умолчанию — текущий месяц.
     *
     * @param   array   $filters
     * @return  array   {from, to, days, center_id}
     */
    public function period(array $filters = array())
    {
        $to   = Erp::day(isset($filters['to']) ? $filters['to'] : NULL, date('Y-m-d'));
        $from = Erp::day(isset($filters['from']) ? $filters['from'] : NULL, date('Y-m-01'));

        if ($from > $to)
        {
            throw new Exception_Erp801_Analytics(
                'Начало периода :from позже окончания :to',
                array(':from' => $from, ':to' => $to),
                'invalid_argument'
            );
        }

        return array(
            'from'      => $from,
            'to'        => $to,
            'days'      => max(1, (int) ((strtotime($to) - strtotime($from)) / 86400) + 1),
            'center_id' => ! empty($filters['center_id']) ? (int) $filters['center_id'] : NULL,
        );
    }

    /**
     * OEE и его составляющие за период.
     *
     * @param   array   $filters  from, to, center_id
     * @return  array
     */
    public function oee(array $filters = array())
    {
        $period = $this->period($filters);
        $stats  = $this->_stats($period);

        $oee = $this->_oee($stats['totals']);

        return array(
            'from'      => $period['from'],
            'to'        => $period['to'],
            'days'      => $period['days'],
            'center_id' => $period['center_id'],
            'target'    => (float) $this->_settings->get('oee_target', 65),
            'state'     => $this->_state($oee['oee'], (float) $this->_settings->get('oee_target', 65)),
            'totals'    => $stats['totals'],
        ) + $oee;
    }

    /**
     * Панель производства: OEE, Парето простоев, план/факт, разрезы по центрам.
     *
     * @param   array   $filters
     * @return  array
     */
    public function panel(array $filters = array())
    {
        $period = $this->period($filters);
        $stats  = $this->_stats($period);

        $oee       = $this->_oee($stats['totals']);
        $pareto    = $this->_pareto($stats['downtime_reasons']);
        $centers   = array();
        $by_center = array();

        foreach ($stats['by_center'] as $row)
        {
            $center_oee = $this->_oee($row);

            $by_center[] = array(
                'work_center_id'    => $row['work_center_id'],
                'work_center_label' => $row['work_center_label'],
                'operations'        => $row['operations'],
                'done'              => $row['done'],
                'late'              => $row['late'],
                'qty_planned'       => $row['qty_planned'],
                'qty_ok'            => $row['qty_ok'],
                'qty_scrap'         => $row['qty_scrap'],
                'worked_sec'        => $row['worked_sec'],
                'downtime_sec'      => $row['downtime_sec'],
            ) + $center_oee;

            $centers[] = $row['work_center_id'];
        }

        $plan_fact = $this->_plan_fact($stats['totals']);

        return array(
            'from'       => $period['from'],
            'to'         => $period['to'],
            'days'       => $period['days'],
            'center_id'  => $period['center_id'],
            'target'     => (float) $this->_settings->get('oee_target', 65),
            'state'      => $this->_state($oee['oee'], (float) $this->_settings->get('oee_target', 65)),
            'totals'     => $stats['totals'],
            'plan_fact'  => $plan_fact,
            'pareto'     => $pareto,
            'by_center'  => $by_center,
        ) + $oee;
    }

    /**
     * Парето простоев: причины по убыванию времени с накопленной долей.
     *
     * @param   array   $filters
     * @param   int     $limit
     * @return  array
     */
    public function downtime_pareto(array $filters = array(), $limit = 10)
    {
        $period = $this->period($filters);
        $stats  = $this->_stats($period);

        return array(
            'from'   => $period['from'],
            'to'     => $period['to'],
            'total'  => $stats['totals']['downtime_sec'],
            'rows'   => array_slice($this->_pareto($stats['downtime_reasons']), 0, max(1, (int) $limit)),
            'all'    => $this->_pareto($stats['downtime_reasons']),
        );
    }

    /**
     * KPI производства для сводки.
     *
     * @param   array   $filters
     * @return  array
     */
    public function summary(array $filters = array())
    {
        $panel     = $this->panel($filters);
        $returned  = $panel['totals']['qty_ok'] + $panel['totals']['qty_scrap'];
        $top       = $panel['pareto'] ? $panel['pareto'][0] : NULL;

        return array(
            'from'             => $panel['from'],
            'to'               => $panel['to'],
            'days'             => $panel['days'],
            'operations'       => $panel['totals']['operations'],
            'done'             => $panel['totals']['done'],
            'late'             => $panel['totals']['late'],
            'qty_planned'      => $panel['totals']['qty_planned'],
            'qty_ok'           => $panel['totals']['qty_ok'],
            'qty_scrap'        => $panel['totals']['qty_scrap'],
            'worked_sec'       => $panel['totals']['worked_sec'],
            'downtime_sec'     => $panel['totals']['downtime_sec'],
            'availability'     => $panel['availability'],
            'performance'      => $panel['performance'],
            'quality'          => $panel['quality'],
            'oee'              => $panel['oee'],
            'oee_target'       => $panel['target'],
            'oee_state'        => $panel['state'],
            'scrap_share'      => $returned > 0 ? round($panel['totals']['qty_scrap'] / $returned * 100, 1) : 0.0,
            'plan_complete'    => $panel['plan_fact']['qty_complete'],
            'top_downtime'     => $top,
        );
    }

    /**
     * Сырьё от цеха.
     *
     * @param   array   $period
     * @return  array
     */
    protected function _stats(array $period)
    {
        $shopfloor = $this->_sources->shopfloor();

        if ( ! $shopfloor OR ! method_exists($shopfloor, 'production_stats'))
        {
            throw $this->_sources->missing('shopfloor');
        }

        $stats = $shopfloor->production_stats($period['from'], $period['to'], $period['center_id']);

        if ( ! is_array($stats) OR ! isset($stats['totals']))
        {
            throw new Exception_Erp801_Analytics(
                'Цех не отдал производственную статистику за :from — :to',
                array(':from' => $period['from'], ':to' => $period['to']),
                'source_missing'
            );
        }

        $stats['downtime_reasons'] = isset($stats['downtime_reasons']) ? $stats['downtime_reasons'] : array();
        $stats['by_center']        = isset($stats['by_center']) ? $stats['by_center'] : array();

        return $stats;
    }

    /**
     * OEE и составляющие по агрегатам (всего или по одному центру).
     *
     * @param   array   $totals
     * @return  array   availability, performance, quality, oee, ideal_cycle_sec, notes
     */
    protected function _oee(array $totals)
    {
        $planned_sec = round((float) $totals['planned_hours'] * 3600, 3);
        $worked_sec  = (int) $totals['worked_sec'];
        $downtime    = (int) $totals['downtime_sec'];
        $planned_qty = (float) $totals['qty_planned'];
        $qty_ok      = (float) $totals['qty_ok'];
        $qty_scrap   = (float) $totals['qty_scrap'];
        $returned    = $qty_ok + $qty_scrap;

        $notes = array();

        // Доступность: доля учтённого времени, когда работали, а не стояли.
        $availability = NULL;

        if (($worked_sec + $downtime) > 0)
        {
            $availability = round($worked_sec / ($worked_sec + $downtime) * 100, 1);
        }
        else
        {
            $notes[] = 'Нет учтённого времени работ и простоев — доступность не определена';
        }

        // Идеальный такт из нормы: плановое время на плановый выпуск.
        $ideal_cycle = NULL;

        if ($planned_qty > 0 AND $planned_sec > 0)
        {
            $ideal_cycle = round($planned_sec / $planned_qty, 3);
        }
        else
        {
            $notes[] = 'Нет планового выпуска или плановых часов — норма такта не определена';
        }

        $performance = NULL;

        if ($ideal_cycle !== NULL AND $worked_sec > 0 AND $returned > 0)
        {
            $performance = round($ideal_cycle * $returned / $worked_sec * 100, 1);

            if ($performance > 100)
            {
                $notes[] = 'Выпуск быстрее нормы (более 100 %): норму стоит пересмотреть';
            }
        }
        elseif ($worked_sec <= 0)
        {
            $notes[] = 'Нет отработанного времени — производительность не определена';
        }

        $quality = $returned > 0 ? round($qty_ok / $returned * 100, 1) : NULL;

        if ($quality === NULL)
        {
            $notes[] = 'Нет выпуска — качество не определено';
        }

        $oee = NULL;

        if ($availability !== NULL AND $performance !== NULL AND $quality !== NULL)
        {
            $oee = round($availability / 100 * $performance / 100 * $quality / 100 * 100, 1);
        }

        return array(
            'availability'    => $availability,
            'performance'     => $performance,
            'quality'         => $quality,
            'oee'             => $oee,
            'ideal_cycle_sec' => $ideal_cycle,
            'planned_sec'     => $planned_sec,
            'run_sec'         => $worked_sec,
            'notes'           => $notes,
        );
    }

    /**
     * План/факт по количеству и времени.
     *
     * @param   array   $totals
     * @return  array
     */
    protected function _plan_fact(array $totals)
    {
        $planned_qty  = (float) $totals['qty_planned'];
        $qty_ok       = (float) $totals['qty_ok'];
        $qty_scrap    = (float) $totals['qty_scrap'];
        $planned_hours = (float) $totals['planned_hours'];
        $worked_hours  = round((int) $totals['worked_sec'] / 3600, 2);

        return array(
            'qty_planned'    => $planned_qty,
            'qty_returned'   => round($qty_ok + $qty_scrap, 6),
            'qty_ok'         => $qty_ok,
            'qty_scrap'      => $qty_scrap,
            'qty_complete'   => $planned_qty > 0 ? round($qty_ok / $planned_qty * 100, 1) : NULL,
            'planned_hours'  => $planned_hours,
            'worked_hours'   => $worked_hours,
            'hours_complete' => $planned_hours > 0 ? round($worked_hours / $planned_hours * 100, 1) : NULL,
            'late'           => (int) $totals['late'],
            'done'           => (int) $totals['done'],
            'late_share'     => ((int) $totals['done'] > 0) ? round((int) $totals['late'] / (int) $totals['done'] * 100, 1) : NULL,
        );
    }

    /**
     * Доли причин простоев с накопленным итогом (Парето).
     *
     * @param   array   $reasons
     * @return  array
     */
    protected function _pareto(array $reasons)
    {
        if ( ! $reasons)
        {
            return array();
        }

        $total  = 0;
        $rows   = array();

        foreach ($reasons as $reason)
        {
            $total += (int) $reason['seconds'];
        }

        $share_total = 0.0;
        $cumulative  = 0;

        foreach ($reasons as $reason)
        {
            $seconds = (int) $reason['seconds'];
            $share   = $total > 0 ? round($seconds / $total * 100, 1) : 0.0;

            $cumulative += $seconds;
            $share_total += $share;

            $rows[] = array(
                'reason'        => (string) $reason['reason'],
                'reason_label'  => (string) $reason['reason_label'],
                'events'        => (int) $reason['events'],
                'seconds'       => $seconds,
                'hours'         => round($seconds / 3600, 2),
                'share'         => $share,
                'share_total'   => round($share_total, 1),
                'cumulative_sec'=> $cumulative,
            );
        }

        return $rows;
    }

    /**
     * Состояние OEE относительно цели.
     *
     * @param   float|null  $oee
     * @param   float       $target
     * @return  string
     */
    protected function _state($oee, $target)
    {
        if ($oee === NULL)
        {
            return 'unknown';
        }

        if ($oee >= $target)
        {
            return 'ok';
        }

        return ($oee >= $target * 0.7) ? 'warn' : 'bad';
    }
}
