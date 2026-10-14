<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Данные источника для подстановок (erp404_documents).
 *
 * Печатная форма не ходит по таблицам соседей сама: она просит у этого сервиса
 * готовый набор данных (`ref.*`), а сервис зовёт владельца — закупки (erp403),
 * производство (erp402), качество (erp405), цех (erp408). Ссылки мягкие: нет
 * модуля — нет данных, и это честная ошибка `dependency`, а не пустая бумага,
 * подписанная «документ сформирован».
 *
 * Наборы (`ref.*`) намеренно плоские и «печатные»: числа уже приведены к виду
 * (`1 250,000`), состояния — подписями, а не кодами. Шаблон печатает документ
 * для человека, и `status = 3` на бумаге смысла не имеет.
 *
 * @package    Erp404
 * @category   Services
 */
class Service_Erp404_Sources
{
    /** @var Service_Erp404_Sources */
    protected static $_instance;

    /** @var array Поддержанные типы источников */
    protected $_types = array(
        'erp402_order',
        'erp403_order',
        'erp403_receipt',
        'erp405_inspection',
        'erp408_operation',
        'erp408_shift_day',
    );

    /**
     * @return  Service_Erp404_Sources
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
     * Типы объектов, для которых модуль умеет собрать данные.
     *
     * @return  array
     */
    public function types()
    {
        return $this->_types;
    }

    /**
     * Поддержан ли тип (пустая строка — универсальный шаблон).
     *
     * @param   string  $ref_type
     * @return  bool
     */
    public function supports($ref_type)
    {
        $ref_type = (string) $ref_type;

        return $ref_type === '' OR in_array($ref_type, $this->_types, TRUE);
    }

    /**
     * Данные источника.
     *
     * @param   string  $ref_type
     * @param   string  $ref_id
     * @return  array
     * @throws  Exception_Erp404_Documents
     */
    public function fetch($ref_type, $ref_id)
    {
        $ref_type = (string) $ref_type;
        $ref_id   = (string) $ref_id;

        if ( ! $this->supports($ref_type))
        {
            throw new Exception_Erp404_Documents(
                'Тип объекта :type не поддержан: неизвестно, откуда брать данные',
                array(':type' => $ref_type),
                'unknown_ref'
            );
        }

        if ($ref_type === '')
        {
            // Универсальный шаблон: данные передаёт вызывающий.
            return array();
        }

        if (trim($ref_id) === '')
        {
            throw new Exception_Erp404_Documents(
                'Не указан объект-источник для шаблона типа :type',
                array(':type' => $ref_type),
                'ref_not_found'
            );
        }

        switch ($ref_type)
        {
            case 'erp403_order':
                return $this->_order($ref_id);
            case 'erp403_receipt':
                return $this->_receipt($ref_id);
            case 'erp402_order':
                return $this->_production_order($ref_id);
            case 'erp405_inspection':
                return $this->_inspection($ref_id);
            case 'erp408_operation':
                return $this->_operation($ref_id);
            case 'erp408_shift_day':
                return $this->_shift_day($ref_id);
        }

        throw new Exception_Erp404_Documents(
            'Тип объекта :type не поддержан',
            array(':type' => $ref_type),
            'unknown_ref'
        );
    }

    /**
     * Заказ поставщику (erp403).
     *
     * @param   string  $ref_id
     * @return  array
     * @throws  Exception_Erp404_Documents
     */
    protected function _order($ref_id)
    {
        if ( ! class_exists('Service_Erp403_Orders'))
        {
            throw new Exception_Erp404_Documents('Модуль закупок недоступен: печатать заказ поставщику нечем', NULL, 'dependency');
        }

        $orders = Service_Erp403_Orders::instance();
        $order  = $orders->get($ref_id);

        if ( ! $order)
        {
            throw new Exception_Erp404_Documents('Заказ поставщику :id не найден', array(':id' => $ref_id), 'ref_not_found');
        }

        $lines = array();

        foreach ($orders->lines($order->id) as $line)
        {
            $lines[] = array(
                'item_name' => $this->_item_name($line->item_id),
                'qty'       => $this->_num($line->qty, 3),
                'price'     => $this->_num($line->price, 2),
                'amount'    => $this->_num(method_exists($line, 'amount') ? $line->amount() : 0, 2),
            );
        }

        return array(
            'number'         => (string) $order->number,
            'supplier_name'  => $this->_supplier_name(isset($order->supplier_id) ? $order->supplier_id : NULL),
            'warehouse_name' => $this->_warehouse_name(isset($order->warehouse_id) ? $order->warehouse_id : NULL),
            'delivery_date'  => $this->_day($order->delivery_date),
            'status_label'   => method_exists($order, 'status_label') ? $order->status_label() : (string) $order->status,
            'amount'         => $this->_num($order->amount, 2),
            'lines'          => $lines,
        );
    }

