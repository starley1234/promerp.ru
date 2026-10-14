<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Поставщики и история цен (erp403_procurement).
 *
 * Поставщик — это контрагент с ролью `supplier` из справочника erp301; модуль
 * закупок ничего не дублирует, а держит рядом свои условия (срок поставки,
 * валюта, минимальная партия, отсрочка, оценка) — таблица `erp_403_suppliers`.
 *
 * Сервис отвечает на главный вопрос закупщика: «у кого брать». Ответ строится
 * из данных, а не из памяти:
 *
 *   - история цен (`erp_403_prices`) — сколько позиция стоила у поставщика
 *     и когда; заполняется заказами и приёмками;
 *   - срок поставки из профиля;
 *   - исполнительность: доля приёмок, пришедших не позже срока заказа;
 *   - оценка закупщика и пометка «предпочтительный».
 *
 * `choose()` складывает это в балл и возвращает лучший вариант с расшифровкой —
 * агент или закупщик видит, почему выбран именно этот поставщик.
 *
 * @package    Erp403
 * @category   Services
 */
class Service_Erp403_Suppliers
{
    const TABLE = 'erp_403_suppliers';
    const PRICES = 'erp_403_prices';

    /** @var Service_Erp403_Suppliers|null */
    protected static $_instance;

    /**
     * @return  Service_Erp403_Suppliers
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    // =========================================================================
    //  Чтение
    // =========================================================================

    /**
     * Поставщики с закупочными условиями.
     *
     * Возвращает массивы: контрагент (`counterparty`), профиль (`profile`,
     * может быть NULL), рассчитанные показатели (`stats`).
     *
     * @param   array   $filters    search, preferred, active, with_prices
     * @param   int     $limit
     * @param   int     $offset
     * @return  array
     */
    public function search(array $filters = array(), $limit = 25, $offset = 0)
    {
        $rows = array();

        foreach ($this->counterparties($filters, $limit, $offset) as $counterparty)
        {
            $supplier_id = (int) $counterparty->id;
            $profile     = $this->profile($supplier_id, FALSE);

            if ( ! empty($filters['preferred']) AND ( ! $profile OR ! $profile->is_preferred()))
            {
                continue;
            }

            if (isset($filters['active']) AND $filters['active'] !== NULL AND ( ! $profile OR $profile->is_active() !== (bool) $filters['active']))
            {
                continue;
            }

            $rows[] = array(
                'counterparty' => $counterparty,
                'profile'      => $profile,
                'goods'        => $this->goods_count($supplier_id),
                'last_price_at' => $this->last_price_date($supplier_id),
            );
        }

        return $rows;
    }

    /**
     * Сколько поставщиков с такими фильтрами.
     *
     * @param   array   $filters
     * @return  int
     */
    public function count(array $filters = array())
    {
        if (empty($filters['preferred']) AND ! isset($filters['active']))
        {
            return count($this->counterparties($filters, 1000, 0));
        }

        return count($this->search($filters, 1000, 0));
    }

    /**
     * Контрагенты с ролью «поставщик» (справочник erp301).
     *
     * @param   array   $filters
     * @param   int     $limit
     * @param   int     $offset
     * @return  array   Model_Erp301_Counterparty
     */
    public function counterparties(array $filters = array(), $limit = 25, $offset = 0)
    {
        if ( ! class_exists('Service_Erp301_Counterparties'))
        {
            return array();
        }

        try
        {
            $page = Service_Erp301_Counterparties::instance()->search(array(
                'role'   => 'supplier',
                'term'   => isset($filters['search']) ? $filters['search'] : NULL,
                'status' => isset($filters['status']) ? $filters['status'] : 'active',
            ), (int) $limit, (int) $offset);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp403: справочник контрагентов недоступен — :error', array(':error' => $e->getMessage()));

            return array();
        }

        // Приводим результат к массиву моделей: сервисы и тесты работают с
        // ним как с обычным списком (reset(), count(), foreach).
        return is_object($page['rows']) ? $page['rows']->as_array() : (array) $page['rows'];
    }

