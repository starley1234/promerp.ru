<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Расчёт потребности (MRP-минимум) — erp407_planning.
 *
 * Отвечает на вопрос плановика: «чего, сколько и когда не хватает, чтобы
 * выполнить программу в срок». Расчёт намеренно повторяет то, как человек
 * считает на бумаге, — так результат можно объяснить, а не «так посчитала
 * система»:
 *
 *   1. СПРОС. Что нужно выпустить: строки программы (или незакрытые заказы
 *      производства, если программа собирается из заказов).
 *   2. РАЗУЗЛОВАНИЕ. Каждое изделие раскрывается по составу техкарты
 *      (erp228 через `Service_Erp402_Bom`): материалы попадают в потребность
 *      отдельными строками с пометкой «из чего». Глубина ограничена
 *      (`max_levels`), циклы в спецификации отсекаются по пути узла.
 *   3. ПОКРЫТИЕ. Из спроса вычитается то, что уже есть: свободный остаток
 *      склада (erp401), незавершёнка цеха (erp408) и ранее утверждённые
 *      предложения по этой позиции.
 *   4. ДЕФИЦИТ И ДАТА ЗАКАЗА. Остаток — это дефицит; дата заказа считается
 *      назад от срока на срок поставки (`lead_time_days`): сегодня эта дата
 *      уже может быть в прошлом — тогда строка помечается `late`.
 *   5. ПАРТИЯ. Заказ округляется до минимальной партии (`min_order_qty`) и
 *      кратности (`order_multiple`) из атрибутов номенклатуры реестра.
 *
 * Результат каждого прогона сохраняется снимком (`run_uuid`) в
 * `erp_407_requirements`: видно, что изменилось между прогонами, а
 * утверждённые предложения прошлых прогонов идут в покрытие следующего —
 * поэтому повторный расчёт не заказывает одно и то же дважды.
 *
 * Модуль никого не вызывает напрямую: склад, техкарты и производство
 * подключаются через `class_exists()` и мягко деградируют (расчёт без склада
 * возможен — он просто не учитывает остатки, о чём сообщает в предупреждениях).
 *
 * @package    Erp407
 * @category   Services
 */
class Service_Erp407_Mrp extends Service_Erp407_Programs
{
    /** @var int Ограничение глубины разузлования состава */
    protected $_max_levels = 3;

    /** @var int Срок поставки по умолчанию для закупки, дней */
    protected $_default_purchase_lead = 7;

    /** @var int Срок изготовления по умолчанию, дней */
    protected $_default_produce_lead = 5;

    /**
     * Рассчитать потребность по программе.
     *
     * @param   int|Model_Erp407_Program    $program
     * @param   array                       $options    warehouse_id, max_levels, keep_history
     * @return  array   run_uuid, program, rows, summary, warnings
     * @throws  Exception_Erp407_Planning
     */
    public function calculate($program, array $options = array())
    {
        $program = ($program instanceof Model_Erp407_Program) ? $program : $this->get($program);

        if ( ! $program)
        {
            throw new Exception_Erp407_Planning('Программа не найдена', NULL, 'not_found');
        }

        if ( ! $program->can_calculate())
        {
            throw new Exception_Erp407_Planning(
                'Программа :number закрыта — расчёт потребности невозможен',
                array(':number' => $program->number),
                'invalid_transition'
            );
        }

        $lines = $this->lines($program->id);

        if ( ! $lines)
        {
            throw new Exception_Erp407_Planning(
                'В программе :number нет строк — считать нечего',
                array(':number' => $program->number),
                'no_demand'
            );
        }

        if ( ! empty($options['max_levels']))
        {
            $this->_max_levels = max(1, (int) $options['max_levels']);
        }

        $warnings = array();
        $demand   = $this->_collect_demand($program, $lines, $options, $warnings);

        $rows = $this->_supply_and_deficit($program, $demand, $options, $warnings);

        $run_uuid = $this->_uuid();

        if (empty($options['keep_history']))
        {
            $this->_delete_requirements($program->id);
        }

        foreach ($rows as $row)
        {
            $row['run_uuid']   = $run_uuid;
            $row['program_id'] = (int) $program->id;

            DB::insert('erp_407_requirements', array_keys($row))
                ->values(array_values($row))
                ->execute();
        }

        $program->run_at    = date('Y-m-d H:i:s');
        $program->run_uuid  = $run_uuid;
        $program->updated_at = date('Y-m-d H:i:s');
        $program->save();

        $this->_journal($program, 'update', array(), array(
            'run_uuid' => $run_uuid,
            'rows'     => count($rows),
        ), 'Расчёт потребности: строк '.count($rows).', дефицитов '.count(array_filter($rows, function ($row) {
            return $row['deficit_qty'] > 0;
        })));

        Event::fire('erp407.mrp.calculated', array(
            'id'      => (int) $program->id,
            'uuid'    => (string) $program->uuid,
            'title'   => (string) $program->number,
            'run'     => (string) $run_uuid,
            'rows'    => count($rows),
        ));

        return array(
            'run_uuid' => $run_uuid,
            'program'  => $program,
            'rows'     => $rows,
            'summary'  => $this->summarize($rows),
            'warnings' => array_values(array_unique($warnings)),
        );
    }