    /**
     * Приёмка закупки (erp403): приходная накладная.
     *
     * @param   string  $ref_id
     * @return  array
     * @throws  Exception_Erp404_Documents
     */
    protected function _receipt($ref_id)
    {
        if ( ! class_exists('Service_Erp403_Receipts'))
        {
            throw new Exception_Erp404_Documents('Модуль закупок недоступен: печатать приёмку нечем', NULL, 'dependency');
        }

        $receipts = Service_Erp403_Receipts::instance();
        $receipt  = $receipts->get($ref_id);

        if ( ! $receipt)
        {
            throw new Exception_Erp404_Documents('Приёмка :id не найдена', array(':id' => $ref_id), 'ref_not_found');
        }

        $lines = array();

        foreach ($receipts->lines($receipt->id) as $line)
        {
            $lines[] = array(
                'item_name'     => $this->_item_name($line->item_id),
                'qty'           => $this->_num($line->qty, 3),
                'price'         => $this->_num($line->price, 2),
                'quality_label' => method_exists($line, 'quality_label') ? $line->quality_label() : (string) $line->quality_status,
            );
        }

        return array(
            'number'         => (string) $receipt->number,
            'supplier_name'  => $this->_supplier_name(isset($receipt->supplier_id) ? $receipt->supplier_id : NULL),
            'warehouse_name' => $this->_warehouse_name(isset($receipt->warehouse_id) ? $receipt->warehouse_id : NULL),
            'status_label'   => method_exists($receipt, 'status_label') ? $receipt->status_label() : (string) $receipt->status,
            'lines'          => $lines,
        );
    }

