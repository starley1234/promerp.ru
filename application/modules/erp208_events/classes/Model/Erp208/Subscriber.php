<?php defined('SYSPATH') or die('No direct script access.');

/**
 * @package Erp208
 * @version 1.0.0
 * @author AI
 */
class Model_Erp208_Subscriber extends ORM {

    protected $_table_name = 'erp_208_event_subscribers';
    protected $_primary_key = 'id';

    protected $_table_columns = array(
        'id'              => array('type' => 'int'),
        'event_name'      => array('type' => 'string'),
        'module_id'       => array('type' => 'string'),
        'callback_method' => array('type' => 'string'),
        'is_active'       => array('type' => 'boolean'),
        'is_notification'   => array('type' => 'is_notification'),
        'description' => array('type' => 'string'),
        'created_at'      => array('type' => 'timestamp'),
        
    );

    /**
     * Определяет связи "один ко многим" (has many).
     * В данной модели нет прямых связей "один ко многим" с другими моделями.
     * Подписчик сам по себе является конечной сущностью, описывающей подписку.
     * Если бы, например, у подписчика были "логи вызовов", то это было бы has_many.
     */
    protected $_has_many = array(
        // 'call_logs' => array(
        //     'model'       => 'Erp208_SubscriberCallLog', // Пример: если бы была модель для логов вызовов подписчика
        //     'foreign_key' => 'subscriber_id',
        // ),
    );

    /**
     * Определяет связи "многие к одному" (belongs to).
     * Подписчик не принадлежит напрямую к какой-либо другой модели в контексте ORM.
     * `module_id` является строковым идентификатором модуля, а не внешним ключом к таблице модулей.
     * `event_name` является строковым названием события, а не внешним ключом к таблице событий.
     */
    protected $_belongs_to = array(
        // 'event' => array(
        //     'model'       => 'Erp208_Event', // Если бы event_name был внешним ключом к таблице erp_208_events
        //     'foreign_key' => 'event_name',
        // ),
        // 'module' => array(
        //     'model'       => 'Erp_Module', // Если бы module_id был внешним ключом к таблице модулей
        //     'foreign_key' => 'module_id',
        // ),
    );

    /**
     * Правила валидации для полей модели.
     *
     * @return array
     */
    public function rules()
    {
        return array(
            'event_name' => array(
                array('not_empty'),
                array('max_length', array(':value', 255)),
            ),
            'module_id' => array(
                array('max_length', array(':value', 50)),
            ),
            'callback_method' => array(
                array('max_length', array(':value', 255)),
            ),
            'is_active' => array(
                array('not_empty'),
                array('in_array', array(':value', array(TRUE, FALSE))),
            ),
        );
    }

    /**
     * Метки полей для использования в сообщениях об ошибках.
     *
     * @return array
     */
    public function labels()
    {
        return array(
            'event_name'      => __('Event Name'),
            'module_id'       => __('Module ID'),
            'callback_method' => __('Callback Method'),
            'is_active'       => __('Is Active'),
        );
    }

    /**
     * Проверяет, существует ли уже подписка с такими же event_name, module_id и callback_method.
     *
     * @param string $event_name
     * @param string $module_id
     * @param string $callback_method
     * @return bool
     */
    public function is_duplicate($event_name, $module_id, $callback_method)
    {
        return (bool) DB::select(array(DB::expr('COUNT(*)'), 'total_count'))
            ->from($this->_table_name)
            ->where('event_name', '=', $event_name)
            ->and_where('module_id', '=', $module_id)
            ->and_where('callback_method', '=', $callback_method)
            ->execute($this->_db)
            ->get('total_count');
    }
}