    /**
     * Расчёт по открытым заказам производства (программа собирается из заказов).
     *
     * @param   array   $options
     * @return  array   run_uuid, rows, summary, warnings
     */
    public function calculate_for_orders(array $options = array())
    {
        if ( ! class_exists('Service_Erp402_Orders'))
        {
            throw new Exception_Erp407_Planning('Модуль производства (erp402) недоступен', NULL, 'dependency');
        }

        // `search()` у erp402 возвращает конверт {rows, total, limit, offset},
        // а строки — ассоциативные массивы (выборка идёт через DB::select).
        // Без этого в цикл попадали сам конверт и счётчик: «Attempt to read
        // property id on array». Ошибка вылезала только на реальных открытых
        // заказах, поэтому форма ответа разбирается явно.
        $page = Service_Erp402_Orders::instance()->search(array(
            'status' => array('planned', 'released', 'in_progress'),
        ), 200);

        $orders = (is_array($page) AND isset($page['rows'])) ? $page['rows'] : (array) $page;
        $orders = array_map(function ($row) { return is_array($row) ? (object) $row : $row; }, $orders);

        $lines    = array();
        $warnings = array();

        foreach ($orders as $order)
        {
            $lines[] = array(
                'item_id'      => (int) $order->item_id,
                'name'         => $order->title ? (string) $order->title : (string) $order->number,
                'code'         => (string) $order->number,
                'qty'          => (float) $order->qty - (float) $order->qty_done,
                'uom_code'     => (string) $order->uom_code,
                'due_date'     => $order->target_date ? (string) $order->target_date : date('Y-m-d'),
                'tech_card_id' => $order->tech_card_id ? (int) $order->tech_card_id : NULL,
                'order_id'     => (int) $order->id,
            );
        }

        if ( ! $lines)
        {
            return array(
                'run_uuid' => NULL,
                'rows'     => array(),
                'summary'  => $this->summarize(array()),
                'warnings' => array('Открытых заказов производства нет — считать нечего'),
            );
        }

        $demand = $this->_collect_from_lines($lines, 'orders', $options, $warnings);
        $rows   = $this->_supply_and_deficit(NULL, $demand, $options, $warnings);

        return array(
            'run_uuid' => NULL,
            'rows'     => $rows,
            'summary'  => $this->summarize($rows),
            'warnings' => array_values(array_unique($warnings)),
        );
    }

    /**
     * Строки последнего расчёта программы.
     *
     * @param   int|Model_Erp407_Program    $program
     * @param   array                       $filters    only_deficit, supply_type, limit
     * @return  array   Model_Erp407_Requirement
     */
    public function requirements($program, array $filters = array(), $limit = 500)
    {
        $program = ($program instanceof Model_Erp407_Program) ? $program : $this->get($program);

        if ( ! $program)
        {
            return array();
        }

        $query = ORM::factory('Erp407_Requirement')
            ->where('program_id', '=', (int) $program->id);

        if ( ! empty($program->run_uuid))
        {
            $query->where('run_uuid', '=', (string) $program->run_uuid);
        }

        if ( ! empty($filters['only_deficit']))
        {
            $query->where('deficit_qty', '>', 0);
        }

        if ( ! empty($filters['supply_type']))
        {
            $query->where('supply_type', '=', (string) $filters['supply_type']);
        }

        return $query
            ->order_by('late', 'DESC')
            ->order_by('order_date', 'ASC')
            ->order_by('id', 'ASC')
            ->limit($limit)
            ->find_all()
            ->as_array();
    }

