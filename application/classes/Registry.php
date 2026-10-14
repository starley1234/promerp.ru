<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Глобальный прокси-класс для работы с универсальным реестром объектов ERP.
 * Обеспечивает статический интерфейс к Service_Erp227_Registry и Service_Erp227_Search.
 * 
 * Доступные атрибуты объекта (DB: erp_227_objects):
 * @property int    $id             Первичный ключ
 * @property string $external_uuid  UUID для внешних интеграций
 * @property int    $type_id        ID типа из erp_227_object_types
 * @property int    $parent_id      ID родительского объекта
 * @property string $kind           Тип записи (definition, instance)
 * @property string $code           Уникальный код (артикул/инвентарный номер)
 * @property string $name           Наименование
 * @property int    $revision       Номер версии
 * @property bool   $is_latest      Флаг актуальной ревизии
 * @property string $path           Ltree путь в иерархии
 * @property array  $attributes     JSONB: технические характеристики
 * @property array  $current_state  JSONB: текущие показатели/датчики
 * @property array  $tags           Массив тегов (TEXT[])
 * @property array  $metadata       JSONB: системные метаданные
 * @property string $status         Статус (active, maintenance, deleted и др.)
 * 
 * @package Erp227
 * @version 1.6.1
 */
class Registry {

    /**
     * Получить экземпляр основного сервиса реестра
     * @return Service_Erp227_Registry
     */
    public static function service()
    {
        return Service_Erp227_Registry::instance();
    }

    /**
     * Получить экземпляр сервиса поиска
     * @return Service_Erp227_Search
     */
    public static function search_service()
    {
        return Service_Erp227_Search::instance();
    }

    /**
     * Найти объект по коду и/или типу
     * @param string $code Код объекта
     * @param string|int|null $type Системное имя типа или ID типа
     * @return Model_Erp227_Object
     */
    public static function get($code, $type = NULL)
    {
        return self::service()->get_object($code, $type);
    }

    /**
     * Получить объект по его первичному ключу (ID)
     * @param int $id
     * @return Model_Erp227_Object
     */
    public static function get_by_id($id)
    {
        return self::service()->get_by_id($id);
    }

    /**
     * Получить объект по UUID
     * @param string $uuid
     * @return Model_Erp227_Object
     */
    public static function get_by_uuid($uuid)
    {
        return self::service()->get_by_uuid($uuid);
    }

    /**
     * Найти коллекцию объектов по атрибутам в JSONB
     * @param array $attributes Ассоциативный массив атрибутов
     * @param array $filters Дополнительные фильтры (type_id, status, kind)
     * @param int $limit
     * @return Model_Erp227_Object[]
     */
    public static function find_by_attributes(array $attributes, array $filters = [], $limit = 100)
    {
        return self::search_service()->find_by_attributes($attributes, $filters, $limit);
    }

    /**
     * Создать новый объект в реестре
     * @param array $data
     * @return Model_Erp227_Object
     */
    public static function create_object(array $data)
    {
        return self::service()->create_object($data);
    }

    /**
     * Обновить существующий объект
     * @param int $id
     * @param array $data
     * @return Model_Erp227_Object
     */
    public static function update_object($id, array $data)
    {
        return self::service()->update_object($id, $data);
    }

    /**
     * Создать новую ревизию (версию) существующего объекта
     * @param int $id ID текущей версии
     * @param array $changes Измененные поля
     * @return Model_Erp227_Object
     */
    public static function create_revision($id, array $changes)
    {
        return self::service()->create_revision($id, $changes);
    }

    /**
     * Получить все ревизии объекта по его коду
     * @param string $code
     * @param string|null $type
     * @return Database_Result
     */
    public static function get_revisions($code, $type = NULL)
    {
        return self::service()->get_revisions($code, $type);
    }

    /**
     * Получить историю изменений объекта
     * @param int $id
     * @return array
     */
    public static function get_history($id)
    {
        return self::service()->get_history($id);
    }

