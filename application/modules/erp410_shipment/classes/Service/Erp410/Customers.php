<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Клиенты и их условия продажи (erp410_shipment).
 *
 * Покупателей модуль не заводит: это контрагенты erp301 с ролью «покупатель».
 * Здесь живёт только торговый профиль — отсрочка, кредитный лимит, скидка,
 * адрес доставки. Контрагент без профиля тоже продаётся (условия по умолчанию),
 * поэтому профиль — дополнение, а не пропуск в продажу.
 *
 * Кредитный лимит — не украшение карточки: `credit_exposure()` считает, сколько
 * клиент уже должен (выставленные и не оплаченные счета), а
 * `credit_check()` отвечает, влезает ли в лимит новая сумма. Решение принимает
 * сервис заказов, здесь — факты.
 *
 * @package    Erp410
 * @category   Services
 */
class Service_Erp410_Customers
{
    const TABLE = 'erp_410_customers';

    /** @var Service_Erp410_Customers|null */
    protected static $_instance;

    /**
     * @return  Service_Erp410_Customers
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
     * Контрагенты с ролью «покупатель» и их профили продажи.
     *
     * @param   array   $filters    search, active, with_profile
     * @param   int     $limit
     * @param   int     $offset
     * @return  array   строки: counterparty, profile, name, customer_id
     */
    public function search(array $filters = array(), $limit = 25, $offset = 0)
    {
        $rows = array();

        foreach ($this->counterparties($filters, 1000, 0) as $counterparty)
        {
            $profile = $this->profile((int) $counterparty->id, FALSE);

            if ( ! empty($filters['with_profile']) AND ! $profile)
            {
                continue;
            }

            if ( ! empty($filters['active']) AND $profile AND ! $profile->is_active())
            {
                continue;
            }

            if ( ! empty($filters['search']))
            {
                $term = mb_strtolower(trim((string) $filters['search']));

                if (mb_strpos(mb_strtolower((string) $counterparty->name), $term) === FALSE)
                {
                    continue;
                }
            }

            $rows[] = array(
                'customer_id'  => (int) $counterparty->id,
                'name'         => (string) $counterparty->name,
                'counterparty' => $counterparty,
                'profile'      => $profile,
                'exposure'     => $this->credit_exposure((int) $counterparty->id),
            );
        }

        return array_slice($rows, max(0, (int) $offset), max(1, (int) $limit));
    }

    /**
     * Сколько клиентов подходит под фильтр.
     *
     * @param   array   $filters
     * @return  int
     */
    public function count(array $filters = array())
    {
        return count($this->search($filters, 1000, 0));
    }

    /**
     * Живые контрагенты с ролью «покупатель» (или все, если роль не важна).
     *
     * @param   array   $filters    search, active
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
                'role'   => 'customer',
                'search' => isset($filters['search']) ? $filters['search'] : NULL,
                'status' => isset($filters['status']) ? $filters['status'] : NULL,
            ), max(1, (int) $limit), max(0, (int) $offset));
        }
        catch (Exception $e)
        {
            return array();
        }

        // Сервис контрагентов отдаёт страницу массивом или объектом с rows
        if (is_array($page) AND isset($page['rows']))
        {
            return $page['rows'];
        }

        if ($page instanceof Traversable)
        {
            return iterator_to_array($page);
        }

        return is_array($page) ? $page : array();
    }

    /**
     * Профиль продажи клиента (или NULL, если его ещё не заводили).
     *
     * @param   int     $customer_id
     * @param   bool    $create_missing  создать пустой профиль на лету
     * @return  Model_Erp410_Customer|null
     */
    public function profile($customer_id, $create_missing = FALSE)
    {
        $customer_id = (int) $customer_id;

        if ($customer_id <= 0)
        {
            return NULL;
        }

        $profile = ORM::factory('Erp410_Customer')->where('customer_id', '=', $customer_id)->find();

        if ( ! $profile->loaded())
        {
            if ( ! $create_missing)
            {
                return NULL;
            }

            $profile = ORM::factory('Erp410_Customer');
            $profile->customer_id   = $customer_id;
            $profile->payment_terms = NULL;
            $profile->credit_days   = 0;
            $profile->credit_limit  = 0;
            $profile->discount      = 0;
            $profile->is_active     = 1;
            $profile->save();
        }

        return $profile;
    }

    /**
     * Имя клиента (из справочника контрагентов).
     *
     * @param   int     $customer_id
     * @return  string
     */
    public function name($customer_id)
    {
        if ( ! $customer_id OR ! class_exists('Service_Erp301_Counterparties'))
        {
            return 'Клиент #'.(int) $customer_id;
        }

        try
        {
            $counterparty = Service_Erp301_Counterparties::instance()->get((int) $customer_id);

            return $counterparty ? (string) $counterparty->name : 'Клиент #'.(int) $customer_id;
        }
        catch (Exception $e)
        {
            return 'Клиент #'.(int) $customer_id;
        }
    }

    /**
     * Скидка клиента, % (без профиля — 0).
     *
     * @param   int     $customer_id
     * @return  float
     */
    public function discount($customer_id)
    {
        $profile = $this->profile($customer_id);

        return $profile ? $profile->discount() : 0.0;
    }

    /**
     * Условие оплаты клиента.
     *
     * @param   int     $customer_id
     * @return  string
     */
    public function payment_label($customer_id)
    {
        $profile = $this->profile($customer_id);

        return $profile ? $profile->payment_label() : 'Предоплата';
    }