    /**
     * Сводка по строкам расчёта.
     *
     * @param   array   $rows
     * @return  array   total, deficit, purchase, produce, late, coverage
     */
    public function summarize(array $rows)
    {
        $summary = array(
            'total'    => count($rows),
            'deficit'  => 0,
            'purchase' => 0,
            'produce'  => 0,
            'late'     => 0,
            'demand'   => 0.0,
            'covered'  => 0.0,
            'shortage' => 0.0,
            'coverage' => 100.0,
        );

        foreach ($rows as $row)
        {
            $deficit = isset($row['deficit_qty']) ? (float) $row['deficit_qty'] : 0.0;

            $summary['demand']  += (float) $row['demand_qty'];
            $summary['covered'] += (float) $row['covered_qty'];

            if ($deficit > 0)
            {
                $summary['deficit']++;
                $summary['shortage'] += $deficit;

                if ($row['supply_type'] === 'produce')
                {
                    $summary['produce']++;
                }
                else
                {
                    $summary['purchase']++;
                }

                // late приходит и булевым (свежий расчёт), и строкой 't'/'f'
                // (снимок из PostgreSQL) — приводим к одному смыслу
                if (Erp::bool(isset($row['late']) ? $row['late'] : FALSE))
                {
                    $summary['late']++;
                }
            }
        }

        if ($summary['demand'] > 0)
        {
            $summary['coverage'] = round(min(100, $summary['covered'] / $summary['demand'] * 100), 1);
        }

        return $summary;
    }

    /**
     * Срок поставки для позиции: из атрибутов номенклатуры, иначе по умолчанию.
     *
     * @param   int     $item_id
     * @param   string  $supply_type
     * @return  int     дней
     */
    public function lead_time($item_id, $supply_type = 'purchase')
    {
        $default = ($supply_type === 'produce') ? $this->_default_produce_lead : $this->_default_purchase_lead;

        $attributes = $this->_attributes($item_id);

        if ($supply_type === 'produce')
        {
            $keys = array('production_lead_time_days', 'produce_lead_time_days', 'manufacturing_days');
        }
        else
        {
            $keys = array('lead_time_days', 'purchase_lead_time_days', 'delivery_days');
        }

        foreach ($keys as $key)
        {
            if (isset($attributes[$key]) AND (int) $attributes[$key] > 0)
            {
                return (int) $attributes[$key];
            }
        }

        return (int) $default;
    }

    /**
     * Способ обеспечения позиции: закупка или своё производство.
     *
     * Признаки по порядку: явный атрибут номенклатуры → наличие техкарты
     * у строки программы → умолчание «закупка».
     *
     * @param   array   $attributes
     * @param   int     $tech_card_id
     * @return  string  purchase|produce
     */
    public function supply_type(array $attributes = array(), $tech_card_id = NULL)
    {
        foreach (array('supply_type', 'procurement', 'make_or_buy') as $key)
        {
            if ( ! empty($attributes[$key]))
            {
                $value = strtolower((string) $attributes[$key]);

                if (in_array($value, array('produce', 'make', 'own', 'свое', 'производство'), TRUE))
                {
                    return 'produce';
                }

                if (in_array($value, array('purchase', 'buy', 'закупка'), TRUE))
                {
                    return 'purchase';
                }
            }
        }

        if ( ! empty($attributes['is_manufactured']) OR ! empty($attributes['made_in_house']))
        {
            return 'produce';
        }

        return ($tech_card_id > 0) ? 'produce' : 'purchase';
    }

    /**
     * Округление заказа: минимальная партия и кратность.
     *
     * @param   float   $qty
     * @param   array   $attributes
     * @return  float
     */
    public function round_qty($qty, array $attributes = array())
    {
        $qty = (float) $qty;

        if ($qty <= 0)
        {
            return 0.0;
        }

        $min   = isset($attributes['min_order_qty']) ? (float) $attributes['min_order_qty'] : 0.0;
        $step  = isset($attributes['order_multiple']) ? (float) $attributes['order_multiple'] : 0.0;
        $round = isset($attributes['round_to']) ? (float) $attributes['round_to'] : 0.0;

        $qty = max($qty, $min);

        if ($step > 0)
        {
            $qty = ceil($qty / $step) * $step;
        }
        elseif ($round > 0)
        {
            $qty = ceil($qty / $round) * $round;
        }

        return round($qty, 6);
    }

