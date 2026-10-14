<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Цены продажи (erp410_shipment).
 *
 * Прайс — не таблица, а история: у позиции может быть базовая цена и
 * договорные цены отдельных клиентов, и каждая запись живёт со своей датой.
 * Сервис отвечает на вопрос «сколько стоит эта позиция этому покупателю
 * сегодня» — и всегда возвращает цену вместе с основанием, чтобы в карточке
 * заказа было видно, откуда она взялась.
 *
 * Порядок поиска цены:
 *
 *   1. договорная цена клиента (последняя по дате);
 *   2. базовая цена прайса (последняя по дате);
 *   3. цена последней отгрузки этому клиенту (исторический факт);
 *   4. 0 — и вызывающий видит, что цены нет, а не выдуманное число.
 *
 * Записи пишутся и из документов (`source = order`, `shipment`, `invoice`): так
 * у позиции шаг за шагом появляется рыночная история, а не один прайс.
 *
 * @package    Erp410
 * @category   Services
 */
class Service_Erp410_Prices
{
    const TABLE = 'erp_410_prices';

    /** @var Service_Erp410_Prices|null */
    protected static $_instance;

    /**
     * @return  Service_Erp410_Prices
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    protected function __construct() {}

    // =========================================================================
    //  Чтение
    // =========================================================================

    /**
     * Цена позиции для клиента: запись прайса и основание.
     *
     * @param   int         $item_id
     * @param   int|null    $customer_id
     * @return  array       price, discount, source, source_label, price_id, basis
     */
    public function price_for($item_id, $customer_id = NULL)
    {
        $item_id = (int) $item_id;

        if ($item_id <= 0)
        {
            return $this->_empty();
        }

        if ($customer_id)
        {
            $contract = $this->_last_price($item_id, (int) $customer_id);

            if ($contract)
            {
                return $this->_result($contract, 'договорная цена клиента');
            }
        }

        $base = $this->_last_price($item_id, NULL);

        if ($base)
        {
            return $this->_result($base, 'базовая цена прайса');
        }

        $history = $this->last_document_price($item_id, $customer_id);

        if ($history)
        {
            return array(
                'price_id'      => NULL,
                'price'         => (float) $history['price'],
                'discount'      => (float) $history['discount'],
                'net_price'     => round((float) $history['price'] * (1 - (float) $history['discount'] / 100), 6),
                'currency'      => (string) $history['currency'],
                'source'        => 'history',
                'source_label'  => 'Последняя отгрузка',
                'basis'         => 'цена последней отгрузки этой позиции',
                'valid_from'    => $history['created_at'],
                'found'         => TRUE,
            );
        }

        return $this->_empty();
    }

    /**
     * Цены позиции: договорные и базовая, свежие сверху.
     *
     * @param   int     $item_id
     * @param   int     $limit
     * @return  array   Model_Erp410_Price
     */
    public function history($item_id, $limit = 50)
    {
        return ORM::factory('Erp410_Price')
            ->where('item_id', '=', (int) $item_id)
            ->order_by('created_at', 'DESC')
            ->order_by('id', 'DESC')
            ->limit(max(1, min(200, (int) $limit)))
            ->find_all()
            ->as_array();
    }

    /**
     * Цены клиента (для карточки клиента).
     *
     * @param   int     $customer_id
     * @param   int     $limit
     * @return  array   Model_Erp410_Price
     */
    public function for_customer($customer_id, $limit = 50)
    {
        return ORM::factory('Erp410_Price')
            ->where('customer_id', '=', (int) $customer_id)
            ->order_by('created_at', 'DESC')
            ->limit(max(1, min(200, (int) $limit)))
            ->find_all()
            ->as_array();
    }

    /**
     * Последняя цена отгрузки (или счёта) по позиции — исторический факт.
     *
     * @param   int         $item_id
     * @param   int|null    $customer_id
     * @return  array|null
     */
    public function last_document_price($item_id, $customer_id = NULL)
    {
        $query = DB::select()
            ->from('erp_410_shipment_lines')
            ->where('item_id', '=', (int) $item_id)
            ->order_by('created_at', 'DESC')
            ->order_by('id', 'DESC')
            ->limit(1);

        if ($customer_id)
        {
            $ids = $this->_customer_shipments((int) $customer_id);

            if ( ! $ids)
            {
                return NULL;
            }

            $query->where('shipment_id', 'IN', $ids);
        }

        $row = $query->execute()->current();

        if ( ! $row)
        {
            return NULL;
        }

        return array(
            'price'      => (float) $row['price'],
            'discount'   => 0,
            'currency'   => 'RUB',
            'created_at' => (string) $row['created_at'],
        );
    }

    /**
     * Сколько записей прайса.
     *
     * @return  array
     */
    public function stats()
    {
        $row = DB::select(
                array(DB::expr('COUNT(*)'), 'total'),
                array(DB::expr("COUNT(*) FILTER (WHERE customer_id IS NULL)"), 'base'),
                array(DB::expr("COUNT(*) FILTER (WHERE customer_id IS NOT NULL)"), 'contract')
            )
            ->from(self::TABLE)
            ->execute()
            ->current();

        return array(
            'total'    => (int) $row['total'],
            'base'     => (int) $row['base'],
            'contract' => (int) $row['contract'],
        );
    }

    // =========================================================================
    //  Запись
    // =========================================================================

