<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель универсального реестра объектов ERP.
 * Обеспечивает хранение сущностей, поддержку иерархии (ltree) и версионирование.
 *
 * @property int        $id                 Идентификатор объекта
 * @property string     $external_uuid      Уникальный UUID для внешних интеграций
 * @property int        $type_id            ID типа объекта (ссылка на Model_Erp227_Type)
 * @property int|null   $parent_id          ID родительского объекта
 * @property string     $kind               Вид записи (definition, instance и т.д.)
 * @property string     $code               Артикул, табельный номер или код объекта
 * @property string     $name               Наименование объекта
 * @property int        $revision           Номер ревизии (версии)
 * @property bool       $is_latest          Флаг актуальной версии
 * @property string     $path               Путь в дереве (PostgreSQL ltree: 1.5.12)
 * @property string     $attributes         JSONB: дополнительные атрибуты по схеме типа
 * @property string     $current_state      JSONB: текущее состояние/метрики объекта
 * @property array      $tags               TEXT[]: массив тегов
 * @property string     $metadata           JSONB: метаданные объекта
 * @property string     $status             Status (active, draft, archived)
 * @property string     $created_at         Дата создания
 * @property string     $updated_at         Дата последнего обновления
 * @property int        $created_by         ID пользователя-создателя
 * @property int        $updated_by         ID пользователя-редактора
 * @property bool       $is_deleted         Флаг мягкого удаления
 * @property int|null   $merged_into_id     ID объекта, в который был объединен текущий
 * @property string     $search_vector      TSVector для полнотекстового поиска
 *
 * @property Model_Erp227_Type    $type     Тип объекта
 * @property Model_Erp227_Object  $parent   Родительский объект
 * @property ORM|Model_Erp227_Object[] $children Дочерние объекты
 * @property ORM|Model_Erp227_Link[]   $links    Исходящие связи (BOM, назначения)
 */
class Model_Erp227_Object extends Model_Erp227_Base {

    protected $_table_name = 'erp_227_objects';
    
    protected $_primary_key = 'id';

    protected $_belongs_to = [
        'type' => [
            'model'       => 'Erp227_Type',
            'foreign_key' => 'type_id',
        ],
        'parent' => [
            'model'       => 'Erp227_Object',
            'foreign_key' => 'parent_id',
        ],
    ];

    protected $_has_many = [
        'children' => [
            'model'       => 'Erp227_Object',
            'foreign_key' => 'parent_id',
        ],
        'links' => [
            'model'       => 'Erp227_Link',
            'foreign_key' => 'source_id',
        ],
    ];

    protected $_created_column = [
        'column' => 'created_at',
        'format' => 'Y-m-d H:i:s',
    ];

    protected $_updated_column = [
        'column' => 'updated_at',
        'format' => 'Y-m-d H:i:s',
    ];

    /**
     * Переопределяем метод set для корректного приведения типов PostgreSQL
     */
    public function set($column, $value)
    {
        if ($column === 'is_deleted' OR $column === 'is_latest')
        {
            $value = (bool) $value;
        }

        return parent::set($column, $value);
    }

    /**
     * Правила валидации данных
     *
     * @return array
     */
    public function rules()
    {
        return [
            'type_id' => [
                ['not_empty'],
                ['digit'],
            ],
            'code' => [
                // ['not_empty'],
                ['max_length', [':value', 100]],
            ],
            'name' => [
                ['not_empty'],
                ['max_length', [':value', 500]],
            ],
            'kind' => [
                ['max_length', [':value', 20]],
            ],
            'status' => [
                ['max_length', [':value', 30]],
            ],
            'revision' => [
                ['digit'],
            ],
            'parent_id' => [
                ['digit'],
            ],
        ];
    }

    /**
     * Метки полей (русский язык)
     *
     * @return array
     */
    public function labels()
    {
        return [
            'id'              => 'ID',
            'external_uuid'   => 'UUID',
            'type_id'         => 'Тип объекта',
            'parent_id'       => 'Родитель',
            'code'            => 'Код / Артикул',
            'name'            => 'Наименование',
            'kind'            => 'Вид',
            'revision'        => 'Ревизия',
            'is_latest'       => 'Актуальная версия',
            'status'          => 'Статус',
            'attributes'      => 'Атрибуты',
            'tags'            => 'Теги',
            'metadata'        => 'Метаданные',
            'created_at'      => 'Дата создания',
            'updated_at'      => 'Дата обновления',
        ];
    }

    /**
     * Фильтры очистки данных
     *
     * @return array
     */
    public function filters()
    {
        return [
            'code' => [
                ['trim'],
                ['strip_tags'],
            ],
            'name' => [
                ['trim'],
                ['strip_tags'],
            ],
            'kind' => [
                ['trim'],
            ],
        ];
    }

    /**
     * Получение значения атрибута из JSONB поля attributes
     * 
     * @param string $key Ключ атрибута
     * @param mixed $default Значение по умолчанию
     * @return mixed
     */
    public function get_attribute($key, $default = NULL)
    {
        $attrs = $this->attributes;
        
        if (is_string($attrs))
        {
            $attrs = json_decode($attrs, TRUE);
        }

        if (is_array($attrs) AND array_key_exists($key, $attrs))
        {
            return $attrs[$key];
        }

        return $default;
    }

    /**
     * Scope: Только актуальные версии объектов
     * 
     * @return $this
     */
    public function latest()
    {
        return $this->where($this->_object_name . '.is_latest', '=', true);
    }

    /**
     * Scope: Исключить удаленные записи
     * 
     * @return $this
     */
    public function active()
    {
        return $this->where($this->_object_name . '.is_deleted', '=', false);
    }

    /**
     * Scope: Фильтрация по коду типа
     * 
     * @param string $type_code
     * @return $this
     */
    public function filter_by_type($type_code)
    {
        return $this->join(['erp_227_object_types', 'ot'])
            ->on($this->_object_name . '.type_id', '=', 'ot.id')
            ->where('ot.code', '=', $type_code);
    }
}