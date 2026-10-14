<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель для работы с логами отправки уведомлений (Архив ответов API).
 * 
 * @package    ERP
 * @category   Model
 * @author     Development Team
 */
class Model_Erp217_Log extends ORM {

    protected $_table_name = 'erp_217_logs';

    protected $_belongs_to = array(
        'notification' => array(
            'model'       => 'Erp217_Notification',
            'foreign_key' => 'queue_id',
        ),
        'user' => array(
            'model'       => 'User',
            'foreign_key' => 'user_id',
        ),
        'creator' => array(
            'model'       => 'User',
            'foreign_key' => 'created_by',
        ),
    );

    /**
     * Правила валидации
     * @return array
     */
    public function rules()
    {
        return array(
            'queue_id'      => array(array('not_empty'), array('digit')),
            'user_id'       => array(array('digit')),
            'channel'       => array(array('not_empty'), array('max_length', array(':value', 50))),
            'response_code' => array(array('max_length', array(':value', 50))),
        );
    }

    /**
     * Автоматическое заполнение полей при создании/обновлении
     */
    public function save(?Validation $validation = NULL)
    {
        if ($this->_loaded === FALSE)
        {
            // Установка создателя, если не задан вручную (например, из Cron)
            if (empty($this->created_by))
            {
                $user = Auth::instance()->get_user();
                $this->created_by = $user ? $user->id : 0; // 0 для системных процессов
            }
            
            $this->created_at = Date::formatted_time();
        }
        
        $this->updated_at = Date::formatted_time();

        return parent::save($validation);
    }

    /**
     * Метод для быстрой записи лога ответа API
     * 
     * @param Model_Erp217_Notification $notification    Объект очереди
     * @param string|int         $code     Код ответа (HTTP или внутренний)
     * @param mixed              $body     Тело ответа (массив или строка)
     * @return Model_Erp217_Log
     */
    public static function record(Model_Erp217_Notification $notification, $code, $body)
    {
        $log = ORM::factory('Erp217_Log');
        $log->queue_id      = $notification->id;
        $log->user_id       = $notification->recipient_id;
        $log->channel       = $notification->channel;
        $log->response_code = (string) $code;
        
        // Если пришел массив или объект (JSON ответ от API), преобразуем в строку
        $log->response_body = is_scalar($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE);
        
        return $log->save();
    }

    /**
     * Фильтр для получения логов по конкретному пользователю
     * @param int $user_id
     * @return $this
     */
    public function where_user($user_id)
    {
        return $this->where('user_id', '=', (int) $user_id);
    }

    /**
     * Фильтр для получения логов по каналу
     * @param string $channel (email, telegram, etc)
     * @return $this
     */
    public function where_channel($channel)
    {
        return $this->where('channel', '=', $channel);
    }
}