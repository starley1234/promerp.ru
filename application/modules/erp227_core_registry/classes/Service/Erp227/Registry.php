<?php defined('SYSPATH') or die('No direct script access.'); 

/**
 * Сервис управления универсальным реестром объектов ERP.
 * Центральная логика: ревизии, иерархия, валидация JSON-схем и контроль циклов.
 *
 * @package Erp227
 * @version 1.7.6
 */
class Service_Erp227_Registry {

    /**
     * @var Service_Erp227_Registry
     */
    protected static $_instance;

    /**
     * @return Service_Erp227_Registry
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
     * Получить объект по ID
     * 
     * @param int $id
     * @return Model_Erp227_Object
     */
    public function get_by_id($id)
    {
        return ORM::factory('Erp227_Object', $id);
    }

    /**
     * Получить объект по коду и типу
     * 
     * @param string $code
     * @param string|int|null $type Код типа или ID типа
     * @return Model_Erp227_Object
     */
    public function get_object($code, $type = NULL)
    {
        $query = ORM::factory('Erp227_Object');
        $alias = $query->object_name();

        $query->where($alias . '.code', '=', (string)$code)
            ->where($alias . '.is_latest', '=', TRUE)
            ->where($alias . '.is_deleted', '=', FALSE);

        if ($type !== NULL)
        {
            if (is_numeric($type))
            {
                $query->where($alias . '.type_id', '=', (int)$type);
            }
            else
            {
                $query->filter_by_type($type);
            }
        }

        return $query->find();
    }