    /**
     * Контрагент-поставщик по идентификатору.
     *
     * @param   int     $supplier_id
     * @return  Model_Erp301_Counterparty|null
     */
    public function counterparty($supplier_id)
    {
        if ( ! $supplier_id OR ! class_exists('Service_Erp301_Counterparties'))
        {
            return NULL;
        }

        try
        {
            $counterparty = Service_Erp301_Counterparties::instance()->get($supplier_id);

            return (int) $counterparty->is_deleted === 1 ? NULL : $counterparty;
        }
        catch (Exception $e)
        {
            return NULL;
        }
    }

    /**
     * Название поставщика для интерфейса (даже если справочник недоступен).
     *
     * @param   int     $supplier_id
     * @return  string
     */
    public function name($supplier_id)
    {
        if ( ! $supplier_id)
        {
            return '';
        }

        $counterparty = $this->counterparty($supplier_id);

        if ($counterparty)
        {
            return (string) $counterparty->name;
        }

        $profile = $this->profile($supplier_id, FALSE);

        if ($profile AND $profile->note)
        {
            return 'Поставщик #'.$supplier_id;
        }

        return 'Поставщик #'.$supplier_id;
    }

    /**
     * Закупочные условия поставщика (создаются по требованию).
     *
     * @param   int     $supplier_id
     * @param   bool    $create
     * @return  Model_Erp403_Supplier|null
     */
    public function profile($supplier_id, $create = TRUE)
    {
        if ( ! $supplier_id)
        {
            return NULL;
        }

        $profile = ORM::factory('Erp403_Supplier')
            ->where('supplier_id', '=', (int) $supplier_id)
            ->find();

        if ($profile->loaded())
        {
            return $profile;
        }

        if ( ! $create)
        {
            return NULL;
        }

        $profile = ORM::factory('Erp403_Supplier');
        $profile->supplier_id    = (int) $supplier_id;
        $profile->lead_time_days = 7;
        $profile->currency       = 'RUB';
        $profile->min_order      = 0;
        $profile->rating         = 3;
        $profile->is_preferred   = 0;
        $profile->is_active      = 1;
        $profile->save();

        return $profile;
    }

    /**
     * Сохранить закупочные условия поставщика.
     *
     * @param   int         $supplier_id
     * @param   array       $data
     * @param   int|null    $user_id
     * @return  Model_Erp403_Supplier
     * @throws  Exception_Erp403_Procurement
     */
    public function save_profile($supplier_id, array $data, $user_id = NULL)
    {
        if ( ! $this->counterparty($supplier_id))
        {
            throw new Exception_Erp403_Procurement('Поставщик #:id не найден в справочнике контрагентов', array(':id' => (int) $supplier_id), 'not_found');
        }

        $profile = $this->profile($supplier_id, TRUE);
        $before  = $profile->as_array();

        if (isset($data['lead_time_days']))
        {
            $profile->lead_time_days = max(0, (int) $data['lead_time_days']);
        }

        if ( ! empty($data['currency']))
        {
            $profile->currency = (string) $data['currency'];
        }

        if (isset($data['min_order']))
        {
            $profile->min_order = Erp::num($data['min_order']);
        }

        if (array_key_exists('payment_terms', $data))
        {
            $profile->payment_terms = $data['payment_terms'] === '' ? NULL : (string) $data['payment_terms'];
        }

        if (isset($data['rating']))
        {
            $profile->rating = min(5, max(1, (int) $data['rating']));
        }

        foreach (array('is_preferred', 'is_active') as $flag)
        {
            if (array_key_exists($flag, $data))
            {
                $profile->$flag = Erp::bool($data[$flag]) ? 1 : 0;
            }
        }

        if (array_key_exists('note', $data))
        {
            $profile->note = $data['note'] === '' ? NULL : (string) $data['note'];
        }

        $profile->save();

        $this->_journal($supplier_id, 'update', $before, $profile->as_array(), 'Изменены закупочные условия поставщика', $user_id);

        Event::fire('erp403.supplier.updated', array(
            'supplier_id' => (int) $supplier_id,
            'lead_time'   => $profile->lead_time(),
            'preferred'   => $profile->is_preferred(),
        ));

        return $profile;
    }

