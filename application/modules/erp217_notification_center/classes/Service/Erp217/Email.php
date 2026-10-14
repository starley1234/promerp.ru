<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Сервис для технической реализации отправки электронных писем через SMTP.
 * Инкапсулирует логику валидации, взаимодействия с PHPMailer (или SwiftMailer) и логирования.
 * 
 * @package Erp217
 * @version 1.0.0
 */
class Service_Erp217_Email {

    /**
     * @var Service_Erp217_Email
     */
    protected static $_instance;

    /**
     * @var Config_Group
     */
    protected $_config;

    /**
     * Singleton instance
     * 
     * @return Service_Erp217_Email
     */
    public static function instance()
    {
        if (self::$_instance === NULL) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Protected constructor
     */
    protected function __construct()
    {
        // $this->_config = Kohana::$config->load('erp217_notification_center.email');
    }

    /**
     * Отправка письма на основе записи из очереди
     * 
     * @param Model_Erp217_Notification $item Объект записи из очереди
     * @return bool
     * @throws Exception
     */
    public function send(Model_Erp217_Notification $item)
    {
        if ($item->channel !== 'email') {
            throw new Kohana_Exception('Invalid channel for Email service: :channel', [':channel' => $item->channel]);
        }

        $db = Database::instance();
        $db->begin();

        try {
            // 1. Валидация данных перед отправкой
            $validation = Validation::factory(['email' => $item->destination])
                ->rule('email', 'not_empty')
                ->rule('email', 'email');

            if (!$validation->check()) {
                throw new Kohana_Exception('Invalid recipient email: :email', [':email' => $item->destination]);
            }

            // 2. Попытка физической отправки
            $result = $this->_execute_smtp_send(
                $item->destination,
                $item->subject,
                $item->payload // В payload уже отрендеренный Bus-сервисом HTML/Text
            );
            

            // 3. Обновление статуса в очереди
            $item->status = 'sent';
            $item->sent_at = Date::formatted_time();
            $item->attempts += 1;
            $item->save();

            // 4. Логирование результата
            Model_Erp217_Log::record($item, 200, 'OK: Message sent successfully');

            // 5. Генерация системного события
            Event::fire('erp217.email.sent', ['notification_id' => $item->id, 'recipient' => $item->destination]);

            $db->commit();
            return TRUE;
        } catch (Exception $e) {
            $db->rollback();

            // Фиксация ошибки в очереди
            $item->status = ($item->attempts + 1 >= Kohana::$config->load('erp217_notification_center.notification.max_attempts')) 
                ? 'failed' 
                : 'pending';
            
            $item->attempts += 1;
            $item->last_error = $e->getMessage();
            $item->save();

            // Логирование ошибки
            Model_Erp217_Log::record($item, $e->getCode() ?: 500, $e->getMessage());
            
            Event::fire('erp217.email.failed', ['notification_id' => $item->id, 'error' => $e->getMessage()]);
            
            throw $e;
        }
    }

    /**
     * Непосредственное взаимодействие с почтовым транспортом
     * В Kohana 3.3 обычно используется интеграция с SwiftMailer или PHPMailer
     * 
     * @param string $to
     * @param string $subject
     * @param mixed $payload
     * @return bool
     * @throws Exception
     */
    protected function _execute_smtp_send($to, $subject, $payload)
    {
        // Если payload пришел в виде JSON-строки, декодируем его
        if (is_string($payload)) {
            $decoded = json_decode($payload, TRUE);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload = $decoded;
            }
        }

        // Извлекаем тело письма. Если payload - массив, берем 'body', иначе используем сам payload как строку
        $body = is_array($payload) ? (isset($payload['body']) ? $payload['body'] : '') : (string) $payload;
        
        // В реальной системе здесь подключается библиотека (например, Email модуль Kohana)
        Email::send($to, 'info@email.ru', $subject, $body, TRUE);
        
        sleep(3);
        
        return TRUE;
    }

    /**
     * Прямая отправка без очереди (для экстренных уведомлений или тестов)
     * 
     * @param string $to
     * @param string $subject
     * @param string $body
     * @return bool
     */
    public function send_direct($to, $subject, $body)
    {
        Audit::log('email_direct_send_attempt', ['to' => $to]);
        return $this->_execute_smtp_send($to, $subject, $body);
    }

    /**
     * Магический метод для клонирования объекта.
     * Должен быть public для корректной работы сериализации/десериализации.
     */
    public function __clone() {}

    /**
     * Магический метод, вызываемый после десериализации объекта.
     * Должен быть public для корректной работы сериализации/десериализации.
     */
    public function __wakeup() {}
}