<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Источники данных аналитики (erp801_analytics).
 *
 * Единственное место в модуле, которое знает про соседей. Правило простое:
 * аналитика не читает чужие таблицы — она спрашивает у модуля-владельца его
 * сервис. Причина не в чистоте ради чистоты:
 *
 *  - владелец меняет схему и правила, а сервис — обещание, которое он держит;
 *    запрос «в лоб» по таблице ломается молча и задним числом;
 *  - агрегаты (остатки, время операций, доли брака) уже посчитаны там, где
 *    живут данные, и считаются по актуальным правилам, а не по копии;
 *  - модуль может быть выключен — тогда витрина честно показывает «источник
 *    недоступен», а не отдаёт нули, неотличимые от «всё хорошо».
 *
 * Поэтому доступ к соседям — мягкий: `class_exists()` + `method_exists()` +
 * перехват исключения. Отказ одного источника не роняет остальные блоки.
 *
 * @package    Erp801
 * @category   Services
 */
class Service_Erp801_Sources
{
    /** @var Service_Erp801_Sources|null */
    protected static $_instance;

    /** @var array Кэш подключённых сервисов: ключ => объект|null */
    protected $_services = array();

    /**
     * @return  Service_Erp801_Sources
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
     * Описание источников: чем модуль пользуется и для чего.
     *
     * @return  array
     */
    public function definitions()
    {
        return array(
            'stock'      => array('class' => 'Service_Erp401_Stock',      'title' => 'Склад (erp401)',      'for' => 'оборачиваемость, покрытие, ABC/XYZ'),
            'shopfloor'  => array('class' => 'Service_Erp408_Operations', 'title' => 'Цех (erp408)',        'for' => 'OEE, простои, план/факт по операциям'),
            'quality'    => array('class' => 'Service_Erp405_Stats',      'title' => 'Качество (erp405)',   'for' => 'брак, причины, карантин'),
            'purchases'  => array('class' => 'Service_Erp403_Orders',     'title' => 'Закупки (erp403)',    'for' => 'заказы поставщикам и просрочка'),
            'suppliers'  => array('class' => 'Service_Erp403_Suppliers',  'title' => 'Поставщики (erp403)', 'for' => 'имена контрагентов в отчётах'),
        );
    }

    /**
     * Состояние источников: что подключено, а что нет.
     *
     * Проверка дешёвая (только наличие классов), потому что её показывает
     * каждая страница аналитики.
     *
     * @return  array
     */
    public function available()
    {
        $rows = array();

        foreach ($this->definitions() as $key => $definition)
        {
            $ok = class_exists($definition['class']);

            $rows[] = array(
                'key'   => $key,
                'title' => $definition['title'],
                'for'   => $definition['for'],
                'class' => $definition['class'],
                'ok'    => $ok,
            );
        }

        return $rows;
    }

    /**
     * Данные недоступны — честная ошибка витрины, а не нули.
     *
     * @param   string  $key
     * @return  Exception_Erp801_Analytics
     */
    public function missing($key)
    {
        $definitions = $this->definitions();

        $title = isset($definitions[$key]) ? $definitions[$key]['title'] : $key;

        return new Exception_Erp801_Analytics(
            'Витрина недоступна: источник «:title» не подключён',
            array(':title' => $title),
            'source_missing'
        );
    }

    /**
     * Склад: остатки, движения, названия позиций.
     *
     * @return  Service_Erp401_Stock|null
     */
    public function stock()
    {
        return $this->_service('stock');
    }

    /**
     * Цех: операции, выработка, простои.
     *
     * @return  Service_Erp408_Operations|null
     */
    public function shopfloor()
    {
        return $this->_service('shopfloor');
    }

    /**
     * Качество: контроли, дефекты, решения.
     *
     * @return  Service_Erp405_Stats|null
     */
    public function quality()
    {
        return $this->_service('quality');
    }

    /**
     * Закупки: заказы поставщикам.
     *
     * @return  Service_Erp403_Orders|null
     */
    public function purchases()
    {
        return $this->_service('purchases');
    }

    /**
     * Поставщики: имена контрагентов.
     *
     * @return  Service_Erp403_Suppliers|null
     */
    public function suppliers()
    {
        return $this->_service('suppliers');
    }

    /**
     * Имя контрагента через владельца справочника (erp301 под капотом erp403).
     *
     * @param   int $supplier_id
     * @return  string
     */
    public function supplier_name($supplier_id)
    {
        $supplier_id = (int) $supplier_id;

        if ($supplier_id <= 0)
        {
            return '—';
        }

        $suppliers = $this->suppliers();

        if ( ! $suppliers OR ! method_exists($suppliers, 'name'))
        {
            return 'Контрагент #'.$supplier_id;
        }

        try
        {
            $name = $suppliers->name($supplier_id);

            return ($name === NULL OR $name === '') ? 'Контрагент #'.$supplier_id : (string) $name;
        }
        catch (Exception $e)
        {
            return 'Контрагент #'.$supplier_id;
        }
    }

    /**
     * Имя и код позиции номенклатуры через склад (он уже умеет спрашивать
     * реестр erp227 и не отдаёт исключение на удалённый объект).
     *
     * @param   int $item_id
     * @return  array   {id, code, name}
     */
    public function item($item_id)
    {
        $item_id = (int) $item_id;

        $stock = $this->stock();

        if ($stock AND method_exists($stock, 'item'))
        {
            try
            {
                $item = $stock->item($item_id);

                if (is_array($item) AND isset($item['name']))
                {
                    return $item;
                }
            }
            catch (Exception $e)
            {
                // Ниже — запасной вариант.
            }
        }

        return array(
            'id'   => $item_id,
            'code' => $item_id > 0 ? '#'.$item_id : '—',
            'name' => $item_id > 0 ? 'Объект #'.$item_id : 'Без позиции',
        );
    }

    /**
     * Названия позиций пачкой (для витрин: одна выборка вместо запроса на строку).
     *
     * @param   array   $item_ids
     * @return  array   item_id => {id, code, name}
     */
    public function items(array $item_ids)
    {
        $items = array();

        foreach (array_unique(array_map('intval', $item_ids)) as $item_id)
        {
            $items[$item_id] = $this->item($item_id);
        }

        return $items;
    }

    /**
     * Подключённый сервис источника или NULL.
     *
     * @param   string  $key
     * @return  object|null
     */
    protected function _service($key)
    {
        if (array_key_exists($key, $this->_services))
        {
            return $this->_services[$key];
        }

        $definitions = $this->definitions();

        $service = NULL;

        if (isset($definitions[$key]))
        {
            $class = $definitions[$key]['class'];

            if (class_exists($class))
            {
                try
                {
                    $service = method_exists($class, 'instance') ? call_user_func(array($class, 'instance')) : new $class;
                }
                catch (Exception $e)
                {
                    Kohana::$log->add(Log::WARNING, 'erp801: источник :class не открылся — :error', array(
                        ':class' => $class,
                        ':error' => $e->getMessage(),
                    ));

                    $service = NULL;
                }
            }
            else
            {
                Kohana::$log->add(Log::DEBUG, 'erp801: источник :class не подключён', array(':class' => $class));
            }
        }

        return $this->_services[$key] = $service;
    }
}
