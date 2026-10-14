<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Адаптеры целей QR-наклейки (erp199_sqr).
 *
 * Наклейка не владеет маркируемыми данными: она указывает на сущность
 * модуля-владельца и читает её через сервис этого модуля. Здесь собраны
 * «переводчики» между ссылкой наклейки (`target_kind` + id/uuid/код) и
 * карточкой цели, которую видит терминал.
 *
 * Виды целей:
 *   local     — свободный объект самого модуля (erp_199_objects);
 *   registry  — объект реестра erp227 (оборудование, локация, материал);
 *   item      — номенклатура erp227 + остатки склада erp401;
 *   lot       — партия/серийный номер erp412;
 *   order     — производственный заказ erp402;
 *   employee  — сотрудник erp310.
 *
 * Карточка цели — единый формат для приложения:
 *   kind, id, uuid, label, subtitle, status, url, fields[], editable.
 *
 * @package    Erp199
 * @category   Services
 */
class Service_Erp199_Targets
{
    /** @var Service_Erp199_Targets|null */
    protected static $_instance;

    /**
     * @return  Service_Erp199_Targets
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Виды целей: код адаптера => подпись.
     *
     * @return  array
     */
    public function kinds()
    {
        return array(
            'local'    => 'Свободный объект модуля',
            'registry' => 'Объект реестра (erp227)',
            'item'     => 'Номенклатура склада (erp227 + erp401)',
            'lot'      => 'Партия / серийный номер (erp412)',
            'order'    => 'Производственный заказ (erp402)',
            'employee' => 'Сотрудник (erp310)',
        );
    }

    /**
     * Поддерживается ли вид цели.
     *
     * @param   string  $kind
     * @return  boolean
     */
    public function supports($kind)
    {
        return array_key_exists((string) $kind, $this->kinds());
    }

    /**
     * Карточка цели по ссылке из наклейки.
     *
     * @param   string  $kind
     * @param   mixed   $ref     id, uuid или код/номер
     * @return  array|null
     */
    public function card($kind, $ref)
    {
        try
        {
            switch ((string) $kind)
            {
                case 'local':
                    return $this->local_card($ref);
                case 'registry':
                    return $this->registry_card($ref, FALSE);
                case 'item':
                    return $this->registry_card($ref, TRUE);
                case 'lot':
                    return $this->lot_card($ref);
                case 'order':
                    return $this->order_card($ref);
                case 'employee':
                    return $this->employee_card($ref);
            }
        }
        catch (Exception $e)
        {
            // Карточка цели — не место для исключений: недоступный модуль-владелец
            // не должен ломать сканирование, оператор увидит «цель не найдена».
            Kohana::$log->add(Log::WARNING, 'erp199: цель :kind/:ref недоступна — :error', array(
                ':kind' => $kind, ':ref' => is_scalar($ref) ? $ref : gettype($ref), ':error' => $e->getMessage(),
            ));
        }

        return NULL;
    }

    /**
     * Человекочитаемая подпись цели для списков наклеек.
     *
     * @param   string  $kind
     * @param   mixed   $ref
     * @return  string|null
     */
    public function label($kind, $ref)
    {
        $card = $this->card($kind, $ref);

        return $card ? $card['label'] : NULL;
    }

