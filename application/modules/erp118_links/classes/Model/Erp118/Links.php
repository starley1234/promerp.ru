<?php defined('SYSPATH') or die('No direct script access.');

class Model_Erp118_Links extends ORM { 

    protected $_table_name = 'lib_118_links';
    protected $_primary_key = 'id';

    protected $_table_columns = array(
        'id'           => array('type' => 'int'),
        'user_id'      => array('type' => 'int'),
        'url'          => array('type' => 'string'),
        'title'        => array('type' => 'string'),
        'description'  => array('type' => 'string'),
        'color'        => array('type' => 'string'),
        'emoji'        => array('type' => 'string'),
        'icon_class'   => array('type' => 'string'),
        'group_name'   => array('type' => 'string'),
        'is_menu_item' => array('type' => 'int'),
        'order'        => array('type' => 'int'),
        'created_at'   => array('type' => 'datetime'),
        'updated_at'   => array('type' => 'datetime'),
    );

    public function rules()
    {
        return array(
            'user_id' => array(
                array('not_empty'),
                array('digit'),
            ),
            'url' => array(
                array('not_empty'),
                array('url'),
                array('max_length', array(':value', 2048)),
            ),
            'title' => array(
                array('max_length', array(':value', 255)),
            ),
            'description' => array(
                array('max_length', array(':value', 65535)),
            ),
            'color' => array(
                array('regex', array(':value', '/^#[0-9a-fA-F]{6}$/')),
            ),
            'emoji' => array(
                array('max_length', array(':value', 10)),
            ),
            'icon_class' => array(
                array('max_length', array(':value', 50)),
            ),
            'group_name' => array(
                array('max_length', array(':value', 100)),
            ),
            'is_menu_item' => array(
                array('range', array(':value', 0, 1)),
            ),
            'order' => array(
                array('digit'),
            ),
        );
    }

    /**
     * Вспомогательный метод для безопасного получения экземпляра БД
     */
    protected function _get_db()
    {
        return ($this->_db instanceof Database) 
            ? $this->_db 
            : Database::instance($this->_db);
    }

    public function get_user_links($user_id, $only_menu = FALSE)
    {
        $query = $this->where('user_id', '=', (int) $user_id);
        
        if ($only_menu)
        {
            $query->and_where('is_menu_item', '=', 1);
        }

        return $query->order_by('order', 'ASC')->find_all();
    }

    public function toggle_link($user_id, array $data)
    {
        $link = $this->where('user_id', '=', (int) $user_id)
            ->and_where('url', '=', $data['url'])
            ->find();

        if ($link->loaded())
        {
            $link->delete();
            return array('action' => 'deleted');
        }

        $this->values($data);
        $this->user_id = $user_id;
        
        $max_order = DB::select(array(DB::expr('MAX("order")'), 'max_order'))
            ->from($this->_table_name)
            ->where('user_id', '=', (int) $user_id)
            ->execute($this->_get_db()) 
            ->get('max_order');

        $this->order = ($max_order !== NULL) ? (int) $max_order + 1 : 0;
        $this->save();

        return array('action' => 'saved', 'id' => $this->id);
    }

    public function get_user_groups($user_id)
    {
        return DB::select('group_name')
            ->distinct(TRUE)
            ->from($this->_table_name)
            ->where('user_id', '=', (int) $user_id)
            ->where('group_name', 'IS NOT', NULL)
            ->where('group_name', '!=', '')
            ->execute($this->_get_db())
            ->as_array(NULL, 'group_name');
    }

    public function update_orders($user_id, array $order_data)
    {
        $db = $this->_get_db();
        $db->begin();
        try
        {
            foreach ($order_data as $item)
            {
                DB::update($this->_table_name)
                    ->set(array('order' => (int) $item['order']))
                    ->where('id', '=', (int) $item['id'])
                    ->where('user_id', '=', (int) $user_id)
                    ->execute($db);
            }
            $db->commit();
            return TRUE;
        }
        catch (Exception $e)
        {
            $db->rollback();
            throw $e;
        }
    }
}