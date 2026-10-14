<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Обеспечение заказа материалами (erp402_production).
 *
 * Производство не ведёт свой склад: оно просит, резервирует и списывает
 * материалы через сервис складского учёта (`Service_Erp401_Stock`). Здесь
 * живёт производственная логика поверх склада:
 *
 *  - `requirements()` — что нужно, что есть и чего не хватает (по строкам);
 *  - `reserve()` — зарезервировать материалы под заказ, помечая строки
 *    `reserved` либо `short` (нет остатка) без исключения «наверх»: решение
 *    принимает планировщик, а не падение страницы;
 *  - `issue()` — списать зарезервированное (или, если резерва не было, сразу
 *    расходом) при выпуске;
 *  - `release_all()` — снять резервы при отмене заказа.
 *
 * Строка состава без сопоставленной номенклатуры (`item_id` = NULL) в резерв
 * не попадает: сначала планировщик связывает материал с позицией реестра.
 *
 * @package    Erp402
 * @category   Services
 */
class Service_Erp402_Materials
{
    /** @var Service_Erp402_Materials */
    protected static $_instance;

    /**
     * @return  Service_Erp402_Materials
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
     * Сервис складского учёта.
     *
     * @return  Service_Erp401_Stock
     * @throws  Exception_Erp402_Production
     */
    public function stock()
    {
        if ( ! class_exists('Service_Erp401_Stock'))
        {
            throw new Exception_Erp402_Production('Модуль складского учёта (erp401) недоступен', NULL, 'dependency');
        }

        return Service_Erp401_Stock::instance();
    }

