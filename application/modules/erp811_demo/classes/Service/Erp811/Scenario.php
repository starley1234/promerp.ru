<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Сценарий демонстрации: доступ к описанию вымышленного производства.
 *
 * Сценарий лежит в конфиге модуля (`config/scenario/print3d.php`) и читается
 * один раз. Один источник правды важен буквально: те же клиенты, материалы и
 * детали показывает лендинг `/demo`, тот же мир называет тур по системе, и из
 * него же `Service_Erp811_Seeder` делает записи во всех модулях контура. Если
 * менять мир — менять в одном файле.
 *
 * @package    Erp811
 * @category   Services
 */
class Service_Erp811_Scenario
{
    /** @var Service_Erp811_Scenario|null */
    protected static $_instance;

    /** @var array|null */
    protected $_data;

    /**
     * @return  Service_Erp811_Scenario
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

    /**
     * Код сценария (по умолчанию — контактная 3D-печать).
     *
     * @return  string
     */
    public function code()
    {
        return (string) Service_Erp811_Demo::instance()->config('scenario', 'print3d');
    }

    /**
     * Весь сценарий.
     *
     * @return  array
     */
    public function all()
    {
        if ($this->_data !== NULL)
        {
            return $this->_data;
        }

        $config = Kohana::$config->load('scenario/'.$this->code());

        $this->_data = ($config === NULL) ? array() : (array) $config->as_array();

        return $this->_data;
    }

    /**
     * @param   string  $key
     * @param   mixed   $default
     * @return  mixed
     */
    public function get($key, $default = array())
    {
        $data = $this->all();

        return isset($data[$key]) ? $data[$key] : $default;
    }

    /**
     * @return  array
     */
    public function company()
    {
        return (array) $this->get('company', array());
    }

    /**
     * @return  array
     */
    public function landing()
    {
        return (array) $this->get('landing', array());
    }

    /**
     * @return  array
     */
    public function tour()
    {
        return (array) $this->get('tour', array());
    }

    // -------------------------------------------------------------------------
    //  Контур «сотрудники — CRM — составы — QR»
    // -------------------------------------------------------------------------

    /**
     * Оргструктура и сотрудники (erp310): подразделения, должности, штатное
     * расписание, табельные карточки.
     *
     * @return  array
     */
    public function workforce()
    {
        return (array) $this->get('workforce', array());
    }

    /**
     * CRM (erp221): профили клиентов, контакты, сделки, база знаний.
     *
     * @return  array
     */
    public function crm()
    {
        return (array) $this->get('crm', array());
    }

    /**
     * Составы изделий (erp231): нормы расхода, ревизии, комплект.
     *
     * @return  array
     */
    public function structures()
    {
        return (array) $this->get('structures', array());
    }

    /**
     * QR-метки (erp199): свободные объекты, наклейки, инвентаризации.
     *
     * @return  array
     */
    public function qr()
    {
        return (array) $this->get('qr', array());
    }

    /**
     * @return  array
     */
    public function clients()
    {
        return (array) $this->get('clients', array());
    }

    /**
     * Название контрагента по коду — для текстов документов и отгрузок.
     *
     * @param   string  $code
     * @return  string
     */
    public function party_name($code)
    {
        foreach (array_merge($this->clients(), $this->suppliers()) as $party)
        {
            if ($party['code'] === $code)
            {
                return $party['name'];
            }
        }

        return (string) $code;
    }

    /**
     * @return  array
     */
    public function suppliers()
    {
        return (array) $this->get('suppliers', array());
    }

    /**
     * @return  array
     */
    public function materials()
    {
        return (array) $this->get('materials', array());
    }

    /**
     * @return  array
     */
    public function parts()
    {
        return (array) $this->get('parts', array());
    }

    /**
     * @return  array
     */
    public function centers()
    {
        return (array) $this->get('centers', array());
    }

    /**
     * @return  array
     */
    public function shifts()
    {
        return (array) $this->get('shifts', array());
    }

    /**
     * @return  array
     */
    public function downtime_reasons()
    {
        return (array) $this->get('downtime_reasons', array());
    }

    /**
     * @return  array
     */
    public function defects()
    {
        return (array) $this->get('defects', array());
    }

    /**
     * Маршрут изготовления по полимеру: FDM или SLA.
     *
     * @param   string  $polymer
     * @return  array
     */
    public function route_for($polymer)
    {
        $routes = (array) $this->get('routes', array());

        $key = (strtoupper((string) $polymer) === 'RESIN') ? 'SLA' : 'FDM';

        return isset($routes[$key]) ? $routes[$key] : array();
    }

    /**
     * Глубина демонстрационной истории в днях.
     *
     * @return  int
     */
    public function history_days()
    {
        return (int) Service_Erp811_Demo::instance()->config('history_days', 120);
    }

    /**
     * Позиции, по которым история специально «просажена»: на стенде по ним
     * видно дефицит и заявку на закупку.
     *
     * @return  array
     */
    public function cover_days_target()
    {
        return (int) $this->get('cover_days_target', 30);
    }

    public function short_materials()
    {
        return (array) $this->get('short_materials', array());
    }

    /**
     * Сколько производственных заказов создаёт сценарий.
     *
     * @return  int
     */
    public function order_count()
    {
        return (int) Service_Erp811_Demo::instance()->config('orders', 14);
    }

    /**
     * Строка для лендинга: короткое описание стенда словами сценария.
     *
     * @return  string
     */
    public function headline()
    {
        $company = $this->company();

        return isset($company['tagline']) ? $company['tagline'] : 'Демонстрационное производство';
    }
}