    /**
     * История цен по номенклатуре: последние записи по всем поставщикам.
     *
     * @param   int     $item_id
     * @param   int     $limit
     * @return  array   строки erp_403_prices
     */
    public function history($item_id, $limit = 50)
    {
        $rows = DB::select()
            ->from(self::PRICES)
            ->where('item_id', '=', (int) $item_id)
            ->order_by('dated_at', 'DESC')
            ->order_by('id', 'DESC')
            ->limit((int) $limit)
            ->execute()
            ->as_array();

        foreach ($rows as &$row)
        {
            $row['supplier_name'] = $this->name((int) $row['supplier_id']);
        }
        unset($row);

        return $rows;
    }

    /**
     * Последние цены поставщика (что он поставляет и по чём).
     *
     * @param   int     $supplier_id
     * @param   int     $limit
     * @return  array
     */
    public function supplier_prices($supplier_id, $limit = 50)
    {
        return DB::select()
            ->from(self::PRICES)
            ->where('supplier_id', '=', (int) $supplier_id)
            ->order_by('dated_at', 'DESC')
            ->order_by('id', 'DESC')
            ->limit((int) $limit)
            ->execute()
            ->as_array();
    }

    /**
     * Сравнение поставщиков по позиции: цена, срок, оценка, балл.
     *
     * @param   int         $item_id
     * @param   float|null  $qty
     * @return  array       строки с ключами supplier_id, name, price, lead_time, rating, score, reasons[]
     */
    public function compare($item_id, $qty = NULL)
    {
        $rows = array();

        foreach ($this->stats_by_item($item_id) as $supplier_id => $stat)
        {
            $profile = $this->profile($supplier_id, FALSE);
            $rating  = $profile ? (int) $profile->rating : 3;
            $lead    = $profile ? $profile->lead_time() : 7;

            $score   = 0.0;
            $reasons = array();

            // Цена: чем дешевле относительно лучшей, тем больше баллов (до 50).
            $best_price = $this->best_price($item_id);

            if ($best_price > 0 AND $stat['last_price'] > 0)
            {
                $score += 50 * min(1, $best_price / $stat['last_price']);
                $reasons[] = 'цена '.number_format($stat['last_price'], 2, '.', ' ').' ₽ (лучшая '.number_format($best_price, 2, '.', ' ').' ₽)';
            }
            else
            {
                $score += 20;
                $reasons[] = 'цена неизвестна';
            }

            // Срок: 10 дней — 30 баллов, 30 дней — 0.
            $score += max(0, 30 - ($lead - 10) * 1.5);
            $reasons[] = 'срок поставки '.$lead.' дн.';

            // Оценка закупщика: до 15 баллов.
            $score += $rating * 3;
            $reasons[] = 'оценка '.$rating.'/5';

            // Исполнительность: до 15 баллов.
            $on_time = $this->on_time_rate($supplier_id);
            $score  += $on_time !== NULL ? $on_time / 100 * 15 : 5;
            $reasons[] = $on_time !== NULL ? 'в срок '.round($on_time).' % приёмок' : 'поставок ещё не было';

            if ($profile AND $profile->is_preferred())
            {
                $score += 10;
                $reasons[] = 'предпочтительный поставщик';
            }

            if ($qty !== NULL AND $profile AND (float) $profile->min_order > 0 AND (float) $qty < (float) $profile->min_order)
            {
                $score -= 20;
                $reasons[] = 'партия меньше минимальной ('.number_format((float) $profile->min_order, 2, '.', ' ').' ₽)';
            }

            $rows[] = array(
                'supplier_id'   => (int) $supplier_id,
                'name'          => $this->name($supplier_id),
                'price'         => $stat['last_price'],
                'best_price'    => $stat['min_price'],
                'lead_time'     => $lead,
                'rating'        => $rating,
                'on_time'       => $on_time,
                'min_order'     => $profile ? (float) $profile->min_order : 0.0,
                'currency'      => $profile ? (string) $profile->currency : 'RUB',
                'preferred'     => $profile ? $profile->is_preferred() : FALSE,
                'deals'         => $stat['deals'],
                'last_dated_at' => $stat['last_dated_at'],
                'score'         => round($score, 1),
                'reasons'       => $reasons,
            );
        }

        usort($rows, function ($a, $b) {
            if ($a['score'] === $b['score'])
            {
                return strcmp((string) $a['name'], (string) $b['name']);
            }

            return ($a['score'] < $b['score']) ? 1 : -1;
        });

        return $rows;
    }