    /**
     * Записать цену (прайс или цена клиента).
     *
     * @param   array       $data   item_id, customer_id, price, discount, currency, source, source_ref, valid_from
     * @param   int|null    $user_id
     * @return  Model_Erp410_Price
     * @throws  Exception_Erp410_Shipment
     */
    public function record(array $data, $user_id = NULL)
    {
        $item_id = isset($data['item_id']) ? (int) $data['item_id'] : 0;

        if ($item_id <= 0)
        {
            throw new Exception_Erp410_Shipment('Цена: укажите позицию номенклатуры', NULL, 'item_required');
        }

        $price = Erp::num(isset($data['price']) ? $data['price'] : 0);

        if ($price < 0)
        {
            throw new Exception_Erp410_Shipment('Цена не может быть отрицательной', NULL, 'invalid_price');
        }

        $entry = ORM::factory('Erp410_Price');

        $entry->item_id     = $item_id;
        $entry->customer_id = ! empty($data['customer_id']) ? (int) $data['customer_id'] : NULL;
        $entry->price       = $price;
        $entry->currency    = ! empty($data['currency']) ? (string) $data['currency'] : 'RUB';
        $entry->discount    = isset($data['discount']) ? min(100, max(0, Erp::num($data['discount']))) : 0;
        $entry->source      = ! empty($data['source']) ? (string) $data['source'] : 'manual';
        $entry->source_ref  = ! empty($data['source_ref']) ? (string) $data['source_ref'] : NULL;
        $entry->valid_from  = ! empty($data['valid_from']) ? Erp::day($data['valid_from']) : date('Y-m-d');
        $entry->created_by  = $user_id ? (int) $user_id : NULL;
        $entry->save();

        Event::fire('erp410.price.recorded', array(
            'price_id'     => (int) $entry->id,
            'item_id'      => (int) $entry->item_id,
            'customer_id'  => $entry->customer_id ? (int) $entry->customer_id : NULL,
            'price'        => (float) $entry->price,
            'discount'     => (float) $entry->discount,
            'source'       => (string) $entry->source,
            'source_ref'   => $entry->source_ref ? (string) $entry->source_ref : NULL,
            'title'        => 'Цена продажи записана',
        ));

        return $entry;
    }

    /**
     * Записать цену из документа (заказ, отгрузка, счёт) — без дублей:
     * повторная запись той же позиции с той же ценой в тот же день не нужна.
     *
     * @param   string      $source
     * @param   string      $source_ref
     * @param   int         $item_id
     * @param   float       $price
     * @param   float       $discount
     * @param   int|null    $customer_id
     * @param   int|null    $user_id
     * @return  Model_Erp410_Price|null
     */
    public function record_from_document($source, $source_ref, $item_id, $price, $discount = 0, $customer_id = NULL, $user_id = NULL)
    {
        if ( ! $item_id OR Erp::num($price) <= 0)
        {
            return NULL;
        }

        $exists = DB::select('id')
            ->from(self::TABLE)
            ->where('item_id', '=', (int) $item_id)
            ->where('source', '=', (string) $source)
            ->where('source_ref', '=', (string) $source_ref)
            ->limit(1)
            ->execute()
            ->current();

        if ($exists)
        {
            return NULL;
        }

        return $this->record(array(
            'item_id'     => $item_id,
            'customer_id' => $customer_id,
            'price'       => $price,
            'discount'    => $discount,
            'source'      => $source,
            'source_ref'  => $source_ref,
        ), $user_id);
    }

    // =========================================================================
    //  Служебное
    // =========================================================================

    /**
     * Последняя запись прайса по позиции и клиенту.
     *
     * @param   int         $item_id
     * @param   int|null    $customer_id
     * @return  Model_Erp410_Price|null
     */
    protected function _last_price($item_id, $customer_id)
    {
        $query = ORM::factory('Erp410_Price')->where('item_id', '=', (int) $item_id);

        if ($customer_id === NULL)
        {
            $query->where('customer_id', 'IS', DB::expr('NULL'));
        }
        else
        {
            $query->where('customer_id', '=', (int) $customer_id);
        }

        $entry = $query
            ->order_by(DB::expr('COALESCE(valid_from, created_at::date)'), 'DESC')
            ->order_by('id', 'DESC')
            ->find();

        return $entry->loaded() ? $entry : NULL;
    }

    /**
     * @param   Model_Erp410_Price  $entry
     * @param   string              $basis
     * @return  array
     */
    protected function _result(Model_Erp410_Price $entry, $basis)
    {
        return array(
            'price_id'     => (int) $entry->id,
            'customer_id'  => $entry->customer_id ? (int) $entry->customer_id : NULL,
            'price'        => (float) $entry->price,
            'discount'     => (float) $entry->discount,
            'net_price'    => $entry->net_price(),
            'currency'     => (string) $entry->currency,
            'source'       => (string) $entry->source,
            'source_label' => $entry->source_label(),
            'basis'        => $basis,
            'valid_from'   => $entry->valid_from ? (string) $entry->valid_from : NULL,
            'found'        => TRUE,
        );
    }

    /**
     * @return  array
     */
    protected function _empty()
    {
        return array(
            'price_id'     => NULL,
            'price'        => 0.0,
            'discount'     => 0.0,
            'net_price'    => 0.0,
            'currency'     => 'RUB',
            'source'       => NULL,
            'source_label' => NULL,
            'basis'        => 'цены нет: ни прайса, ни истории',
            'valid_from'   => NULL,
            'found'        => FALSE,
        );
    }

    /**
     * Идентификаторы отгрузок клиента (для истории цен).
     *
     * @param   int     $customer_id
     * @return  array
     */
    protected function _customer_shipments($customer_id)
    {
        $rows = DB::select('id')
            ->from('erp_410_shipments')
            ->where('customer_id', '=', (int) $customer_id)
            ->execute()
            ->as_array(NULL, 'id');

        return array_map('intval', $rows);
    }
}
