<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Статистика качества (erp405_quality).
 *
 * Отвечает на вопросы, ради которых модуль и нужен: «где теряем качество»,
 * «сколько брака дал поставщик», «сколько партий стоит на карантине и ждёт
 * решения». Все агрегаты считает PostgreSQL (`GROUP BY`), а не PHP: брака на
 * заводе бывает много, и выгружать его в память ради суммы — плохая привычка.
 *
 * Периоды — по дате записи дефекта (`erp_405_defects.created_at`), а не по дате
 * контроля: дефект находят один раз, а контроль может быть заведён задним
 * числом.
 *
 * @package    Erp405
 * @category   Services
 */
class Service_Erp405_Stats
{
    /** @var Service_Erp405_Stats|null */
    protected static $_instance;

    /**
     * @return  Service_Erp405_Stats
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
     * Сводка для доски качества.
     *
     * @return  array
     */
    public function summary()
    {
        $inspections = DB::select(
                array(DB::expr('COUNT(*)'), 'total'),
                array(DB::expr("COUNT(*) FILTER (WHERE status IN ('draft', 'registered'))"), 'open'),
                array(DB::expr("COUNT(*) FILTER (WHERE result = 'failed' AND decision IS NULL AND status NOT IN ('closed', 'cancelled'))"), 'pending'),
                array(DB::expr("COUNT(*) FILTER (WHERE result = 'passed')"), 'passed'),
                array(DB::expr("COUNT(*) FILTER (WHERE result = 'failed')"), 'failed'),
                array(DB::expr('COALESCE(SUM(defect_qty), 0)'), 'defect_qty'),
                array(DB::expr('COALESCE(SUM(quarantine_qty), 0)'), 'quarantine_qty'),
                array(DB::expr("COUNT(*) FILTER (WHERE created_at >= date_trunc('month', CURRENT_DATE))"), 'this_month')
            )
            ->from('erp_405_inspections')
            ->execute()
            ->current();

        $decisions = DB::select(
                'd.decision',
                array(DB::expr('COUNT(*)'), 'total'),
                array(DB::expr('COALESCE(SUM(d.qty), 0)'), 'qty')
            )
            ->from(array('erp_405_decisions', 'd'))
            ->group_by('d.decision')
            ->order_by('total', 'DESC')
            ->execute()
            ->as_array();

        $share = (float) $inspections['total'] > 0
            ? round((int) $inspections['failed'] / (int) $inspections['total'] * 100, 1)
            : 0.0;

        return array(
            'total'          => (int) $inspections['total'],
            'open'           => (int) $inspections['open'],
            'pending'        => (int) $inspections['pending'],
            'passed'         => (int) $inspections['passed'],
            'failed'         => (int) $inspections['failed'],
            'defect_qty'     => (float) $inspections['defect_qty'],
            'quarantine_qty' => (float) $inspections['quarantine_qty'],
            'this_month'     => (int) $inspections['this_month'],
            'defect_share'   => $share,
            'decisions'      => $decisions,
        );
    }

    /**
     * Брак по кодам дефектов.
     *
     * @param   string|null $from  дата Y-m-d
     * @param   string|null $to
     * @param   int         $limit
     * @return  array
     */
    public function by_code($from = NULL, $to = NULL, $limit = 20)
    {
        $query = DB::select(
                'd.defect_code_id',
                'd.code',
                array(DB::expr('COUNT(*)'), 'records'),
                array(DB::expr('SUM(d.qty)'), 'qty'),
                array(DB::expr('MAX(c.name)'), 'name'),
                array(DB::expr('MAX(c.category)'), 'category')
            )
            ->from(array('erp_405_defects', 'd'))
            ->join(array('erp_405_defect_codes', 'c'), 'LEFT')
            ->on('c.id', '=', 'd.defect_code_id')
            ->group_by('d.defect_code_id', 'd.code')
            ->order_by('qty', 'DESC')
            ->limit($limit);

        $this->_period($query, 'd.created_at', $from, $to);

        return $query->execute()->as_array();
    }

    /**
     * Брак по причинам: где теряем качество — у поставщика, в цехе, на складе.
     *
     * @param   string|null $from
     * @param   string|null $to
     * @return  array
     */
    public function by_cause($from = NULL, $to = NULL)
    {
        $query = DB::select(
                'd.cause',
                array(DB::expr('COUNT(*)'), 'records'),
                array(DB::expr('SUM(d.qty)'), 'qty'),
                array(DB::expr('COUNT(DISTINCT d.inspection_id)'), 'inspections')
            )
            ->from(array('erp_405_defects', 'd'))
            ->group_by('d.cause')
            ->order_by('qty', 'DESC');

        $this->_period($query, 'd.created_at', $from, $to);

        return $query->execute()->as_array();
    }