    /**
     * Рекомендация поставщика по позиции.
     *
     * @param   int         $item_id
     * @param   float|null  $qty
     * @return  array|null  лучший вариант из `compare()` плюс alternatives
     */
    public function choose($item_id, $qty = NULL)
    {
        $rows = $this->compare($item_id, $qty);

        if ( ! $rows)
        {
            return NULL;
        }

        $best = $rows[0];
        $best['alternatives'] = array_slice($rows, 1, 3);

        return $best;
    }

    /**
     * Статистика по поставщикам позиции: цена и количество сделок.
     *
     * @param   int     $item_id
     * @return  array   supplier_id => array(last_price, min_price, deals, last_dated_at)
     */
    public function stats_by_item($item_id)
    {
        $stats = array();

        foreach (DB::select()
            ->from(self::PRICES)
            ->where('item_id', '=', (int) $item_id)
            ->order_by('dated_at', 'ASC')
            ->order_by('id', 'ASC')
            ->execute()
            ->as_array() as $row)
        {
            $supplier_id = (int) $row['supplier_id'];
            $price       = (float) $row['price'];

            if ( ! isset($stats[$supplier_id]))
            {
                $stats[$supplier_id] = array(
                    'last_price'    => $price,
                    'min_price'     => $price,
                    'deals'         => 0,
                    'last_dated_at' => (string) $row['dated_at'],
                );
            }

            $stats[$supplier_id]['last_price']    = $price;
            $stats[$supplier_id]['min_price']     = min($stats[$supplier_id]['min_price'], $price);
            $stats[$supplier_id]['deals']++;
            $stats[$supplier_id]['last_dated_at'] = (string) $row['dated_at'];
        }

        return $stats;
    }

    /**
     * Лучшая (минимальная) известная цена позиции.
     *
     * @param   int     $item_id
     * @return  float
     */
    public function best_price($item_id)
    {
        $row = DB::select(array(DB::expr('MIN(price)'), 'price'))
            ->from(self::PRICES)
            ->where('item_id', '=', (int) $item_id)
            ->execute()
            ->current();

        return $row ? (float) $row['price'] : 0.0;
    }

    /**
     * Доля приёмок, пришедших не позже срока поставки заказа, в процентах.
     *
     * @param   int     $supplier_id
     * @return  float|null  NULL — поставок ещё не было
     */
    public function on_time_rate($supplier_id)
    {
        $row = DB::select(
                array(DB::expr('COUNT(*)'), 'total'),
                array(DB::expr('COUNT(*) FILTER (WHERE r.received_at <= o.delivery_date OR o.delivery_date IS NULL)'), 'in_time')
            )
            ->from(array('erp_403_receipts', 'r'))
            ->join(array('erp_403_orders', 'o'), 'LEFT')
            ->on('o.id', '=', 'r.order_id')
            ->where('r.status', '=', 'posted')
            ->where('r.supplier_id', '=', (int) $supplier_id)
            ->execute()
            ->current();

        $total = (int) $row['total'];

        if ($total === 0)
        {
            return NULL;
        }

        return round((int) $row['in_time'] / $total * 100, 1);
    }