    /**
     * Производственный заказ (erp402): состав и маршрут.
     *
     * Состав берётся из состава заказа (`items()`: норма на изделие и потребность
     * на весь заказ), маршрут — из модуля цеха (erp408), потому что операции
     * живут там. Модуль цеха выключен — маршрут печатается пустым, заказ от
     * этого не перестаёт быть заказом.
     *
     * @param   string  $ref_id
     * @return  array
     * @throws  Exception_Erp404_Documents
     */
    protected function _production_order($ref_id)
    {
        if ( ! class_exists('Service_Erp402_Orders'))
        {
            throw new Exception_Erp404_Documents('Модуль производства недоступен: печатать заказ нечем', NULL, 'dependency');
        }

        $orders = Service_Erp402_Orders::instance();
        $order  = $orders->get($ref_id);

        if ( ! $order)
        {
            throw new Exception_Erp404_Documents('Производственный заказ :id не найден', array(':id' => $ref_id), 'ref_not_found');
        }

        $materials = array();

        foreach ($orders->items($order->id) as $item)
        {
            $materials[] = array(
                'item_name' => $this->_item_name($item->item_id),
                'norm'      => $this->_num($item->qty_per_unit, 3),
                'required'  => $this->_num($item->qty_total, 3),
            );
        }

        $route = array();

        if (class_exists('Service_Erp408_Operations'))
        {
            try
            {
                foreach (Service_Erp408_Operations::instance()->for_order($order->id) as $operation)
                {
                    $route[] = array(
                        'operation_name'   => (string) (isset($operation['operation_name']) ? $operation['operation_name'] : ''),
                        'work_center_name' => (string) (isset($operation['work_center_label']) ? $operation['work_center_label'] : ''),
                        'planned_minutes'  => $this->_num(isset($operation['planned_hours']) ? $operation['planned_hours'] : 0, 2),
                    );
                }
            }
            catch (Exception $e)
            {
                $route = array();
            }
        }

        // Брак по заказу: паспорт качества обязан показывать, что и почему
        // забраковано. Раньше этот блок был только у контроля (erp405), а на
        // производственный заказ шаблон паспорта падал с «нет списка ref.defects»:
        // шаблон ссылался на данные, которых источник не отдавал.
        $defects = array();

        if (class_exists('Service_Erp405_Inspections'))
        {
            try
            {
                $inspections = Service_Erp405_Inspections::instance();

                foreach ($inspections->search(array('order_id' => (int) $order->id, 'result' => 'failed'), 20) as $inspection)
                {
                    foreach ($inspections->defects($inspection->id) as $defect)
                    {
                        $defects[] = array(
                            'code'        => (string) $defect->code,
                            'code_name'   => $this->_defect_code_name(isset($defect->defect_code_id) ? $defect->defect_code_id : NULL),
                            'qty'         => $this->_num($defect->qty, 3),
                            'cause_label' => method_exists($defect, 'cause_label') ? $defect->cause_label() : (string) $defect->cause,
                        );
                    }
                }
            }
            catch (Exception $e)
            {
                $defects = array();
            }
        }

        return array(
            'number'       => (string) $order->number,
            'item_name'    => $this->_item_name(isset($order->item_id) ? $order->item_id : NULL),
            'qty'          => $this->_num($order->qty, 3),
            'due_date'     => $this->_day(isset($order->due_date) ? $order->due_date : (isset($order->planned_at) ? $order->planned_at : NULL)),
            'status_label' => method_exists($order, 'status_label') ? $order->status_label() : (string) $order->status,
            'materials'    => $materials,
            'route'        => $route,
            'defects'      => $defects,
        );
    }

    /**
     * Контроль качества (erp405): паспорт партии.
     *
     * @param   string  $ref_id
     * @return  array
     * @throws  Exception_Erp404_Documents
     */
    protected function _inspection($ref_id)
    {
        if ( ! class_exists('Service_Erp405_Inspections'))
        {
            throw new Exception_Erp404_Documents('Модуль качества недоступен: печатать паспорт нечем', NULL, 'dependency');
        }

        $inspections = Service_Erp405_Inspections::instance();
        $inspection  = $inspections->get($ref_id);

        if ( ! $inspection)
        {
            throw new Exception_Erp404_Documents('Контроль :id не найден', array(':id' => $ref_id), 'ref_not_found');
        }

        $defects = array();

        foreach ($inspections->defects($inspection->id) as $defect)
        {
            $defects[] = array(
                'code'        => (string) $defect->code,
                'code_name'   => $this->_defect_code_name(isset($defect->defect_code_id) ? $defect->defect_code_id : NULL),
                'qty'         => $this->_num($defect->qty, 3),
                'cause_label' => method_exists($defect, 'cause_label') ? $defect->cause_label() : (string) $defect->cause,
            );
        }

        $kinds     = Model_Erp405_Inspection::$kinds;
        $results   = Model_Erp405_Inspection::$results;
        $decisions = Model_Erp405_Inspection::$decisions;

        return array(
            'number'         => (string) $inspection->number,
            'item_name'      => (string) $inspection->item_name !== '' ? (string) $inspection->item_name : $this->_item_name($inspection->item_id),
            'qty'            => $this->_num($inspection->qty, 3),
            'kind_label'     => isset($kinds[$inspection->kind]) ? $kinds[$inspection->kind] : (string) $inspection->kind,
            'result_label'   => isset($results[$inspection->result]) ? $results[$inspection->result] : 'не определён',
            'decision_label' => isset($decisions[$inspection->decision]) ? $decisions[$inspection->decision] : 'не принято',
            'defects'        => $defects,
        );
    }