    /**
     * Брак по поставщикам: с кем разговаривать про качество.
     *
     * @param   string|null $from
     * @param   string|null $to
     * @param   int         $limit
     * @return  array
     */
    public function by_supplier($from = NULL, $to = NULL, $limit = 20)
    {
        $query = DB::select(
                'd.supplier_id',
                array(DB::expr('COUNT(*)'), 'records'),
                array(DB::expr('SUM(d.qty)'), 'qty'),
                array(DB::expr('COUNT(DISTINCT d.inspection_id)'), 'inspections')
            )
            ->from(array('erp_405_defects', 'd'))
            ->where('d.supplier_id', 'IS NOT', DB::expr('NULL'))
            ->group_by('d.supplier_id')
            ->order_by('qty', 'DESC')
            ->limit($limit);

        $this->_period($query, 'd.created_at', $from, $to);

        $rows = $query->execute()->as_array();

        // Имя контрагента берём у владельца справочника (erp301), а не запросом
        // в его таблицы
        foreach ($rows as $index => $row)
        {
            $rows[$index]['name'] = $this->_counterparty_name((int) $row['supplier_id']);
        }

        return $rows;
    }

    /**
     * Брак по номенклатуре.
     *
     * @param   string|null $from
     * @param   string|null $to
     * @param   int         $limit
     * @return  array
     */
    public function by_item($from = NULL, $to = NULL, $limit = 20)
    {
        $query = DB::select(
                'd.item_id',
                array(DB::expr('COUNT(*)'), 'records'),
                array(DB::expr('SUM(d.qty)'), 'qty')
            )
            ->from(array('erp_405_defects', 'd'))
            ->where('d.item_id', 'IS NOT', DB::expr('NULL'))
            ->group_by('d.item_id')
            ->order_by('qty', 'DESC')
            ->limit($limit);

        $this->_period($query, 'd.created_at', $from, $to);

        $rows = $query->execute()->as_array();

        foreach ($rows as $index => $row)
        {
            list($name, ) = Service_Erp405_Inspections::instance()->item_info((int) $row['item_id']);
            $rows[$index]['name'] = $name;
        }

        return $rows;
    }

    /**
     * Динамика по месяцам: сколько партий проверили и сколько брака нашли.
     *
     * @param   int $months
     * @return  array
     */
    public function by_month($months = 6)
    {
        $rows = DB::select(
                array(DB::expr("to_char(date_trunc('month', i.created_at), 'YYYY-MM')"), 'period'),
                array(DB::expr('COUNT(*)'), 'inspections'),
                array(DB::expr("COUNT(*) FILTER (WHERE i.result = 'failed')"), 'failed'),
                array(DB::expr('COALESCE(SUM(i.defect_qty), 0)'), 'defect_qty')
            )
            ->from(array('erp_405_inspections', 'i'))
            ->where('i.created_at', '>=', DB::expr("date_trunc('month', CURRENT_DATE) - INTERVAL '".max(1, min(24, (int) $months) - 1)." months'"))
            ->group_by(DB::expr("date_trunc('month', i.created_at)"))
            ->order_by(DB::expr("date_trunc('month', i.created_at)"), 'ASC')
            ->execute()
            ->as_array();

        $result = array();

        foreach ($rows as $row)
        {
            $result[] = array(
                'period'      => (string) $row['period'],
                'inspections' => (int) $row['inspections'],
                'failed'      => (int) $row['failed'],
                'defect_qty'  => (float) $row['defect_qty'],
                'share'       => ((int) $row['inspections'] > 0)
                    ? round((int) $row['failed'] / (int) $row['inspections'] * 100, 1)
                    : 0.0,
            );
        }

        return $result;
    }

    /**
     * Что стоит на карантине и сколько ждёт решения.
     *
     * @return  array
     */
    public function quarantine()
    {
        return DB::select(
                'i.id',
                'i.number',
                'i.item_id',
                'i.item_name',
                'i.qty',
                'i.defect_qty',
                'i.quarantine_qty',
                'i.quarantine_warehouse_id',
                'i.status',
                'i.result',
                'i.created_at'
            )
            ->from(array('erp_405_inspections', 'i'))
            ->where('i.quarantine_qty', '>', 0)
            ->order_by('i.created_at', 'ASC')
            ->execute()
            ->as_array();
    }

    /**
     * @param   ORM     $query
     * @param   string  $column
     * @param   string|null $from
     * @param   string|null $to
     * @return  void
     */
    protected function _period($query, $column, $from, $to)
    {
        if ($from)
        {
            $query->where($column, '>=', (string) $from.' 00:00:00');
        }

        if ($to)
        {
            $query->where($column, '<=', (string) $to.' 23:59:59');
        }
    }

    /**
     * @param   int $supplier_id
     * @return  string|null
     */
    protected function _counterparty_name($supplier_id)
    {
        if ( ! $supplier_id OR ! class_exists('Service_Erp301_Counterparties'))
        {
            return NULL;
        }

        try
        {
            $counterparty = Service_Erp301_Counterparties::instance()->get($supplier_id);
        }
        catch (Exception $e)
        {
            return NULL;
        }

        return $counterparty ? (string) $counterparty->name : NULL;
    }
}