    /**
     * Отсрочка клиента в днях (для срока оплаты счёта).
     *
     * @param   int     $customer_id
     * @return  int
     */
    public function credit_days($customer_id)
    {
        $profile = $this->profile($customer_id);

        return $profile ? $profile->credit_days() : 0;
    }

    /**
     * Сколько клиент уже должен: выставленные и не оплаченные счета.
     *
     * @param   int     $customer_id
     * @return  float
     */
    public function credit_exposure($customer_id)
    {
        $row = DB::select(array(DB::expr('COALESCE(SUM(amount - paid_amount), 0)'), 'exposure'))
            ->from('erp_410_invoices')
            ->where('customer_id', '=', (int) $customer_id)
            ->where('status', '=', 'issued')
            ->execute()
            ->current();

        return round((float) $row['exposure'], 2);
    }

    /**
     * Влезает ли сумма в кредитный лимит клиента.
     *
     * @param   int     $customer_id
     * @param   float   $amount
     * @return  array   allowed, limit, exposure, available, reason
     */
    public function credit_check($customer_id, $amount)
    {
        $profile = $this->profile($customer_id);

        if ( ! $profile OR (float) $profile->credit_limit <= 0)
        {
            // Лимит не задан — продажа работает по предоплате: проверять нечего.
            return array(
                'allowed'   => TRUE,
                'limit'     => 0.0,
                'exposure'  => $this->credit_exposure($customer_id),
                'available' => 0.0,
                'reason'    => 'лимит не задан',
            );
        }

        $limit    = round((float) $profile->credit_limit, 2);
        $exposure = $this->credit_exposure($customer_id);
        $available = round($limit - $exposure, 2);

        $allowed = ($amount <= $available);

        return array(
            'allowed'   => $allowed,
            'limit'     => $limit,
            'exposure'  => $exposure,
            'available' => $available,
            'reason'    => $allowed
                ? 'в пределах лимита'
                : 'лимит исчерпан: свободно '.number_format($available, 2, '.', ' ').' ₽, запрошено '.number_format((float) $amount, 2, '.', ' ').' ₽',
        );
    }

    /**
     * Сводка по клиентам.
     *
     * @return  array
     */
    public function stats()
    {
        $customers = count($this->counterparties(array(), 1000, 0));

        $row = DB::select(
                array(DB::expr('COUNT(*)'), 'profiles'),
                array(DB::expr('COALESCE(SUM(credit_limit), 0)'), 'limit_total'),
                array(DB::expr("COUNT(*) FILTER (WHERE is_active = 1)"), 'active')
            )
            ->from(self::TABLE)
            ->execute()
            ->current();

        $exposure = DB::select(array(DB::expr('COALESCE(SUM(amount - paid_amount), 0)'), 'exposure'))
            ->from('erp_410_invoices')
            ->where('status', '=', 'issued')
            ->execute()
            ->current();

        return array(
            'counterparties' => $customers,
            'profiles'       => (int) $row['profiles'],
            'active'         => (int) $row['active'],
            'limit_total'    => round((float) $row['limit_total'], 2),
            'exposure'       => round((float) $exposure['exposure'], 2),
            'without_profile' => max(0, $customers - (int) $row['profiles']),
        );
    }

    // =========================================================================
    //  Запись
    // =========================================================================

    /**
     * Сохранить условия клиента.
     *
     * @param   int         $customer_id
     * @param   array       $data
     * @param   int|null    $user_id
     * @return  Model_Erp410_Customer
     * @throws  Exception_Erp410_Shipment
     */
    public function save($customer_id, array $data, $user_id = NULL)
    {
        $customer_id = (int) $customer_id;

        if ($customer_id <= 0)
        {
            throw new Exception_Erp410_Shipment('Условия клиента: укажите контрагента', NULL, 'customer_required');
        }

        $profile = $this->profile($customer_id, TRUE);

        if (isset($data['payment_terms']))
        {
            $profile->payment_terms = trim((string) $data['payment_terms']) !== '' ? (string) $data['payment_terms'] : NULL;
        }

        if (isset($data['credit_days']))
        {
            $profile->credit_days = max(0, (int) $data['credit_days']);
        }

        if (isset($data['credit_limit']))
        {
            $profile->credit_limit = max(0, Erp::num($data['credit_limit']));
        }

        if (isset($data['discount']))
        {
            $profile->discount = min(100, max(0, Erp::num($data['discount'])));
        }

        if (array_key_exists('manager_id', $data))
        {
            $profile->manager_id = ! empty($data['manager_id']) ? (int) $data['manager_id'] : NULL;
        }

        if (array_key_exists('delivery_address', $data))
        {
            $profile->delivery_address = trim((string) $data['delivery_address']) !== '' ? (string) $data['delivery_address'] : NULL;
        }

        if (array_key_exists('terms', $data))
        {
            $profile->terms = trim((string) $data['terms']) !== '' ? (string) $data['terms'] : NULL;
        }

        if (array_key_exists('is_active', $data))
        {
            $profile->is_active = Erp::bool($data['is_active']) ? 1 : 0;
        }

        $profile->save();

        Event::fire('erp410.customer.updated', array(
            'customer_id'  => $customer_id,
            'profile_id'   => (int) $profile->id,
            'credit_days'  => $profile->credit_days(),
            'credit_limit' => (float) $profile->credit_limit,
            'discount'     => $profile->discount(),
            'is_active'    => $profile->is_active(),
            'title'        => 'Условия клиента обновлены',
        ));

        return $profile;
    }
}