    /**
     * Операция цеха (erp408).
     *
     * @param   string  $ref_id
     * @return  array
     * @throws  Exception_Erp404_Documents
     */
    protected function _operation($ref_id)
    {
        if ( ! class_exists('Service_Erp408_Operations'))
        {
            throw new Exception_Erp404_Documents('Модуль цеха недоступен: печатать операцию нечем', NULL, 'dependency');
        }

        $operation = Service_Erp408_Operations::instance()->get($ref_id);

        if ( ! $operation)
        {
            throw new Exception_Erp404_Documents('Операция :id не найдена', array(':id' => $ref_id), 'ref_not_found');
        }

        return array(
            'number'       => (string) $operation['id'],
            'name'         => (string) (isset($operation['operation_name']) ? $operation['operation_name'] : ''),
            'order_number' => (string) (isset($operation['order_number']) ? $operation['order_number'] : ''),
            'qty_planned'  => $this->_num(isset($operation['qty_planned']) ? $operation['qty_planned'] : 0, 3),
            'qty_ok'       => $this->_num(isset($operation['qty_ok']) ? $operation['qty_ok'] : 0, 3),
            'status_label' => (string) (isset($operation['status_label']) ? $operation['status_label'] : ''),
        );
    }

    /**
     * Сменное задание на конкретный день (erp408).
     *
     * Источник — ISO-дата, а не внутренний ID. Это позволяет открыть форму
     * прямо с диспетчерской доски и получить неизменяемый снимок erp404.
     * Строки идут в порядке центров/операций; смена подписывается по справочнику.
     *
     * @param   string  $ref_id  YYYY-MM-DD
     * @return  array
     * @throws  Exception_Erp404_Documents
     */
    protected function _shift_day($ref_id)
    {
        if ( ! class_exists('Service_Erp408_Operations') OR ! class_exists('Service_Erp408_Shifts'))
        {
            throw new Exception_Erp404_Documents('Модуль цеха недоступен: печатать сменное задание нечем', NULL, 'dependency');
        }

        $day = trim((string) $ref_id);

        if ( ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) OR date('Y-m-d', strtotime($day)) !== $day)
        {
            throw new Exception_Erp404_Documents('Для сменного задания нужна дата в формате ГГГГ-ММ-ДД', NULL, 'ref_not_found');
        }

        $board      = Service_Erp408_Operations::instance()->board($day);
        $shift_rows = Service_Erp408_Shifts::instance()->search(array(), 100, 0)['rows'];
        $shifts     = array();
        $lines      = array();

        foreach ($shift_rows as $shift)
        {
            $shifts[(int) $shift['id']] = (string) $shift['code'].' · '.(string) $shift['label'];
        }

        foreach ($board['centers'] as $center_row)
        {
            foreach ($center_row['operations'] as $operation)
            {
                $shift_id = isset($operation['shift_id']) ? (int) $operation['shift_id'] : 0;
                $lines[]  = array(
                    'center'       => (string) $center_row['center']['code'].' · '.(string) $center_row['center']['name'],
                    'shift'        => isset($shifts[$shift_id]) ? $shifts[$shift_id] : 'Не назначена',
                    'order_number' => (string) $operation['order_number'],
                    'op_no'        => (string) $operation['op_no'],
                    'name'         => (string) $operation['name'],
                    'qty_planned'  => $this->_num($operation['qty_planned'], 3),
                    'hours'        => $this->_num($operation['planned_hours'], 2),
                    'status_label' => (string) $operation['status_label'],
                );
            }
        }

        foreach ($board['unassigned'] as $operation)
        {
            $lines[] = array(
                'center'       => 'Не назначен',
                'shift'        => 'Не назначена',
                'order_number' => (string) $operation['order_number'],
                'op_no'        => (string) $operation['op_no'],
                'name'         => (string) $operation['name'],
                'qty_planned'  => $this->_num($operation['qty_planned'], 3),
                'hours'        => $this->_num($operation['planned_hours'], 2),
                'status_label' => (string) $operation['status_label'],
            );
        }

