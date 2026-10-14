<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Витрина закупок (erp801_analytics).
 *
 * Директору важно не «сколько заказов в базе», а сколько из них висит с
 * просроченной датой поставки и на какую сумму. Поэтому здесь сводка владельца
 * (`Service_Erp403_Orders::stats()`) превращается в показатели с целью
 * (допустимая доля просрочки из настроек) и короткие списки: что просрочено и
 * что подходит к сроку.
 *
 * Имена поставщиков аналитика спрашивает у закупок, а не у своей копии
 * справочника: контрагент живёт в erp301, и вторая правда о его названии
 * разошлась бы с первой на первой же правке.
 *
 * @package    Erp801
 * @category   Services
 */
class Service_Erp801_Purchases
{
    /** @var Service_Erp801_Purchases|null */
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
     * @return  Service_Erp801_Purchases
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
     * Панель закупок: состояние, просрочка, ближайшие сроки.
     *
     * @param   array   $filters  limit, due_days
     * @return  array
     */
    public function panel(array $filters = array())
    {
        $orders = $this->_service();
        $limit  = ! empty($filters['limit']) ? min(50, max(1, (int) $filters['limit'])) : 10;
        $due    = ! empty($filters['due_days']) ? max(1, (int) $filters['due_days']) : 7;

        $stats = method_exists($orders, 'stats') ? (array) $orders->stats() : array();

        $open    = isset($stats['open']) ? (int) $stats['open'] : 0;
        $overdue = isset($stats['overdue']) ? (int) $stats['overdue'] : 0;

        $share  = $open > 0 ? round($overdue / $open * 100, 1) : NULL;
        $target = (float) $this->_settings->get('overdue_target', 10);

        return array(
            'stats'         => $stats,
            'open'          => $open,
            'overdue'       => $overdue,
            'overdue_share' => $share,
            'target'        => $target,
            'state'         => $this->_state($share, $target),
            'overdue_rows'  => $this->_rows($orders, array('overdue' => TRUE), $limit),
            'due_rows'      => $this->_rows($orders, array('open' => TRUE), $limit, $due),
            'due_days'      => $due,
        );
    }

    /**
     * KPI закупок для сводки.
     *
     * @param   array   $filters
     * @return  array
     */
    public function summary(array $filters = array())
    {
        $panel = $this->panel($filters);
        $stats = $panel['stats'];

        return array(
            'total'         => isset($stats['total']) ? (int) $stats['total'] : 0,
            'open'          => $panel['open'],
            'overdue'       => $panel['overdue'],
            'overdue_share' => $panel['overdue_share'],
            'target'        => $panel['target'],
            'state'         => $panel['state'],
            'amount_open'   => isset($stats['amount_open']) ? (float) $stats['amount_open'] : 0.0,
            'received'      => isset($stats['received']) ? (int) $stats['received'] : 0,
            'cancelled'     => isset($stats['cancelled']) ? (int) $stats['cancelled'] : 0,
        );
    }

    /**
     * Строки заказов (просроченные или ближайшие) через сервис закупок.
     *
     * @param   object  $orders
     * @param   array   $filters
     * @param   int     $limit
     * @param   int|null $due_days  для ближайших: горизонт в днях
     * @return  array
     */
    protected function _rows($orders, array $filters, $limit, $due_days = NULL)
    {
        if ($due_days !== NULL AND method_exists($orders, 'due_soon'))
        {
            $found = $orders->due_soon($due_days, $limit);

            return $this->_format($this->_to_array($found));
        }

        if (method_exists($orders, 'search'))
        {
            return $this->_format($this->_to_array($orders->search($filters, $limit, 0)));
        }

        return array();
    }

    /**
     * ORM-результат в массив: `iterate_to_array()` на результате ORM не годится —
     * собираем циклом (см. AGENTS.md).
     *
     * @param   mixed   $result
     * @return  array
     */
    protected function _to_array($result)
    {
        if (is_array($result))
        {
            return $result;
        }

        $rows = array();

        foreach ($result as $row)
        {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Подписи и производные поля для списка заказов.
     *
     * @param   array   $orders
     * @return  array
     */
    protected function _format(array $orders)
    {
        $rows    = array();
        $today   = strtotime(date('Y-m-d'));

        foreach ($orders as $order)
        {
            $delivery = isset($order->delivery_date) ? (string) $order->delivery_date : NULL;
            $late     = NULL;

            if ($delivery)
            {
                $late = (int) floor(($today - strtotime(date('Y-m-d', strtotime($delivery)))) / 86400);
            }

            $rows[] = array(
                'id'            => (int) $order->id,
                'number'        => (string) $order->number,
                'uuid'          => (string) $order->uuid,
                'title'         => isset($order->title) ? (string) $order->title : '',
                'status'        => (string) $order->status,
                'status_label'  => method_exists($order, 'status_label') ? $order->status_label() : (string) $order->status,
                'delivery_date' => $delivery,
                'days_late'     => ($late !== NULL AND $late > 0) ? $late : 0,
                'days_left'     => ($late !== NULL AND $late <= 0) ? abs($late) : 0,
                'amount'        => isset($order->amount) ? (float) $order->amount : NULL,
                'supplier_id'   => isset($order->supplier_id) ? (int) $order->supplier_id : NULL,
                'supplier_name' => isset($order->supplier_id) ? $this->_sources->supplier_name((int) $order->supplier_id) : '—',
                'url'           => '/procurement/orders/view/'.(int) $order->id,
            );
        }

        return $rows;
    }

    /**
     * Сервис закупок или честная ошибка витрины.
     *
     * @return  object
     */
    protected function _service()
    {
        $orders = $this->_sources->purchases();

        if ( ! $orders)
        {
            throw $this->_sources->missing('purchases');
        }

        return $orders;
    }

    /**
     * Состояние доли просрочки относительно цели.
     *
     * @param   float   $share
     * @param   float   $target
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