    /**
     * Сколько позиций поставщик уже поставлял.
     *
     * @param   int     $supplier_id
     * @return  int
     */
    public function goods_count($supplier_id)
    {
        $row = DB::select(array(DB::expr('COUNT(DISTINCT item_id)'), 'total'))
            ->from(self::PRICES)
            ->where('supplier_id', '=', (int) $supplier_id)
            ->where('item_id', 'IS NOT', NULL)
            ->execute()
            ->current();

        return (int) $row['total'];
    }

    /**
     * Когда поставщик последний раз давал цену.
     *
     * @param   int     $supplier_id
     * @return  string|null
     */
    public function last_price_date($supplier_id)
    {
        $row = DB::select('dated_at')
            ->from(self::PRICES)
            ->where('supplier_id', '=', (int) $supplier_id)
            ->order_by('dated_at', 'DESC')
            ->limit(1)
            ->execute()
            ->current();

        return $row ? (string) $row['dated_at'] : NULL;
    }

    /**
     * Записать цену в историю (из заказа, приёмки или вручную).
     *
     * @param   array       $data   supplier_id, item_id, name, price, qty,
     *                              currency, uom_code, source, order_id,
     *                              receipt_id, dated_at, note
     * @param   int|null    $user_id
     * @return  Model_Erp403_Price
     * @throws  Exception_Erp403_Procurement
     */
    public function record_price(array $data, $user_id = NULL)
    {
        if (empty($data['supplier_id']))
        {
            throw new Exception_Erp403_Procurement('История цен: не указан поставщик', NULL, 'supplier_required');
        }

        if ( ! isset($data['price']) OR Erp::num($data['price']) <= 0)
        {
            throw new Exception_Erp403_Procurement('История цен: цена должна быть больше нуля', NULL, 'not_found');
        }

        $price = ORM::factory('Erp403_Price');

        $price->supplier_id = (int) $data['supplier_id'];
        $price->item_id     = ! empty($data['item_id']) ? (int) $data['item_id'] : NULL;
        $price->name        = isset($data['name']) ? (string) $data['name'] : '';
        $price->price       = Erp::num($data['price']);
        $price->qty         = isset($data['qty']) ? Erp::num($data['qty'], 1) : 1;
        $price->currency    = ! empty($data['currency']) ? (string) $data['currency'] : 'RUB';
        $price->uom_code    = ! empty($data['uom_code']) ? (string) $data['uom_code'] : 'шт';
        $price->source      = ! empty($data['source']) ? (string) $data['source'] : 'manual';
        $price->order_id    = ! empty($data['order_id']) ? (int) $data['order_id'] : NULL;
        $price->receipt_id  = ! empty($data['receipt_id']) ? (int) $data['receipt_id'] : NULL;
        $price->dated_at    = ! empty($data['dated_at']) ? Erp::day($data['dated_at']) : date('Y-m-d');
        $price->note        = isset($data['note']) ? (string) $data['note'] : NULL;
        $price->created_by  = $user_id ? (int) $user_id : NULL;
        $price->save();

        Event::fire('erp403.price.recorded', array(
            'supplier_id' => (int) $price->supplier_id,
            'item_id'     => $price->item_id ? (int) $price->item_id : NULL,
            'price'       => (float) $price->price,
            'source'      => (string) $price->source,
        ));

        return $price;
    }