        return array(
            'number'     => date('d.m.Y', strtotime($day)),
            'day'        => date('d.m.Y', strtotime($day)),
            'operations' => (string) count($lines),
            'hours'      => $this->_num($board['totals']['hours'], 2),
            'lines'      => $lines,
        );
    }

    /**
     * Общие данные для шаблона: организация, дата, автор.
     *
     * Организация берётся из настроек сайта, автор — из учётной записи: печатная
     * форма должна уметь подписаться тем, кто её выпустил, а не «системой».
     *
     * @param   int|null    $user_id
     * @return  array
     */
    public function meta($user_id = NULL)
    {
        $name = '';

        try
        {
            $config = Kohana::$config->load('site_config');
            $name   = (string) $config->get('site_name', '');
        }
        catch (Exception $e)
        {
            $name = '';
        }

        $user = '';

        if ($user_id)
        {
            try
            {
                $model = ORM::factory('User', (int) $user_id);
                $user  = $model->loaded() ? (string) $model->username : '';
            }
            catch (Exception $e)
            {
                $user = '';
            }
        }

        return array(
            'company' => $name,
            'date'    => date('d.m.Y'),
            'user'    => $user,
        );
    }

    /**
     * Название номенклатуры: реестр erp227 через помощник закупок.
     *
     * Своего обращения к таблицам реестра здесь нет — правило проекта: чужие
     * данные только через сервис владельца. Модуль закупок с его `item_name()`
     * уже это делает (и мягко переживает выключенный реестр).
     *
     * @param   int|null    $item_id
     * @return  string
     */
    protected function _item_name($item_id)
    {
        if ( ! $item_id)
        {
            return '';
        }

        if (class_exists('Service_Erp403_Requests'))
        {
            try
            {
                return (string) Service_Erp403_Requests::instance()->item_name($item_id);
            }
            catch (Exception $e)
            {
                return '';
            }
        }

        return '';
    }

    /**
     * Название кода дефекта по идентификатору (справочник erp405).
     *
     * @param   int|null    $defect_code_id
     * @return  string
     */
    protected function _defect_code_name($defect_code_id)
    {
        if ( ! $defect_code_id OR ! class_exists('Service_Erp405_DefectCodes'))
        {
            return '';
        }

        try
        {
            $code = Service_Erp405_DefectCodes::instance()->find($defect_code_id);

            return $code ? (string) $code->name : '';
        }
        catch (Exception $e)
        {
            return '';
        }
    }

    /**
     * Название поставщика (erp301) — мягко: нет модуля, печатаем прочерк.
     *
     * @param   int|null    $supplier_id
     * @return  string
     */
    protected function _supplier_name($supplier_id)
    {
        if ( ! $supplier_id OR ! class_exists('Service_Erp301_Counterparties'))
        {
            return '';
        }

        try
        {
            return (string) Service_Erp301_Counterparties::instance()->name($supplier_id);
        }
        catch (Exception $e)
        {
            return '';
        }
    }

    /**
     * Название склада (erp401) — мягко.
     *
     * @param   int|null    $warehouse_id
     * @return  string
     */
    protected function _warehouse_name($warehouse_id)
    {
        if ( ! $warehouse_id OR ! class_exists('Service_Erp401_Stock'))
        {
            return '';
        }

        try
        {
            foreach (Service_Erp401_Stock::instance()->warehouses(TRUE) as $warehouse)
            {
                if ((int) $warehouse->id === (int) $warehouse_id)
                {
                    return (string) $warehouse->name;
                }
            }
        }
        catch (Exception $e)
        {
            return '';
        }

        return '';
    }

    /**
     * Число в печатном виде: `1 250,000`.
     *
     * @param   mixed   $value
     * @param   int     $decimals
     * @return  string
     */
    protected function _num($value, $decimals = 3)
    {
        return number_format(Erp::num($value), $decimals, ',', ' ');
    }

    /**
     * Дата для печати.
     *
     * @param   mixed   $value
     * @return  string
     */
    protected function _day($value)
    {
        if ($value === NULL OR $value === '')
        {
            return '';
        }

        $stamp = strtotime((string) $value);

        return $stamp === FALSE ? (string) $value : date('d.m.Y', $stamp);
    }
}