    /**
     * Создание нового объекта в реестре
     * 
     * @param array $data Данные объекта
     * @return Model_Erp227_Object
     * @throws Exception|ORM_Validation_Exception
     */
    public function create_object(array $data)
    {
        $db = Database::instance();
        $db->begin();

        try 
        {
            if (isset($data['type']) AND ! isset($data['type_id'])) 
            {
                $type = ORM::factory('Erp227_Type')
                    ->where('code', '=', $data['type'])
                    ->find();
                    
                if ( ! $type->loaded())
                {
                    throw new Kohana_Exception("Object type ':type' not found", [':type' => $data['type']]);
                }
                $data['type_id'] = $type->id;
            }

            // Внедрение Нумератора: если код не задан, генерируем его автоматически
            if (empty($data['code']) AND isset($data['type_id']))
            {
                // Вызываем строго один раз для предотвращения дублей и лишних инкрементов
                $data['code'] = Model_Erp227_Sequence::next_code($data['type_id']);
            }

            // Проверка на существование активного объекта с таким же кодом
            if ( ! empty($data['code']) AND isset($data['type_id']))
            {
                $existing = $this->get_object($data['code'], $data['type_id']);
                if ($existing->loaded())
                {
                    // Если объект уже есть, перенаправляем на создание ревизии вместо вставки нового
                    $db->rollback();
                    return $this->update_object($existing->id, $data);
                }
            }

            $object = ORM::factory('Erp227_Object');
            
            if (isset($data['parent_id']) AND (empty($data['parent_id']) OR $data['parent_id'] == 0))
            {
                $data['parent_id'] = NULL;
            }

            // Подготовка JSONB полей
            foreach (['attributes', 'current_state', 'metadata'] as $field)
            {
                if (isset($data[$field]) AND is_array($data[$field]))
                {
                    $data[$field] = json_encode($data[$field], JSON_UNESCAPED_UNICODE);
                }
            }

            // Обработка тегов (PostgreSQL TEXT[])
            if (isset($data['tags']) AND is_array($data['tags']))
            {
                $data['tags'] = '{' . implode(',', array_map(function($v) { return '"'.addslashes($v).'"'; }, $data['tags'])) . '}';
            }

            $object->values($data, [
                'type_id', 'parent_id', 'kind', 'code', 'name', 
                'attributes', 'current_state', 'tags', 'metadata', 'status',
                'created_by', 'updated_by', 'external_uuid'
            ]);
            
            $this->validate_attributes($object->type_id, $data['attributes'] ?? []);

            $object->save();
            $object->reload();

            $db->commit();
            return $object;
        }
        catch (Exception $e)
        {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Обновление объекта через создание новой ревизии
     */
    public function update_object($id, array $data)
    {
        return $this->create_revision($id, $data);
    }

    /**
     * Создание новой ревизии (версии) объекта
     */
    public function create_revision($id, array $changes)
    {
        $db = Database::instance();
        $db->begin();

        try 
        {
            $old_version = ORM::factory('Erp227_Object', $id);
            if ( ! $old_version->loaded())
                throw new Kohana_Exception('Object not found');

            DB::update('erp_227_objects')
                ->set(['is_latest' => false])
                ->where('code', '=', $old_version->code)
                ->where('type_id', '=', $old_version->type_id)
                ->execute();

            $new_version = ORM::factory('Erp227_Object');
            $data = $old_version->as_array();
            
            foreach ($changes as $key => $val)
            {
                $data[$key] = $val;
            }

            if (isset($data['parent_id']) AND (empty($data['parent_id']) OR $data['parent_id'] == 0))
            {
                $data['parent_id'] = NULL;
            }

            // Подготовка JSONB полей
            foreach (['attributes', 'current_state', 'metadata'] as $field)
            {
                if (isset($data[$field]) AND is_array($data[$field]))
                {
                    $data[$field] = json_encode($data[$field], JSON_UNESCAPED_UNICODE);
                }
            }

            // Обработка тегов (PostgreSQL TEXT[]) для ревизий
            if (isset($data['tags']) AND is_array($data['tags']))
            {
                $data['tags'] = '{' . implode(',', array_map(function($v) { return '"'.addslashes($v).'"'; }, $data['tags'])) . '}';
            }

            $new_version->values($data, [
                'type_id', 'parent_id', 'kind', 'code', 'name', 
                'attributes', 'current_state', 'tags', 'metadata', 'status', 'updated_by'
            ]);
            
            $new_version->revision = $old_version->revision + 1;
            $new_version->is_latest = true;
            
            $this->validate_attributes($new_version->type_id, $new_version->attributes);
            
            $new_version->save();
            // Database defaults and trigger-maintained values (notably UUID)
            // must be available to integration mappings immediately.
            $new_version->reload();

            Event::fire('erp227.object.revision', ['old_id' => $id, 'new_object' => $new_version]);
            
            $db->commit();
            return $new_version;
        }
        catch (Exception $e)
        {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Получить все ревизии объекта по его коду
     */
    public function get_revisions($code, $type = NULL)
    {
        $query = ORM::factory('Erp227_Object');
        $alias = $query->object_name();

        $query->where($alias . '.code', '=', $code)
            ->where($alias . '.is_deleted', '=', FALSE)
            ->order_by($alias . '.revision', 'DESC');

        if ($type !== NULL)
        {
            if (is_numeric($type))
                $query->where($alias . '.type_id', '=', (int)$type);
            else
                $query->filter_by_type($type);
        }

        return $query->find_all();
    }

    /**
     * Получить историю изменений объекта
     */
    public function get_history($id)
    {
        return ORM::factory('Erp227_Event')
            ->where('object_id', '=', $id)
            ->order_by('created_at', 'DESC')
            ->find_all();
    }

    /**
     * Мягкое удаление объекта
     */
    public function delete_object($id)
    {
        $object = ORM::factory('Erp227_Object', $id);
        if ($object->loaded()) 
        {
            $object->is_deleted = TRUE;
            $object->save();
            Event::fire('erp227.object.deleted', $object);
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Получить дочерние объекты
     */
    public function get_children($parent_id, $type_filter = NULL)
    {
        $query = ORM::factory('Erp227_Object');
        $alias = $query->object_name();

        $query->where($alias . '.parent_id', '=', $parent_id)
            ->where($alias . '.is_latest', '=', TRUE)
            ->where($alias . '.is_deleted', '=', FALSE);

        if ($type_filter)
        {
            if (is_numeric($type_filter))
                $query->where($alias . '.type_id', '=', $type_filter);
            else
                $query->filter_by_type($type_filter);
        }

        return $query->find_all();
    }

    /**
     * Получить иерархическое дерево объектов
     */
    public function get_tree($parent_id, $depth = 1)
    {
        $result = [];
        $children = $this->get_children($parent_id);

        foreach ($children as $child)
        {
            $node = $child->as_array();
            if ($depth > 1)
            {
                $node['children'] = $this->get_tree($child->id, $depth - 1);
            }
            $result[] = $node;
        }

        return $result;
    }

    /**
     * Получить путь к объекту (хлебные крошки) через ltree
     */
    public function get_path($id)
    {
        $object = ORM::factory('Erp227_Object', $id);
        if ( ! $object->loaded() OR ! $object->path)
            return [];

        $query = ORM::factory('Erp227_Object');
        $alias = $query->object_name();

        return $query->where($alias . '.path', '@>', $object->path)
            ->where($alias . '.is_latest', '=', TRUE)
            ->order_by($alias . '.path', 'ASC')
            ->find_all();
    }

    /**
     * Добавление связи
     */
    public function add_link($source_id, $target_id, $type, $qty = 1.0, $extra = [])
    {
        $db = Database::instance();
        $db->begin();

        try 
        {
            if ($source_id == $target_id)
                throw new Kohana_Exception('Self-linking is prohibited');

            if ($this->detect_cycle($source_id, $target_id))
                throw new Kohana_Exception('Circular dependency detected');

            $link = ORM::factory('Erp227_Link');
            $link->source_id = $source_id;
            $link->target_id = $target_id;
            $link->link_type = $type;
            $link->quantity  = $qty;

            if (is_array($extra))
            {
                $link->unit = $extra['unit'] ?? NULL;
                $link->sort_order = $extra['sort_order'] ?? 0;
                if (isset($extra['data']))
                {
                    $link->data = is_array($extra['data']) ? json_encode($extra['data'], JSON_UNESCAPED_UNICODE) : $extra['data'];
                }
            }
            else
            {
                $link->unit = $extra;
            }

            $link->save();
            Event::fire('erp227.link.added', ['link' => $link]);
            
            $db->commit();
            return $link;
        }
        catch (Exception $e)
        {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Получить связи объекта
     */
    public function get_links($id, $direction = 'outgoing', $type = NULL)
    {
        $query = ORM::factory('Erp227_Link')->where('is_deleted', '=', FALSE);
        
        if ($direction === 'incoming')
            $query->where('target_id', '=', $id);
        else
            $query->where('source_id', '=', $id);

        if ($type)
            $query->where('link_type', '=', $type);

        return $query->order_by('sort_order', 'ASC')->find_all();
    }

    /**
     * Обновление параметров связи
     */
    public function update_link($link_id, array $data)
    {
        $link = ORM::factory('Erp227_Link', $link_id);
        if ( ! $link->loaded()) 
            throw new Kohana_Exception('Link not found');
        
        $link->values($data, ['quantity', 'sort_order', 'unit', 'link_type']);
        
        if (isset($data['data']))
        {
            $link->data = is_array($data['data']) ? json_encode($data['data'], JSON_UNESCAPED_UNICODE) : $data['data'];
        }

        $link->save();
        Event::fire('erp227.link.updated', $link);
        return $link;
    }

    /**
     * Удаление связи (мягкое)
     */
    public function delete_link($link_id)
    {
        $link = ORM::factory('Erp227_Link', $link_id);
        if ($link->loaded())
        {
            $link->is_deleted = TRUE;
            $link->save();
            Event::fire('erp227.link.deleted', $link);
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Изменение статуса объекта
     */
    public function change_status($id, $new_status)
    {
        $object = ORM::factory('Erp227_Object', $id);
        if ( ! $object->loaded())
            throw new Kohana_Exception('Object not found');

        $old_status = $object->status;
        $object->status = $new_status;
        $object->save();

        Event::fire('erp227.object.status_changed', [
            'object' => $object,
            'old_status' => $old_status,
            'new_status' => $new_status
        ]);

        return $object;
    }

    /**
     * Получить список всех типов объектов
     */
    public function get_types()
    {
        return ORM::factory('Erp227_Type')->find_all();
    }

    /**
     * Получить тип объекта по коду
     */
    public function get_type_by_code($code)
    {
        return ORM::factory('Erp227_Type')
            ->where('code', '=', $code)
            ->find();
    }

    /**
     * Создать новый тип объекта
     */
    public function create_type(array $data)
    {
        $type = ORM::factory('Erp227_Type');
        
        if (isset($data['schema']) AND is_array($data['schema']))
            $data['schema'] = json_encode($data['schema'], JSON_UNESCAPED_UNICODE);
            
        if (isset($data['ui_config']) AND is_array($data['ui_config']))
            $data['ui_config'] = json_encode($data['ui_config'], JSON_UNESCAPED_UNICODE);

        $type->values($data, ['code', 'name', 'schema', 'ui_config', 'icon']);
        $type->save();
        return $type;
    }

    /**
     * Валидация JSON-атрибутов
     */
    public function validate_attributes($type_id, $attributes)
    {
        $type = ORM::factory('Erp227_Type', $type_id);
        if ( ! $type->loaded())
            throw new Kohana_Exception('Invalid object type');

        $schema = is_string($type->schema) ? json_decode($type->schema, TRUE) : $type->schema;
        if (empty($schema)) return TRUE;

        $data = is_array($attributes) ? $attributes : json_decode($attributes, TRUE);
        if (empty($data)) $data = [];
        
        $validation = Validation::factory($data);

        $has_rules = false;
        if (isset($schema['required']) && is_array($schema['required']))
        {
            foreach ($schema['required'] as $field)
            {
                $validation->rule($field, 'not_empty');
                $has_rules = true;
            }
        }

        if ($has_rules AND ! $validation->check())
        {
            throw new Kohana_Exception('Attribute validation failed: :errors', [
                ':errors' => json_encode($validation->errors('validation'))
            ]);
        }

        return TRUE;
    }

    /**
     * Проверка на наличие циклической зависимости
     */
    protected function detect_cycle($source_id, $target_id)
    {
        $target = ORM::factory('Erp227_Object', $target_id);
        $source = ORM::factory('Erp227_Object', $source_id);

        if ($target->loaded() && $source->loaded())
        {
            if ($target->path AND $source->path AND strpos($source->path, $target->path) === 0)
            {
                return TRUE;
            }
        }

        $query = DB::query(Database::SELECT, "
            WITH RECURSIVE search_graph(id) AS (
                SELECT target_id FROM erp_227_object_links WHERE source_id = :target AND is_deleted = false
                UNION
                SELECT l.target_id FROM erp_227_object_links l
                JOIN search_graph sg ON sg.id = l.source_id
                WHERE l.is_deleted = false
            )
            SELECT count(*) as cnt FROM search_graph WHERE id = :source
        ")
        ->parameters([
            ':target' => $target_id,
            ':source' => $source_id
        ])
        ->execute()
        ->get('cnt');

        return (int)$query > 0;
    }
}