    /**
     * Общая сводка планирования для дашборда: программы, дефициты, предложения.
     *
     * @return  array
     */
    public function stats()
    {
        $programs = DB::select(
                array(DB::expr('COUNT(*)'), 'total'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'approved')"), 'approved'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'draft')"), 'draft')
            )
            ->from('erp_407_programs')
            ->execute()
            ->current();

        $proposals = DB::select(
                array(DB::expr('COUNT(*)'), 'total'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'new')"), 'new'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'exported')"), 'exported'),
                array(DB::expr("COALESCE(SUM(CASE WHEN status = 'new' THEN qty ELSE 0 END), 0)"), 'qty_new')
            )
            ->from('erp_407_proposals')
            ->execute()
            ->current();

        $latest = ORM::factory('Erp407_Program')
            ->order_by('run_at', 'DESC')
            ->order_by('id', 'DESC')
            ->find();

        return array(
            'programs'   => (int) $programs['total'],
            'approved'   => (int) $programs['approved'],
            'draft'      => (int) $programs['draft'],
            'proposals'  => (int) $proposals['total'],
            'proposals_new' => (int) $proposals['new'],
            'exported'   => (int) $proposals['exported'],
            'qty_new'    => (float) $proposals['qty_new'],
            'last_run'   => $latest->loaded() ? (string) $latest->run_at : NULL,
        );
    }

    // ---------------------------------------------------------------- спрос --

    /**
     * Собрать спрос из строк программы (с разузлованием состава).
     *
     * @param   Model_Erp407_Program    $program
     * @param   array                   $lines
     * @param   array                   $options
     * @param   array                   $warnings
     * @return  array   item_id => строка спроса
     */
    protected function _collect_demand(Model_Erp407_Program $program, array $lines, array $options, array & $warnings)
    {
        $rows = array();

        foreach ($lines as $line)
        {
            if ($line->status === 'cancelled')
            {
                continue;
            }

            $qty = (float) $line->qty - ($line->status === 'produced' ? (float) $line->qty : 0);

            if ($qty <= 0)
            {
                continue;
            }

            $rows[] = array(
                'item_id'      => (int) $line->item_id,
                'name'         => (string) $line->name,
                'code'         => $line->code ? (string) $line->code : NULL,
                'qty'          => $qty,
                'uom_code'     => (string) $line->uom_code,
                'due_date'     => $line->due_date ? (string) $line->due_date : (string) $program->period_to,
                'tech_card_id' => $line->tech_card_id ? (int) $line->tech_card_id : NULL,
                'line_id'      => (int) $line->id,
                'parent_name'  => NULL,
                'level'        => 0,
            );
        }

        return $this->_collect_from_lines($rows, 'program', $options, $warnings);
    }

    /**
     * Разузлование: изделие → материалы по составу техкарты.
     *
     * @param   array   $lines
     * @param   string  $source
     * @param   array   $options
     * @param   array   $warnings
     * @return  array   item_id|name => строка спроса
     */
    protected function _collect_from_lines(array $lines, $source, array $options, array & $warnings)
    {
        $demand = array();
        $queue  = $lines;

        while ($queue)
        {
            $line = array_shift($queue);

            $level = isset($line['level']) ? (int) $line['level'] : 0;
            $key   = $this->_demand_key($line);

            if ( ! isset($demand[$key]))
            {
                $demand[$key] = array(
                    'item_id'      => (int) $line['item_id'],
                    'line_id'      => isset($line['line_id']) ? $line['line_id'] : NULL,
                    'name'         => (string) $line['name'],
                    'code'         => isset($line['code']) ? $line['code'] : NULL,
                    'uom_code'     => isset($line['uom_code']) ? (string) $line['uom_code'] : 'шт',
                    'demand_qty'   => 0.0,
                    'due_date'     => isset($line['due_date']) ? (string) $line['due_date'] : NULL,
                    'tech_card_id' => isset($line['tech_card_id']) ? (int) $line['tech_card_id'] : NULL,
                    'source'       => $source,
                    'level'        => $level,
                    'parent_name'  => isset($line['parent_name']) ? $line['parent_name'] : NULL,
                );
            }

            $demand[$key]['demand_qty'] += (float) $line['qty'];

            // Раскрываем состав только для того, что делаем сами
            if ($level + 1 > $this->_max_levels OR empty($line['tech_card_id']))
            {
                continue;
            }

            foreach ($this->_explode($line, $warnings) as $child)
            {
                $child['level']       = $level + 1;
                $child['parent_name'] = (string) $line['name'];
                $queue[] = $child;
            }
        }

        return $demand;
    }

    /**
     * Состав изделия по техкарте, приведённый к спросу.
     *
     * @param   array   $line
     * @param   array   $warnings
     * @return  array
     */
    protected function _explode(array $line, array & $warnings)
    {
        if ( ! class_exists('Service_Erp402_Bom'))
        {
            $warnings[] = 'Модуль производства (erp402) недоступен: состав по техкарте не разузлован';

            return array();
        }

        try
        {
            $bom = Service_Erp402_Bom::instance()->from_tech_card((int) $line['tech_card_id']);
        }
        catch (Exception $e)
        {
            $warnings[] = $line['name'].': состав не прочитан — '.$e->getMessage();

            return array();
        }

        $children = array();

        foreach ($bom['items'] as $item)
        {
            $qty = (float) $item['qty_per_unit'] * (float) $line['qty'];

            if ($qty <= 0)
            {
                continue;
            }

            $children[] = array(
                'item_id'      => (int) $item['item_id'],
                'name'         => (string) $item['name'],
                'code'         => NULL,
                'qty'          => $qty,
                'uom_code'     => isset($item['uom_code']) ? (string) $item['uom_code'] : 'шт',
                'due_date'     => (string) $line['due_date'],
                'tech_card_id' => NULL,
            );
        }

        if ( ! $children AND ! empty($bom['warnings']))
        {
            foreach ($bom['warnings'] as $warning)
            {
                $warnings[] = $line['name'].': '.$warning;
            }
        }

        return $children;
    }

    /**
     * Ключ строки спроса: одна позиция — одна строка (даже если пришла из
     * нескольких заказов или из разных уровней состава).
     *
     * @param   array   $line
     * @return  string
     */
    protected function _demand_key(array $line)
    {
        if ( ! empty($line['item_id']))
        {
            return 'item:'.(int) $line['item_id'];
        }

        return 'name:'.mb_strtolower(trim((string) $line['name']));
    }

    // ------------------------------------------------------------- покрытие --

    /**
     * Покрытие спроса и расчёт дефицита по каждой позиции.
     *
     * @param   Model_Erp407_Program|null   $program
     * @param   array                       $demand
     * @param   array                       $options
     * @param   array                       $warnings
     * @return  array
     */
    protected function _supply_and_deficit($program, array $demand, array $options, array & $warnings)
    {
        $rows = array();

        foreach ($demand as $key => $row)
        {
            $attributes = $this->_attributes($row['item_id']);
            $supply     = $this->supply_type($attributes, $row['tech_card_id']);

            $stock    = 0.0;
            $reserved = 0.0;

            if ($row['item_id'] > 0)
            {
                list($stock, $reserved) = $this->_stock($row['item_id'], $options, $warnings);
            }
            else
            {
                $warnings[] = $row['name'].': позиция не сопоставлена с номенклатурой — остатки не учтены';
            }

            $wip      = $row['item_id'] > 0 ? $this->_wip($row['item_id'], $warnings) : 0.0;
            $on_order = $row['item_id'] > 0 ? $this->_on_order($row['item_id'], $program) : 0.0;

            $available = $stock + $wip + $on_order;
            $covered   = min((float) $row['demand_qty'], $available);
            $deficit   = round(max(0, (float) $row['demand_qty'] - $available), 6);

            $lead       = $this->lead_time($row['item_id'], $supply);
            $order_qty  = $deficit > 0 ? $this->round_qty($deficit, $attributes) : 0.0;
            $order_date = $deficit > 0 ? $this->_order_date($row['due_date'], $lead) : NULL;

            $rows[] = array(
                'line_id'       => $row['line_id'] ? (int) $row['line_id'] : NULL,
                'item_id'       => $row['item_id'] ? (int) $row['item_id'] : NULL,
                'name'          => $row['name'],
                'code'          => $row['code'],
                'uom_code'      => $row['uom_code'],
                'supply_type'   => $supply,
                'demand_qty'    => round((float) $row['demand_qty'], 6),
                'stock_qty'     => round($stock, 6),
                'reserved_qty'  => round($reserved, 6),
                'wip_qty'       => round($wip, 6),
                'on_order_qty'  => round($on_order, 6),
                'covered_qty'   => round($covered, 6),
                'deficit_qty'   => $deficit,
                'order_qty'     => $order_qty,
                'round_qty'     => round($order_qty - $deficit, 6),
                'lead_time_days' => (int) $lead,
                'due_date'      => $row['due_date'],
                'order_date'    => $order_date,
                'late'          => $order_date ? (strtotime($order_date) < strtotime(date('Y-m-d'))) : FALSE,
                'level'         => (int) $row['level'],
                'parent_name'   => $row['parent_name'],
                'source'        => $row['source'],
            );
        }

        usort($rows, function ($a, $b) {
            if ($a['late'] !== $b['late'])
            {
                return $a['late'] ? -1 : 1;
            }

            return strcmp((string) $a['order_date'], (string) $b['order_date']);
        });

        return $rows;
    }

    /**
     * Свободный остаток и резерв по позиции (склад erp401).
     *
     * @param   int     $item_id
     * @param   array   $options
     * @param   array   $warnings
     * @return  array   [доступно, зарезервировано]
     */
    protected function _stock($item_id, array $options, array & $warnings)
    {
        if ( ! class_exists('Service_Erp401_Stock'))
        {
            $warnings[] = 'Модуль склада (erp401) недоступен: остатки не учтены';

            return array(0.0, 0.0);
        }

        try
        {
            $stock = Service_Erp401_Stock::instance();

            if ( ! empty($options['warehouse_id']))
            {
                $available = (float) $stock->available((int) $options['warehouse_id'], $item_id);
                $balance   = $stock->balance((int) $options['warehouse_id'], $item_id);
                $total     = $balance->loaded() ? (float) $balance->qty : $available;

                return array($available, round(max(0, $total - $available), 6));
            }

            $total = (float) $stock->total_qty($item_id);

            return array($total, 0.0);
        }
        catch (Exception $e)
        {
            $warnings[] = 'Остатки по позиции '.$item_id.' не прочитаны: '.$e->getMessage();

            return array(0.0, 0.0);
        }
    }

    /**
     * Незавершёнка: сколько ещё будет выпущено по уже запущенным заказам.
     *
     * @param   int     $item_id
     * @param   array   $warnings
     * @return  float
     */
    protected function _wip($item_id, array & $warnings)
    {
        try
        {
            $row = DB::select(array(DB::expr('COALESCE(SUM(qty - qty_done), 0)'), 'qty'))
                ->from('erp_402_orders')
                ->where('item_id', '=', (int) $item_id)
                ->where('status', 'IN', array('released', 'in_progress'))
                ->execute()
                ->current();
        }
        catch (Exception $e)
        {
            $warnings[] = 'Незавершёнка по позиции '.$item_id.' не прочитана';

            return 0.0;
        }

        return (float) $row['qty'];
    }

    /**
     * Что уже заказано: утверждённые и переданные предложения (без учёта
     * предложений текущего расчёта).
     *
     * @param   int                         $item_id
     * @param   Model_Erp407_Program|null   $program
     * @return  float
     */
    protected function _on_order($item_id, $program = NULL)
    {
        $query = DB::select(array(DB::expr('COALESCE(SUM(qty), 0)'), 'qty'))
            ->from('erp_407_proposals')
            ->where('item_id', '=', (int) $item_id)
            ->where('status', 'IN', array('approved', 'exported'));

        $row = $query->execute()->current();

        return (float) $row['qty'];
    }

    /**
     * Дата заказа: срок минус срок поставки.
     *
     * @param   string  $due_date
     * @param   int     $lead_time_days
     * @return  string  Y-m-d
     */
    protected function _order_date($due_date, $lead_time_days)
    {
        $due = $due_date ? strtotime($due_date) : time();

        return date('Y-m-d', $due - ((int) $lead_time_days * 86400));
    }

    /**
     * Атрибуты номенклатуры из реестра (erp227).
     *
     * @param   int     $item_id
     * @return  array
     */
    protected function _attributes($item_id)
    {
        if ( ! $item_id OR ! class_exists('Service_Erp227_Registry'))
        {
            return array();
        }

        try
        {
            $object = Service_Erp227_Registry::instance()->get_by_id((int) $item_id);

            if ( ! $object->loaded())
            {
                return array();
            }

            $attributes = $object->attributes;

            return is_array($attributes) ? $attributes : (array) json_decode((string) $attributes, TRUE);
        }
        catch (Exception $e)
        {
            return array();
        }
    }
}