    /**
     * Удалить объект (мягкое удаление)
     * @param int $id
     * @return bool
     */
    public static function delete_object($id)
    {
        return self::service()->delete_object($id);
    }

    /**
     * Изменить статус объекта
     * @param int $id
     * @param string $new_status
     * @return Model_Erp227_Object
     */
    public static function change_status($id, $new_status)
    {
        return self::service()->change_status($id, $new_status);
    }

    /**
     * Добавить связь между объектами
     * @param int $source_id Откуда
     * @param int $target_id Куда
     * @param string $type Тип связи (link_type)
     * @param float $qty Количество/Вес
     * @param mixed $extra Дополнительные данные (unit или массив с data)
     * @return Model_Erp227_Link
     */
    public static function link($source_id, $target_id, $type, $qty = 1.0, $extra = [])
    {
        return self::service()->add_link($source_id, $target_id, $type, $qty, $extra);
    }

    /**
     * Удалить связь
     * @param int $link_id
     * @return bool
     */
    public static function unlink($link_id)
    {
        return self::service()->delete_link($link_id);
    }

    /**
     * Получить все связи объекта
     * @param int $id
     * @param string $direction incoming|outgoing
     * @param string|null $type Фильтр по типу связи
     * @return Database_Result
     */
    public static function get_links($id, $direction = 'outgoing', $type = NULL)
    {
        return self::service()->get_links($id, $direction, $type);
    }

    /**
     * Получить дерево дочерних объектов (первый уровень)
     * @param int $parent_id
     * @param string|int|null $type_filter
     * @return Database_Result
     */
    public static function get_children($parent_id, $type_filter = NULL)
    {
        return self::service()->get_children($parent_id, $type_filter);
    }

    /**
     * Получить иерархическое дерево объектов
     * @param int $parent_id
     * @param int $depth Глубина вложенности
     * @return array
     */
    public static function get_tree($parent_id, $depth = 1)
    {
        return self::service()->get_tree($parent_id, $depth);
    }

    /**
     * Получить полную цепочку родителей (хлебные крошки)
     * @param int $id
     * @return Database_Result
     */
    public static function get_path($id)
    {
        return self::service()->get_path($id);
    }

    /**
     * Выполнить гибридный поиск по реестру
     * @param string $query Поисковый запрос
     * @param array $filters Фильтры (type_id, parent_id, status)
     * @param int $limit
     * @return array [results, total]
     */
    public static function search($query, array $filters = [], $limit = 20)
    {
        return self::search_service()->execute($query, $filters, $limit);
    }

    /**
     * Получить список всех типов объектов
     * @return Database_Result
     */
    public static function get_types()
    {
        return self::service()->get_types();
    }

    /**
     * Получить конкретный тип объекта по коду
     * @param string $code
     * @return Model_Erp227_Type
     */
    public static function get_type($code)
    {
        return self::service()->get_type_by_code($code);
    }

    /**
     * Создать новый тип объекта
     * @param array $data
     * @return Model_Erp227_Type
     */
    public static function create_type(array $data)
    {
        return self::service()->create_type($data);
    }

    /**
     * Получить список единиц измерения
     * @return Database_Result
     */
    public static function get_units()
    {
        return self::service()->get_units();
    }

    /**
     * Проверить существование объекта по коду
     * @param string $code
     * @param string|null $type
     * @return bool
     */
    public static function exists($code, $type = NULL)
    {
        $obj = self::get($code, $type);
        return ($obj AND $obj->loaded());
    }

    /**
     * Записать событие или телеметрию
     * 
     * @param int $object_id
     * @param string $event_type (telemetry, status_change, maintenance, etc)
     * @param array $payload Контекст события
     * @param mixed $value Числовое или строковое значение
     */
    public static function log_event($object_id, $event_type, array $payload = [], $value = NULL)
    {
        Event::fire('erp227.registry.event', [
            'object_id' => $object_id,
            'type'      => $event_type,
            'payload'   => $payload,
            'value'     => $value,
            'time'      => date('Y-m-d H:i:s')
        ]);

        return self::service()->log_event($object_id, $event_type, $payload, $value);
    }
}