<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Технологическая карта (erp228_tech_cards).
 *
 * Хранит исходные данные изделия (код OpenSCAD, тип производства, ограничения)
 * и состояние генерации техпроцесса. Результаты генерации лежат в связанных
 * документах (Model_Erp228_TechDocument), шаги процесса — в операциях.
 *
 * @package    Erp228
 * @category   Models
 *
 * @property int    $id
 * @property string $uuid
 * @property string $target_type
 * @property int    $target_id
 * @property string $title
 * @property string $source_code
 * @property string $input_params
 * @property string $production_type
 * @property string $status
 * @property string $error_msg
 * @property string $search_text
 * @property int    $is_deleted
 * @property int    $created_by
 * @property string $created_at
 * @property string $updated_at
 */
class Model_Erp228_TechCard extends ORM
{
    protected $_table_name = 'erp_228_tech_cards';

    protected $_belongs_to = array(
        'creator' => array(
            'model'       => 'User',
            'foreign_key' => 'created_by',
        ),
    );

    protected $_has_many = array(
        'documents' => array(
            'model'       => 'Erp228_TechDocument',
            'foreign_key' => 'tech_card_id',
        ),
    );

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    /** Статусы генерации: машинный код => подпись. */
    public static $statuses = array(
        'draft'      => 'Черновик',
        'pending'    => 'В очереди',
        'processing' => 'Генерация',
        'ready'      => 'Готово',
        'error'      => 'Ошибка',
    );

    /** Цвета бейджей статуса для интерфейса. */
    public static $status_colors = array(
        'draft'      => 'gray',
        'pending'    => 'amber',
        'processing' => 'blue',
        'ready'      => 'green',
        'error'      => 'red',
    );

    /** Типы производства: машинный код => подпись. */
    public static $production_types = array(
        'additive'   => 'Аддитивные технологии (3D-печать)',
        'mechanical' => 'Механическая обработка (ЧПУ)',
        'manual'     => 'Ручная сборка',
    );

    /** Типы исходного объекта. */
    public static $target_types = array(
        'parametric' => 'Параметрическая модель',
        'stl'        => 'Готовая 3D-модель (STL)',
        'manual'     => 'Описано вручную',
    );

    /** Статусы, при которых карту можно править. */
    protected static $_editable_statuses = array('draft', 'error', 'ready');

    public function rules()
    {
        return array(
            'title' => array(
                array('not_empty'),
                array('max_length', array(':value', 255)),
            ),
            'source_code' => array(
                array('not_empty'),
            ),
            'production_type' => array(
                array('not_empty'),
                array('in_array', array(':value', array_keys(self::$production_types))),
            ),
            'target_type' => array(
                array('not_empty'),
                array('in_array', array(':value', array_keys(self::$target_types))),
            ),
            'status' => array(
                array('in_array', array(':value', array_keys(self::$statuses))),
            ),
            'target_id' => array(
                array('digit'),
            ),
            'created_by' => array(
                array('digit'),
            ),
        );
    }

    public function labels()
    {
        return array(
            'title'           => 'Название техкарты',
            'target_type'     => 'Тип объекта',
            'target_id'       => 'Ссылка на объект',
            'source_code'     => 'Исходный код (OpenSCAD)',
            'input_params'    => 'Параметры производства',
            'production_type' => 'Тип производства',
            'status'          => 'Статус',
            'error_msg'       => 'Ошибка генерации',
            'created_by'      => 'Автор',
        );
    }

    public function filters()
    {
        return array(
            'title' => array(
                array('trim'),
                array('strip_tags'),
            ),
            'source_code' => array(
                array('trim'),
            ),
        );
    }

    /**
     * Входные параметры карты (колонка jsonb) в виде массива.
     *
     * @return array
     */
    public function decode_params()
    {
        $params = $this->input_params;

        if (is_array($params))
        {
            return $params;
        }

        $decoded = json_decode((string) $params, TRUE);

        return is_array($decoded) ? $decoded : array();
    }

    /**
     * Подпись статуса для интерфейса.
     *
     * @return string
     */
    public function status_label()
    {
        return isset(self::$statuses[$this->status])
            ? self::$statuses[$this->status]
            : (string) $this->status;
    }

    /**
     * Подпись типа производства.
     *
     * @return string
     */
    public function production_label()
    {
        return isset(self::$production_types[$this->production_type])
            ? self::$production_types[$this->production_type]
            : (string) $this->production_type;
    }

    /**
     * Актуальный документ карты (последняя версия техпроцесса).
     *
     * @return Model_Erp228_TechDocument
     */
    public function actual_document()
    {
        return $this->documents
            ->where('is_actual', '=', 1)
            ->find();
    }

    /**
     * Сколько версий документа накоплено.
     *
     * @return int
     */
    public function documents_count()
    {
        return (int) ORM::factory('Erp228_TechDocument')
            ->where('tech_card_id', '=', $this->id)
            ->count_all();
    }

    /**
     * Краткая сводка для виджетов других модулей.
     *
     * @return array
     */
    public function summary()
    {
        return array(
            'id'      => (int) $this->id,
            'uuid'    => $this->uuid,
            'title'   => $this->title,
            'status'  => $this->status,
            'status_label' => $this->status_label(),
            'production_type' => $this->production_type,
            'is_ready' => ($this->status === 'ready'),
        );
    }

    /**
     * UUID v4 для новой карты.
     *
     * ORM не читает значения по умолчанию из БД, поэтому uuid генерируем сами —
     * так он есть и до вставки (его же отдаём наружу как публичный идентификатор).
     *
     * @return string
     */
    public static function make_uuid()
    {
        $data = random_bytes(16);

        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Новая запись получает uuid до вставки.
     *
     * Колонку `uuid` заполняет и база (`gen_random_uuid()`), но выданное
     * значение модель не знает до перечитывания строки — а оно нужно тут же:
     * в журнале действий (`erp119`), событиях и ответе JSON API. Пустой uuid в
     * этих местах означает «ссылку на объект потеряли»: запись журнала
     * оказывается ни к чему не привязанной. Поэтому идентификатор выдаётся
     * помощником `Erp::uuid()` до сохранения, а серверный default остаётся
     * подстраховкой для вставок в обход ORM.
     *
     * @param   Validation|null $validation
     * @return  ORM
     */
    public function save(?Validation $validation = NULL)
    {
        if ( ! $this->loaded() AND (string) $this->uuid === '')
        {
            $this->uuid = Erp::uuid();
        }

        return parent::save($validation);
    }

}
