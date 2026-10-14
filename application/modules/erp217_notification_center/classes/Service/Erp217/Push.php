<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Драйвер отправки внутренних Push-уведомлений.
 * Отвечает за обновление счетчиков непрочитанных сообщений в БД.
 * 
 * @package    ERP217
 * @category   Service
 * @author     Development Team
 */
class Service_Erp217_Push {

    /**
     * @var Config_Group Настройки модуля
     */
    protected $_config;

    /**
     * Конструктор, загружающий конфигурацию
     */
    public function __construct()
    {
        $this->_config = Kohana::$config->load('erp217_notification_center');
    }

    /**
     * Основной метод обработки внутреннего уведомления.
     * Обновляет непрочитанных сообщений в таблице erp_217_unread_counters.
     * 
     * @param Model_Erp217_Notification $notification Объект записи из очереди
     * @return bool Результат операции
     */
    public function send(Model_Erp217_Notification $notification)
    {
        try 
        {
            $user_id = (int) $notification->recipient_id;
            
            // Получаем актуальное количество непрочитанных сообщений типа push
            $actual_count = $this->count_actual_unread($user_id);

            // Обновляем или создаем счетчик непрочитанных сообщений в БД
            $this->update_unread_counter($user_id, $actual_count);

            // Регистрируем успех в логах
            if ($this->_config && $this->_config->get('notification') && $this->_config->get('notification')['log_success']) 
            {
                $log_data = [
                    'status' => 'success', 
                    'counter_updated' => TRUE,
                    'message' => 'Internal notification counter updated',
                    'new_count' => $actual_count
                ];
                
                $log = ORM::factory('Erp217_Log');
                $log->values([
                    'notification_id'      => $notification->id,
                    'user_id'       => $user_id,
                    'channel'       => 'push',
                    'response_code' => '200',
                    'response_body' => json_encode($log_data),
                    'created_by'    => 0
                ])->save();
            }

            return TRUE;
        } 
        catch (Exception $e) 
        {
            // Регистрируем ошибку
            if ($this->_config && $this->_config->get('notification') && $this->_config->get('notification')['log_errors']) 
            {
                $log = ORM::factory('Erp217_Log');
                $log->values([
                    'notification_id'      => $notification->id,
                    'user_id'       => $notification->recipient_id,
                    'channel'       => 'push',
                    'response_code' => '500',
                    'response_body' => $e->getMessage(),
                    'created_by'    => 0
                ])->save();
            }
            
            return FALSE;
        }
    }

    /**
     * Обновляет или создает запись счетчика непрочитанных сообщений для пользователя
     * 
     * @param int $user_id
     * @param int $count
     * @return void
     */
    public function update_unread_counter($user_id, $count)
    {
        DB::query(Database::INSERT, "
            INSERT INTO `erp_217_unread_counters` (`user_id`, `count`, `updated_at`)
            VALUES (:user_id, :count, :now)
            ON DUPLICATE KEY UPDATE `count` = :count, `updated_at` = :now
        ")
        ->parameters([
            ':user_id' => (int) $user_id,
            ':count'   => (int) $count,
            ':now'     => date('Y-m-d H:i:s'),
        ])
        ->execute();
    }

    /**
     * Подсчитывает количество всех текущих непрочитанных сообщений пользователя в очереди (только push)
     * 
     * @param int $user_id
     * @return int
     */
    public function count_actual_unread($user_id)
    {
        return (int) DB::select([DB::expr('COUNT(*)'), 'total'])
            ->from('erp_217_notification')
            ->where('recipient_id', '=', (int) $user_id)
            ->where('channel', '=', 'push')
            ->where('is_read', '=', 0)
            ->where('is_deleted', '=', 0)
            ->execute()
            ->get('total');
    }

    /**
     * Получить количество непрочитанных уведомлений для пользователя из таблицы счетчиков
     * 
     * @param int $user_id
     * @return int
     */
    public static function get_unread_count($user_id)
    {
        $result = DB::select('count')
            ->from('erp_217_unread_counters')
            ->where('user_id', '=', (int) $user_id)
            ->execute()
            ->current();

        return $result ? (int) $result['count'] : 0;
    }

    /**
     * Пометить сообщения как прочитанные и обновить счетчик
     * 
     * @param int $user_id
     * @param array|int|null $message_ids ID конкретных сообщений или null для всех
     * @return bool
     */
    public function mark_as_read($user_id, $message_ids = NULL)
    {
        $query = DB::update('erp_217_notification')
            ->set(['is_read' => 1])
            ->where('recipient_id', '=', (int) $user_id)
            ->where('channel', '=', 'push')
            ->where('is_read', '=', 0);

        if ($message_ids !== NULL)
        {
            if (is_array($message_ids))
            {
                $query->where('id', 'IN', $message_ids);
            }
            else
            {
                $query->where('id', '=', (int) $message_ids);
            }
        }

        $query->execute();

        // Пересчитываем и обновляем счетчик
        $actual_count = $this->count_actual_unread($user_id);
        $this->update_unread_counter($user_id, $actual_count);

        return TRUE;
    }

    /**
     * Сбросить счетчик непрочитанных уведомлений (пометить все как прочитанные)
     * 
     * @param int $user_id
     * @return bool
     */
    public static function reset_unread_count($user_id)
    {
        $service = new self();
        return $service->mark_as_read($user_id);
    }
}