    /**
     * Поиск цели для привязки наклейки (терминал и админка).
     *
     * @param   string  $kind
     * @param   string  $query
     * @param   integer $limit
     * @return  array   список карточек
     */
    public function search($kind, $query, $limit = 10)
    {
        $query = trim((string) $query);
        $limit = max(1, min(50, (int) $limit));

        if ($query === '')
        {
            return array();
        }

        $cards = array();

        try
        {
            switch ((string) $kind)
            {
                case 'local':
                    $rows = DB::select('id', 'uuid', 'name', 'serial_number', 'status')
                        ->from('erp_199_objects')
                        ->where('is_deleted', '=', FALSE)
                        ->and_where_open()
                            ->where('name', 'ILIKE', '%'.$query.'%')
                            ->or_where('serial_number', 'ILIKE', '%'.$query.'%')
                        ->and_where_close()
                        ->order_by('updated_at', 'DESC')
                        ->limit($limit)
                        ->execute()
                        ->as_array();

                    foreach ($rows as $row)
                    {
                        $cards[] = $this->local_card($row['id']);
                    }
                    break;

                case 'registry':
                case 'item':
                    // Владелец номенклатуры — реестр; его же сервис и ищем.
                    $rows = DB::select('id')
                        ->from('erp_227_objects')
                        ->where('is_deleted', '=', FALSE)
                        ->and_where('is_latest', '=', TRUE)
                        ->and_where_open()
                            ->where('code', 'ILIKE', '%'.$query.'%')
                            ->or_where('name', 'ILIKE', '%'.$query.'%')
                        ->and_where_close()
                        ->order_by('id', 'DESC')
                        ->limit($limit)
                        ->execute()
                        ->as_array();

                    foreach ($rows as $row)
                    {
                        $cards[] = $this->registry_card($row['id'], $kind === 'item');
                    }
                    break;

                case 'lot':
                    if (class_exists('Service_Erp412_Traceability'))
                    {
                        foreach (Service_Erp412_Traceability::instance()->search(array('q' => $query), $limit) as $row)
                        {
                            $cards[] = $this->lot_card_from_row($row);
                        }
                    }
                    break;

                case 'order':
                    if (class_exists('Service_Erp402_Orders'))
                    {
                        // Фильтр заказов называется `term`, а выдача — страница
                        // `rows/total`: перебирать нужно именно строки.
                        $page = Service_Erp402_Orders::instance()->search(array('term' => $query), $limit);

                        foreach ($page['rows'] as $row)
                        {
                            $cards[] = $this->order_card_from_row($row);
                        }
                    }
                    break;

                case 'employee':
                    if (class_exists('Service_Erp310_Workforce'))
                    {
                        // У кадрового сервиса поиск идёт по фильтру `term`,
                        // результат — страница `rows/total`.
                        $page = Service_Erp310_Workforce::instance()->search(array('term' => $query), $limit);

                        foreach ($page['rows'] as $row)
                        {
                            $cards[] = $this->employee_card_from_row($row);
                        }
                    }
                    break;
            }
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp199: поиск цели :kind недоступен — :error', array(
                ':kind' => $kind, ':error' => $e->getMessage(),
            ));
        }

        return array_values(array_filter($cards));
    }

    /**
     * Изменение статуса цели через сервис владельца.
     *
     * Поддерживается там, где статус — часть предметной модели: свободный
     * объект модуля, партия (erp412), объект реестра (erp227). Для заказа,
     * номенклатуры и сотрудника статус меняют только их собственные процессы —
     * терминал в этом случае пишет запись в журнал.
     *
     * @param   string  $kind
     * @param   mixed   $ref
     * @param   string  $status
     * @param   integer $actor
     * @return  boolean
     */
    public function set_status($kind, $ref, $status, $actor = NULL)
    {
        $status = trim((string) $status);

        if ($status === '')
        {
            return FALSE;
        }

        switch ((string) $kind)
        {
            case 'local':
                // Статус свободного объекта ограничен проверкой в БД: сценарий
                // может предложить «в ремонте», которого у объекта нет, — тогда
                // отметка остаётся в журнале, а не падает на ограничении.
                if ( ! array_key_exists($status, Service_Erp199_Objects::instance()->statuses()))
                {
                    return FALSE;
                }

                $object = DB::select('id')->from('erp_199_objects')->where('id', '=', (int) $ref)->execute()->current();

                if ( ! $object)
                {
                    return FALSE;
                }

                DB::update('erp_199_objects')
                    ->set(array('status' => $status, 'updated_by' => $actor ?: NULL, 'updated_at' => DB::expr('CURRENT_TIMESTAMP')))
                    ->where('id', '=', (int) $object['id'])
                    ->execute();

                return TRUE;

            case 'lot':
                if ( ! class_exists('Service_Erp412_Traceability'))
                {
                    return FALSE;
                }

                // Разрешённые переходы сторожат владельцы: пробуем и сообщаем
                // результат честно, а не подменяем статус в обход их правил.
                try
                {
                    Service_Erp412_Traceability::instance()->transition((int) $ref, $status, 'Терминал QR (erp199)', $actor, 'erp199_scan');

                    return TRUE;
                }
                catch (Exception $e)
                {
                    Kohana::$log->add(Log::WARNING, 'erp199: erp412 отклонил статус :status — :error', array(
                        ':status' => $status, ':error' => $e->getMessage(),
                    ));

                    return FALSE;
                }

            case 'registry':
            case 'item':
                if ( ! class_exists('Service_Erp227_Registry'))
                {
                    return FALSE;
                }

                $object = Service_Erp227_Registry::instance()->get_by_id((int) $ref);

                if ( ! $object->loaded())
                {
                    return FALSE;
                }

                Service_Erp227_Registry::instance()->change_status((int) $ref, $status);

                return TRUE;
        }

        return FALSE;
    }

    /**
     * Запись произвольных полей карточки локального объекта.
     *
     * Для целей чужих модулей атрибуты не переписываются: модуль не владеет
     * их данными, поэтому терминал оставляет только запись в журнале, а
     * изменение делает сервис владельца (см. README).
     *
     * @param   string  $kind
     * @param   mixed   $ref
     * @param   array   $values
     * @param   integer $actor
     * @return  boolean
     */
    public function set_attributes($kind, $ref, array $values, $actor = NULL)
    {
        if ((string) $kind !== 'local')
        {
            return FALSE;
        }

        $object = DB::select('id', 'attributes')->from('erp_199_objects')
            ->where('id', '=', (int) $ref)->execute()->current();

        if ( ! $object)
        {
            return FALSE;
        }

        $attributes = Service_Erp199_Cast::json($object['attributes']);
        $attributes = is_array($attributes) ? $attributes : array();

        foreach ($values as $key => $value)
        {
            if ($value === NULL OR $value === '')
            {
                continue;
            }

            $attributes[(string) $key] = $value;
        }

        DB::update('erp_199_objects')
            ->set(array(
                'attributes' => json_encode($attributes, JSON_UNESCAPED_UNICODE),
                'updated_by' => $actor ?: NULL,
                'updated_at' => DB::expr('CURRENT_TIMESTAMP'),
            ))
            ->where('id', '=', (int) $object['id'])
            ->execute();

        return TRUE;
    }

    // =========================================================================
    //  Карточки по видам целей
    // =========================================================================

    /**
     * Свободный объект модуля.
     *
     * @param   mixed  $ref
     * @return  array|null
     */
    protected function local_card($ref)
    {
        $row = DB::select('o.*', array('t.label', 'type_label'), array('t.code', 'type_code'))
            ->from(array('erp_199_objects', 'o'))
            ->join(array('erp_199_types', 't'), 'LEFT')->on('t.id', '=', 'o.type_id')
            ->where('o.id', '=', (int) $ref)
            ->and_where('o.is_deleted', '=', FALSE)
            ->execute()
            ->current();

        if ( ! $row)
        {
            return NULL;
        }

        $attributes = Service_Erp199_Cast::json($row['attributes']);

        return array(
            'kind'     => 'local',
            'id'       => (int) $row['id'],
            'uuid'     => (string) $row['uuid'],
            'label'    => (string) $row['name'],
            'subtitle' => $row['type_label'] ? 'Свободный объект · '.$row['type_label'] : 'Свободный объект',
            'status'   => (string) $row['status'],
            'url'      => '/qr/object/'.(int) $row['id'],
            'editable' => TRUE,
            'fields'   => array_values(array_filter(array(
                $row['brand'] ? array('label' => 'Производитель', 'value' => $row['brand']) : NULL,
                $row['model'] ? array('label' => 'Модель', 'value' => $row['model']) : NULL,
                $row['serial_number'] ? array('label' => 'Серийный номер', 'value' => $row['serial_number']) : NULL,
                is_array($attributes) ? array('label' => 'Атрибуты', 'value' => $this->attribute_text($attributes)) : NULL,
            ))),
        );
    }

    /**
     * Объект реестра erp227 (или номенклатура с остатками).
     *
     * @param   mixed    $ref
     * @param   boolean  $with_stock
     * @return  array|null
     */
    protected function registry_card($ref, $with_stock = FALSE)
    {
        if ( ! class_exists('Service_Erp227_Registry'))
        {
            return NULL;
        }

        // Читаем напрямую: ORM-модель реестра падает на обращении к колонке
        // uuid (в таблице она называется external_uuid), а карточке нужны
        // только код, имя, статус и версия.
        $row = DB::select('id', 'external_uuid', 'code', 'name', 'status')
            ->from('erp_227_objects')
            ->where('id', '=', (int) $ref)
            ->and_where('is_deleted', '=', FALSE)
            ->execute()
            ->current();

        if ( ! $row)
        {
            return NULL;
        }

        $fields = array();

        if ($row['code'])
        {
            $fields[] = array('label' => 'Код', 'value' => (string) $row['code']);
        }

        if ($with_stock AND class_exists('Service_Erp401_Stock'))
        {
            $qty = Service_Erp401_Stock::instance()->total_qty((int) $row['id']);
            $fields[] = array('label' => 'Остаток по всем складам', 'value' => number_format((float) $qty, 3, '.', ' '));
        }

        return array(
            'kind'     => $with_stock ? 'item' : 'registry',
            'id'       => (int) $row['id'],
            'uuid'     => (string) $row['external_uuid'],
            'label'    => (string) $row['name'],
            'subtitle' => $with_stock ? 'Номенклатура реестра' : 'Объект реестра',
            'status'   => (string) $row['status'],
            'url'      => '/registry/objects/view/'.(int) $row['id'],
            'editable' => TRUE,
            'fields'   => $fields,
        );
    }

    /**
     * Партия / серийный номер erp412.
     *
     * @param   mixed  $ref
     * @return  array|null
     */
    protected function lot_card($ref)
    {
        if ( ! class_exists('Service_Erp412_Traceability'))
        {
            return NULL;
        }

        return $this->lot_card_from_row(Service_Erp412_Traceability::instance()->get($ref));
    }

    /**
     * @param   array  $row
     * @return  array
     */
    protected function lot_card_from_row(array $row)
    {
        return array(
            'kind'     => 'lot',
            'id'       => (int) $row['id'],
            'uuid'     => (string) $row['uuid'],
            'label'    => trim((string) $row['lot_number'].($row['serial_number'] ? ' · S/N '.$row['serial_number'] : '')),
            'subtitle' => trim('Партия'.($row['item_name'] ? ' · '.$row['item_name'] : '')),
            'status'   => (string) $row['status'],
            'url'      => '/traceability?lot='.(string) $row['uuid'],
            'editable' => FALSE,
            'fields'   => array_values(array_filter(array(
                ! empty($row['item_code']) ? array('label' => 'Код номенклатуры', 'value' => $row['item_code']) : NULL,
                isset($row['qty_trace']) ? array('label' => 'Прослеживаемое количество', 'value' => number_format((float) $row['qty_trace'], 3, '.', ' ')) : NULL,
            ))),
        );
    }

    /**
     * Производственный заказ erp402.
     *
     * @param   mixed  $ref
     * @return  array|null
     */
    protected function order_card($ref)
    {
        if ( ! class_exists('Service_Erp402_Orders'))
        {
            return NULL;
        }

        // Сервис заказов отдаёт ORM-модель (или NULL, если заказа нет).
        $order = Service_Erp402_Orders::instance()->get($ref);

        return $order ? $this->order_card_from_row($order->as_array()) : NULL;
    }

    /**
     * @param   array  $row
     * @return  array
     */
    protected function order_card_from_row(array $row)
    {
        return array(
            'kind'     => 'order',
            'id'       => (int) $row['id'],
            'uuid'     => (string) $row['uuid'],
            'label'    => 'Заказ '.$row['number'].' · '.$row['title'],
            'subtitle' => 'Производственный заказ',
            'status'   => (string) $row['status'],
            'url'      => '/production/orders/view/'.(int) $row['id'],
            'editable' => FALSE,
            'fields'   => array(
                array('label' => 'Выпуск', 'value' => number_format((float) $row['qty_done'], 3, '.', ' ')
                    .' / '.number_format((float) $row['qty'], 3, '.', ' ').' '.$row['uom_code']),
            ),
        );
    }

    /**
     * Сотрудник erp310.
     *
     * @param   mixed  $ref
     * @return  array|null
     */
    protected function employee_card($ref)
    {
        if ( ! class_exists('Service_Erp310_Workforce'))
        {
            return NULL;
        }

        $row = Service_Erp310_Workforce::instance()->get((int) $ref);

        return $row ? $this->employee_card_from_row($row) : NULL;
    }

    /**
     * @param   array  $row
     * @return  array
     */
    protected function employee_card_from_row(array $row)
    {
        return array(
            'kind'     => 'employee',
            'id'       => (int) $row['id'],
            'uuid'     => (string) $row['uuid'],
            'label'    => trim($row['last_name'].' '.$row['first_name'].' '.$row['middle_name']),
            'subtitle' => trim('Сотрудник'.($row['position_name'] ? ' · '.$row['position_name'] : '')),
            'status'   => (string) $row['status'],
            'url'      => '/workforce/employees/view/'.(int) $row['id'],
            'editable' => FALSE,
            'fields'   => array_values(array_filter(array(
                ! empty($row['personnel_no']) ? array('label' => 'Табельный номер', 'value' => $row['personnel_no']) : NULL,
                ! empty($row['department_name']) ? array('label' => 'Подразделение', 'value' => $row['department_name']) : NULL,
            ))),
        );
    }

    /**
     * Текст атрибутов для карточки.
     *
     * @param   array  $attributes
     * @return  string
     */
    protected function attribute_text(array $attributes)
    {
        $parts = array();

        foreach ($attributes as $key => $value)
        {
            if (is_array($value))
            {
                continue;
            }

            $parts[] = $key.': '.$value;
        }

        return implode('; ', array_slice($parts, 0, 6));
    }
}