    /**
     * Сводка по поставщикам и ценам для доски закупок.
     *
     * @return  array
     */
    public function stats()
    {
        $supplier_row = DB::select(
                array(DB::expr('COUNT(*)'), 'total'),
                array(DB::expr('COUNT(*) FILTER (WHERE is_preferred = 1)'), 'preferred'),
                array(DB::expr('COUNT(*) FILTER (WHERE is_active = 0)'), 'disabled'),
                array(DB::expr('COALESCE(AVG(lead_time_days), 0)'), 'avg_lead_time')
            )
            ->from(self::TABLE)
            ->execute()
            ->current();

        $price_row = DB::select(
                array(DB::expr('COUNT(*)'), 'total'),
                array(DB::expr('COUNT(DISTINCT supplier_id)'), 'suppliers'),
                array(DB::expr('COUNT(DISTINCT item_id)'), 'items'),
                array(DB::expr('MAX(created_at)'), 'last_at')
            )
            ->from(self::PRICES)
            ->execute()
            ->current();

        return array(
            'suppliers'      => count($this->counterparties(array(), 1000, 0)),
            'profiles'       => (int) $supplier_row['total'],
            'preferred'      => (int) $supplier_row['preferred'],
            'disabled'       => (int) $supplier_row['disabled'],
            'avg_lead_time'  => round((float) $supplier_row['avg_lead_time'], 1),
            'prices'         => (int) $price_row['total'],
            'priced_items'   => (int) $price_row['items'],
            'last_price_at'  => $price_row['last_at'] ? (string) $price_row['last_at'] : NULL,
        );
    }

    /**
     * Поставщик с ценой лучше текущей — повод для отчёта «экономия».
     *
     * @param   int     $limit
     * @return  array
     */
    public function savings($limit = 10)
    {
        $rows = array();

        foreach (DB::select('item_id', array(DB::expr('MAX(price)'), 'max_price'), array(DB::expr('MIN(price)'), 'min_price'))
            ->from(self::PRICES)
            ->where('item_id', 'IS NOT', NULL)
            ->group_by('item_id')
            ->having(DB::expr('COUNT(DISTINCT supplier_id)'), '>', 1)
            ->order_by(DB::expr('(MAX(price) - MIN(price)) * MAX(price)'), 'DESC')
            ->limit((int) $limit)
            ->execute()
            ->as_array() as $row)
        {
            $item_id = (int) $row['item_id'];

            $rows[] = array(
                'item_id'    => $item_id,
                'name'       => $this->item_name($item_id),
                'min_price'  => (float) $row['min_price'],
                'max_price'  => (float) $row['max_price'],
                'diff'       => round((float) $row['max_price'] - (float) $row['min_price'], 2),
                'diff_pct'   => (float) $row['max_price'] > 0
                    ? round(((float) $row['max_price'] - (float) $row['min_price']) / (float) $row['max_price'] * 100, 1)
                    : 0.0,
            );
        }

        return $rows;
    }

    /**
     * Наименование позиции номенклатуры (реестр erp227).
     *
     * @param   int     $item_id
     * @return  string
     */
    public function item_name($item_id)
    {
        if ( ! $item_id OR ! class_exists('Service_Erp227_Registry'))
        {
            return 'Позиция #'.$item_id;
        }

        try
        {
            $object = Service_Erp227_Registry::instance()->get_by_id((int) $item_id);

            return $object->loaded() ? (string) $object->name : 'Позиция #'.$item_id;
        }
        catch (Exception $e)
        {
            return 'Позиция #'.$item_id;
        }
    }

    /**
     * Запись в журнал действий (erp119).
     *
     * @param   int         $supplier_id
     * @param   string      $action
     * @param   array       $before
     * @param   array       $after
     * @param   string|null $message
     * @param   int|null    $user_id
     * @return  void
     */
    protected function _journal($supplier_id, $action, array $before, array $after, $message = NULL, $user_id = NULL)
    {
        if ( ! class_exists('Service_Erp119_Journal'))
        {
            return;
        }

        $options = array(
            'module'       => 'erp403',
            'entity_label' => $this->name($supplier_id),
            'message'      => $message,
        );

        if ($user_id)
        {
            // Журнал пишет «кто» из сессии; для API и MCP актора передают явно.
            $options['actor_id'] = (int) $user_id;
        }

        try
        {
            Service_Erp119_Journal::instance()->change('supplier', (string) (int) $supplier_id, $action, $before, $after, $options);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp403: журнал действий недоступен — :error', array(':error' => $e->getMessage()));
        }
    }
}
