<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель для работы с шаблонами уведомлений (Email, Telegram, Push и т.д.)
 * 
 * @package    ERP217
 * @category   Model
 * @author     Generated for Notification Center
 */
class Model_Erp217_Template extends ORM {

    protected $_table_name = 'erp_217_templates';

    // Связи модели
    protected $_has_many = array(
        'notification' => array(
            'model'       => 'Erp217_Notification',
            'foreign_key' => 'template_id',
        ),
    );

    // Автоматическое управление датами
    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    /**
     * Правила валидации
     */
    public function rules()
    {
        return array(
            'alias' => array(
                array('not_empty'),
                array('max_length', array(':value', 100)),
            ),
            'channel' => array(
                array('not_empty'),
                array('in_array', array(':value', array('email', 'telegram', 'max', 'push'))),
            ),
            'subject' => array(
                array('max_length', array(':value', 255)),
            ),
            'body' => array(
                array('not_empty'),
            ),
            'created_by' => array(
                array('not_empty'),
                array('digit'),
            ),
        );
    }

    /**
     * Метки полей (для форм и ошибок)
     */
    public function labels()
    {
        return array(
            'alias'       => 'Код шаблона',
            'channel'     => 'Канал связи',
            'subject'     => 'Тема сообщения',
            'body'        => 'Текст шаблона',
            'description' => 'Описание',
            'created_by'  => 'Автор',
        );
    }

    /**
     * Фильтры данных
     */
    public function filters()
    {
        return array(
            'alias' => array(
                array('trim'),
            ),
            'subject' => array(
                array('trim'),
            ),
        );
    }


    /**
     * Поиск активного шаблона по алиасу и каналу
     * 
     * @param string $alias
     * @param string $channel
     * @return Model_Erp217_Template
     */
    public static function find_by_alias($alias, $channel)
    {
        return ORM::factory('Erp217_Template')
            ->where('alias', '=', $alias)
            ->where('channel', '=', $channel)
            ->where('is_deleted', '=', 0)
            ->find();
    }

    /**
     * Получить список доступных каналов
     * @return array
     */
    public static function get_channels()
    {
        return array(
            'email'    => 'Email',
            'telegram' => 'Telegram',
            'push'     => 'Push-уведомление',
            'max'      => 'Система Max'
        );
    }
}