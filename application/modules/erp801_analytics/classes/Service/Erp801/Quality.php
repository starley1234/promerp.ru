<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Витрина качества (erp801_analytics).
 *
 * Качество уже считает свои потери (`Service_Erp405_Stats`): контроли, брак,
 * решения, карантин. Задача аналитики — свести это в один экран с понятными
 * границами: где красная зона, какие причины дают основную часть брака (Парето)
 * и что делать, если источник выключен.
 *
 * Доля брака считается по контролям, а не по количеству произведённого: склад и
 * цех ведут количество по своим единицам, а контроль — по партиям, и свести
 * «штуки партии» с «штуками выпуска» без общего ключа нельзя. Поэтому метрика
 * названа честно — «доля забракованных контролей», и она же применяется как
 * порог в настройках.
 *
 * @package    Erp801
 * @category   Services
 */
class Service_Erp801_Quality
{
    /** @var Service_Erp801_Quality|null */
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
     * @return  Service_Erp801_Quality
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
     * @return  array   {from, to, days}
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
            'from' => $from,
            'to'   => $to,
            'days' => max(1, (int) ((strtotime($to) - strtotime($from)) / 86400) + 1),
        );
    }

    /**
     * Панель качества: сводка владельца плюс Парето причин и кодов.
     *
     * @param   array   $filters  from, to, limit
     * @return  array
     */
    public function panel(array $filters = array())
    {
        $period  = $this->period($filters);
        $quality = $this->_service();
        $limit   = ! empty($filters['limit']) ? min(100, max(1, (int) $filters['limit'])) : 15;

        $summary = method_exists($quality, 'summary') ? (array) $quality->summary() : array();

        $by_cause = method_exists($quality, 'by_cause')
            ? (array) $quality->by_cause($period['from'], $period['to'])
            : array();

        $by_code = method_exists($quality, 'by_code')
            ? (array) $quality->by_code($period['from'], $period['to'], $limit)
            : array();

        $by_supplier = method_exists($quality, 'by_supplier')
            ? (array) $quality->by_supplier($period['from'], $period['to'], $limit)
            : array();

        $by_month = method_exists($quality, 'by_month')
            ? (array) $quality->by_month(6)
            : array();

        $quarantine = method_exists($quality, 'quarantine')
            ? (array) $quality->quarantine()
            : array();

        // `AND` имеет приоритет ниже `=`: без скобок в $share попадал bool от
        // первого isset(), поэтому MCP возвращал true вместо процента.
        $share = (isset($summary['total']) && (int) $summary['total'] > 0 && isset($summary['defect_share']))
            ? (float) $summary['defect_share']
            : NULL;
        $target = (float) $this->_settings->get('defect_share_target', 5);

        return array(
            'from'          => $period['from'],
            'to'            => $period['to'],
            'days'          => $period['days'],
            'summary'       => $summary,
            'defect_share'  => $share,
            'target'        => $target,
            'state'         => $this->_state($share, $target),
            'cause_pareto'  => $this->_pareto($by_cause, 'cause'),
            'code_pareto'   => $this->_pareto($by_code, 'code'),
            'by_supplier'   => $by_supplier,
            'by_month'      => $by_month,
            'quarantine'    => $quarantine,
        );
    }

    /**
     * KPI качества для сводки.
     *
     * @param   array   $filters
     * @return  array
     */
    public function summary(array $filters = array())
    {
        $panel   = $this->panel($filters);
        $summary = $panel['summary'];
        $top     = $panel['cause_pareto'] ? $panel['cause_pareto'][0] : NULL;

        return array(
            'from'          => $panel['from'],
            'to'            => $panel['to'],
            'days'          => $panel['days'],
            'total'         => isset($summary['total']) ? (int) $summary['total'] : 0,
            'open'          => isset($summary['open']) ? (int) $summary['open'] : 0,
            'pending'       => isset($summary['pending']) ? (int) $summary['pending'] : 0,
            'passed'        => isset($summary['passed']) ? (int) $summary['passed'] : 0,
            'failed'        => isset($summary['failed']) ? (int) $summary['failed'] : 0,
            'defect_qty'    => isset($summary['defect_qty']) ? (float) $summary['defect_qty'] : 0.0,
            'quarantine_qty'=> isset($summary['quarantine_qty']) ? (float) $summary['quarantine_qty'] : 0.0,
            'defect_share'  => $panel['defect_share'],
            'target'        => $panel['target'],
            'state'         => $panel['state'],
            'top_cause'     => $top,
        );
    }

    /**
     * Сервис качества или честная ошибка витрины.
     *
     * @return  object
     */
    protected function _service()
    {
        $quality = $this->_sources->quality();

        if ( ! $quality)
        {
            throw $this->_sources->missing('quality');
        }

        return $quality;
    }

    /**
     * Парето по строкам владельца: причина/код, количество брака, доля,
     * накопленная доля. Названия причин аналитика не переводит — она их не
     * знает; подписи берёт владелец (`erp405`), здесь только сортировка и доли.
     *
     * @param   array   $rows
     * @param   string  $key_field
     * @return  array
     */
    protected function _pareto(array $rows, $key_field = 'cause')
    {
        if ( ! $rows)
        {
            return array();
        }

        $total_rows = array();
        $total_qty  = 0.0;

        foreach ($rows as $row)
        {
            $qty = isset($row['qty']) ? (float) $row['qty'] : 0.0;

            $total_qty += $qty;
            $total_rows[] = $row;
        }

        usort($total_rows, function ($a, $b) {
            $left  = isset($a['qty']) ? (float) $a['qty'] : 0.0;
            $right = isset($b['qty']) ? (float) $b['qty'] : 0.0;

            if ($left == $right)
            {
                return 0;
            }

            return ($left < $right) ? 1 : -1;
        });

        $share_total = 0.0;
        $result      = array();

        foreach ($total_rows as $row)
        {
            $qty   = isset($row['qty']) ? (float) $row['qty'] : 0.0;
            $share = $total_qty > 0 ? round($qty / $total_qty * 100, 1) : 0.0;

            $share_total += $share;

            $key = isset($row[$key_field]) ? $row[$key_field] : (isset($row['reason']) ? $row['reason'] : '');

            $result[] = array(
                'key'          => (string) $key,
                'label'        => isset($row['name']) ? (string) $row['name'] : (string) $key,
                'category'     => isset($row['category']) ? (string) $row['category'] : NULL,
                'qty'          => round($qty, 6),
                'records'      => isset($row['records']) ? (int) $row['records'] : NULL,
                'inspections'  => isset($row['inspections']) ? (int) $row['inspections'] : NULL,
                'share'        => $share,
                'share_total'  => round($share_total, 1),
            );
        }

        return $result;
    }

    /**
     * Состояние доли брака относительно цели.
     *
     * @param   float|null  $share
     * @param   float       $target
     * @return  string
     */
    protected function _state($share, $target)
    {
        if ($share === NULL)
        {
            return 'unknown';
        }

        if ($share <= $target)
        {
            return 'ok';
        }

        return ($share <= $target * 2) ? 'warn' : 'bad';
    }
}
