<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контрагент: поставщик, покупатель, перевозчик или подрядчик (erp301_counterparty).
 *
 * Один контрагент может совмещать роли (например, поставщик и покупатель),
 * поэтому роли хранятся массивом в JSONB-поле roles.
 *
 * @package    Erp301
 * @category   Models
 *
 * @property int    $id
 * @property string $code       Краткий код (уникальный)
 * @property string $name       Краткое наименование
 * @property string $full_name  Полное наименование для документов
 * @property string $kind       legal|individual|state|foreign
 * @property array  $roles      supplier|customer|carrier|contractor
 * @property string $inn
 * @property string $kpp
 * @property string $ogrn
 * @property string $status     active|blocked|archived
 * @property int    $rating     0..5
 */
class Model_Erp301_Counterparty extends ORM
{
    protected $_table_name = 'erp_301_counterparties';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_has_many = array(
        'contacts'   => array('model' => 'Erp301_Contact',   'foreign_key' => 'counterparty_id'),
        'requisites' => array('model' => 'Erp301_Requisite', 'foreign_key' => 'counterparty_id'),
        'contracts'  => array('model' => 'Erp301_Contract',  'foreign_key' => 'counterparty_id'),
    );

    /** @var array Организационно-правовые формы */
    public static $kinds = array(
        'legal'      => 'Юридическое лицо',
        'individual' => 'Индивидуальный предприниматель',
        'state'      => 'Государственный орган',
        'foreign'    => 'Иностранная организация',
    );

    /** @var array Роли контрагента */
    public static $role_labels = array(
        'supplier'   => 'Поставщик',
        'customer'   => 'Покупатель',
        'carrier'    => 'Перевозчик',
        'contractor' => 'Подрядчик',
    );

    /** @var array Статусы */
    public static $statuses = array(
        'active'   => 'Активен',
        'blocked'  => 'Заблокирован',
        'archived' => 'В архиве',
    );

    public function rules()
    {
        return array(
            'code' => array(
                array('not_empty'),
                array('max_length', array(':value', 32)),
                array(array($this, 'code_unique')),
            ),
            'name' => array(
                array('not_empty'),
                array('max_length', array(':value', 255)),
            ),
            'kind' => array(
                array('not_empty'),
                array('in_array', array(':value', array_keys(self::$kinds))),
            ),
            'status' => array(
                array('in_array', array(':value', array_keys(self::$statuses))),
            ),
            'inn' => array(
                array(array($this, 'inn_format'), array(':value')),
                array(array($this, 'inn_unique'), array(':value')),
            ),
            'email' => array(
                array('email', array(':value')),
            ),
        );
    }

    /**
     * Уникальность кода контрагента.
     *
     * @param  string $value
     * @return bool
     */
    public function code_unique($value)
    {
        return $this->_unique('code', $value);
    }

    /**
     * ИНН: 10 цифр у юрлица, 12 — у ИП. Пустое значение допустимо.
     *
     * @param  string $value
     * @return bool
     */
    public function inn_format($value)
    {
        if ($value === NULL OR $value === '')
        {
            return TRUE;
        }

        return (bool) preg_match('/^\d{10}$|^\d{12}$/', (string) $value);
    }

    /**
     * ИНН уникален среди неудалённых контрагентов.
     *
     * @param  string $value
     * @return bool
     */
    public function inn_unique($value)
    {
        if ($value === NULL OR $value === '')
        {
            return TRUE;
        }

        return $this->_unique('inn', $value);
    }

    public function labels()
    {
        return array(
            'code'      => 'Код',
            'name'      => 'Наименование',
            'full_name' => 'Полное наименование',
            'kind'      => 'Форма',
            'roles'     => 'Роли',
            'inn'       => 'ИНН',
            'kpp'       => 'КПП',
            'ogrn'      => 'ОГРН',
            'country'   => 'Страна',
            'city'      => 'Город',
            'address'   => 'Адрес',
            'phone'     => 'Телефон',
            'email'     => 'E-mail',
            'site'      => 'Сайт',
            'status'    => 'Статус',
            'rating'    => 'Оценка',
            'note'      => 'Заметка',
        );
    }

    /**
     * Роли как массив (JSONB приходит строкой или массивом).
     *
     * @return array
     */
    public function role_codes()
    {
        $roles = $this->roles;

        if (is_string($roles))
        {
            $decoded = json_decode($roles, TRUE);
            $roles   = is_array($decoded) ? $decoded : array();
        }

        return is_array($roles) ? array_values(array_filter($roles)) : array();
    }

    /**
     * Человекочитаемые роли: «Поставщик, Покупатель».
     *
     * @return string
     */
    public function roles_label()
    {
        $labels = array();

        foreach ($this->role_codes() as $role)
        {
            $labels[] = isset(self::$role_labels[$role]) ? self::$role_labels[$role] : $role;
        }

        return $labels ? implode(', ', $labels) : '—';
    }

    /**
     * @param  string $role
     * @return bool
     */
    public function has_role($role)
    {
        return in_array($role, $this->role_codes(), TRUE);
    }

    /**
     * Человекочитаемая форма и статус.
     *
     * @return string
     */
    public function kind_label()
    {
        return isset(self::$kinds[$this->kind]) ? self::$kinds[$this->kind] : $this->kind;
    }

    /**
     * @return string
     */
    public function status_label()
    {
        return isset(self::$statuses[$this->status]) ? self::$statuses[$this->status] : $this->status;
    }

    /**
     * Договоры контрагента с учётом мягкого удаления.
     *
     * @param  array $filters  ['status' => ..., 'kind' => ...]
     * @return ORM
     */
    public function active_contracts(array $filters = array())
    {
        $query = ORM::factory('Erp301_Contract')
            ->where('counterparty_id', '=', $this->id)
            ->where('is_deleted', '=', 0);

        if ( ! empty($filters['status']))
        {
            $query->where('status', '=', $filters['status']);
        }

        if ( ! empty($filters['kind']))
        {
            $query->where('kind', '=', $filters['kind']);
        }

        return $query->order_by('valid_from', 'DESC')->order_by('id', 'DESC');
    }

    /**
     * Символьное представление для списков и логов.
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->name;
    }

    /**
     * Общая проверка уникальности значения поля среди неудалённых записей.
     *
     * @param  string $column
     * @param  mixed  $value
     * @return bool
     */
    protected function _unique($column, $value)
    {
        $query = ORM::factory('Erp301_Counterparty')
            ->where($column, '=', $value)
            ->where('is_deleted', '=', 0);

        if ($this->loaded())
        {
            $query->where('id', '<>', $this->id);
        }

        return (int) $query->count_all() === 0;
    }
}