    /**
     * Потребность заказа в материалах.
     *
     * @param   Model_Erp402_Order  $order
     * @return  array   строки: line, name, item_id, uom_code, warehouse_id, needed, have, shortage, mapped
     */
    public function requirements(Model_Erp402_Order $order)
    {
        $rows  = array();
        $stock = $this->stock();

        foreach ($order->items()->find_all() as $item)
        {
            $needed     = $item->remaining();
            $warehouse  = (int) ($item->warehouse_id ? $item->warehouse_id : $order->warehouse_id);

            $row = array(
                'item'         => $item,
                'line_no'      => (int) $item->line_no,
                'name'         => (string) $item->name,
                'item_id'      => (int) $item->item_id,
                'uom_code'     => (string) $item->uom_code,
                'warehouse_id' => $warehouse,
                'needed'       => $needed,
                'have'         => NULL,
                'total'        => NULL,
                'shortage'     => NULL,
                'mapped'       => (bool) $item->item_id,
            );

            if ($row['item_id'] > 0 AND $warehouse > 0)
            {
                $row['have']     = (float) $stock->available($warehouse, $row['item_id']);
                $row['total']    = (float) $stock->total_qty($row['item_id']);
                $row['shortage'] = round(max(0, $needed - $row['have']), 6);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Сводка по заказу: сколько строк обеспечено, сколько не хватает.
     *
     * @param   Model_Erp402_Order  $order
     * @return  array   total, mapped, ready, short, unmapped
     */
    public function summary(Model_Erp402_Order $order)
    {
        $summary = array('total' => 0, 'mapped' => 0, 'ready' => 0, 'short' => 0, 'unmapped' => 0);

        foreach ($this->requirements($order) as $row)
        {
            $summary['total']++;

            if ( ! $row['mapped'])
            {
                $summary['unmapped']++;
                continue;
            }

            $summary['mapped']++;

            if ($row['shortage'] > 0)
            {
                $summary['short']++;
            }
            else
            {
                $summary['ready']++;
            }
        }

        return $summary;
    }

    /**
     * Зарезервировать материалы под заказ.
     *
     * @param   Model_Erp402_Order  $order
     * @param   int|null            $user_id
     * @return  array   reserved, short, unmapped (списки для отчёта пользователю)
     */
    public function reserve(Model_Erp402_Order $order, $user_id = NULL)
    {
        $stock    = $this->stock();
        $reserved = 0;
        $short    = array();
        $unmapped = array();

        foreach ($order->items()->find_all() as $item)
        {
            $qty = $item->remaining();

            if ( ! $item->item_id OR $qty <= 0)
            {
                if ( ! $item->item_id)
                {
                    $unmapped[] = (string) $item->name;
                }

                continue;
            }

            $warehouse = (int) ($item->warehouse_id ? $item->warehouse_id : $order->warehouse_id);

            if ($warehouse <= 0)
            {
                $short[] = $item->name.': не выбран склад материалов';

                $item->status = 'short';
                $item->save();

                continue;
            }

            try
            {
                $reservation = $stock->reserve($warehouse, (int) $item->item_id, $qty, array(
                    'ref_type'   => 'erp402_order',
                    'ref_id'     => (int) $order->id,
                    'expires_at' => NULL,
                ));

                $item->reservation_id = (int) $reservation->id;
                $item->warehouse_id   = $warehouse;
                $item->status         = 'reserved';
                $item->save();

                $reserved++;
            }
            catch (Exception $e)
            {
                // Нехватка — рабочая ситуация: строка помечается, заказ остаётся планом
                $item->status = 'short';
                $item->save();

                $short[] = $item->name.': '.$e->getMessage();
            }
        }

        return array('reserved' => $reserved, 'short' => $short, 'unmapped' => $unmapped);
    }

    /**
     * Списать материалы в производство.
     *
     * @param   Model_Erp402_Order  $order
     * @param   int|null            $user_id
     * @return  array   issued, errors
     */
    public function issue(Model_Erp402_Order $order, $user_id = NULL)
    {
        $stock  = $this->stock();
        $issued = 0;
        $errors = array();

        foreach ($order->items()->find_all() as $item)
        {
            $qty = $item->remaining();

            if ( ! $item->item_id OR $qty <= 0)
            {
                continue;
            }

            try
            {
                if ($item->reservation_id)
                {
                    // Резерв уже снимает остаток со склада — отдельный расход не нужен
                    $stock->consume_reservation((int) $item->reservation_id, $qty);
                }
                else
                {
                    $warehouse = (int) ($item->warehouse_id ? $item->warehouse_id : $order->warehouse_id);

                    $stock->issue($warehouse, (int) $item->item_id, $qty, array(
                        'doc_type' => 'erp402_order',
                        // doc_id в складских документах — числовой идентификатор,
                        // номер заказа уходит в примечание
                        'doc_id'   => (int) $order->id,
                        'note'     => 'Списание по производственному заказу '.$order->number,
                    ));
                }

                $item->qty_issued     = (float) $item->qty_total;
                $item->reservation_id = NULL;
                $item->status         = 'issued';
                $item->save();

                $issued++;
            }
            catch (Exception $e)
            {
                $errors[] = $item->name.': '.$e->getMessage();
            }
        }

        return array('issued' => $issued, 'errors' => $errors);
    }

    /**
     * Снять резервы заказа (отмена, возврат в план).
     *
     * @param   Model_Erp402_Order  $order
     * @return  int     сколько резервов снято
     */
    public function release_all(Model_Erp402_Order $order)
    {
        $stock   = $this->stock();
        $released = 0;

        foreach ($order->items()->find_all() as $item)
        {
            if ( ! $item->reservation_id)
            {
                continue;
            }

            try
            {
                $stock->release_reservation((int) $item->reservation_id);
            }
            catch (Exception $e)
            {
                // Резерв мог быть уже снят вручную на складе — это не ошибка заказа
                Kohana::$log->add(Log::WARNING, 'erp402: резерв :id не снят — :error', array(
                    ':id'    => (int) $item->reservation_id,
                    ':error' => $e->getMessage(),
                ));
            }

            $item->reservation_id = NULL;
            $item->status         = ($item->qty_issued >= $item->qty_total) ? 'issued' : 'planned';
            $item->save();

            $released++;
        }

        return $released;
    }

    /**
     * Заказы, которым не хватает материалов (для дашборда и задачи).
     *
     * @param   int     $limit
     * @return  array   строки: order, item, needed, have, shortage
     */
    public function shortages($limit = 20)
    {
        $result = array();

        $orders = ORM::factory('Erp402_Order')
            ->where('status', 'IN', array('planned', 'released', 'in_progress'))
            ->order_by('priority', 'ASC')
            ->order_by('target_date', 'ASC')
            ->limit($limit)
            ->find_all();

        foreach ($orders as $order)
        {
            try
            {
                $rows = $this->requirements($order);
            }
            catch (Exception $e)
            {
                continue;
            }

            foreach ($rows as $row)
            {
                if ($row['shortage'] === NULL OR $row['shortage'] <= 0)
                {
                    continue;
                }

                $result[] = array(
                    'order'    => $order,
                    'name'     => $row['name'],
                    'item_id'  => $row['item_id'],
                    'needed'   => $row['needed'],
                    'have'     => $row['have'],
                    'shortage' => $row['shortage'],
                    'uom_code' => $row['uom_code'],
                );
            }
        }

        return $result;
    }
}
