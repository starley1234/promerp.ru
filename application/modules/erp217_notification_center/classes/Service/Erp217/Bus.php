<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Service_Erp217_Bus - Центральный сервис (Шина) системы уведомлений.
 * 
 * Отвечает за:
 * 1. Валидацию подписок пользователей (в т.ч. на конкретные сущности).
 * 2. Рендеринг шаблонов с подстановкой данных.
 * 3. Постановку сообщений в очередь (Notification).
 * 4. Распределение задач по каналам (Email, Telegram, Push, Max).
 * 
 * @package    ERP217
 * @category   Service
 * @author     Центр уведомлений
 */
class Service_Erp217_Bus {

    /**
     * @var Service_Erp217_Bus Экземпляр синглтона
     */
    protected static $_instance;

    /**
     * @var Config_Group Настройки модуля
     */
    protected $_config;

    /**
     * Получение экземпляра сервиса
     * @return Service_Erp217_Bus
     */
    public static function instance()
    {
        if (self::$_instance === NULL) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    protected function __construct()
    {
        $this->_config = Kohana::$config->load('erp217_notification_center');
    }

    /**
     * Главная точка входа для отправки уведомления.
     * Проверяет подписки и ставит задачи в очередь для всех разрешенных каналов.
     * 
     * @param string $event_alias Код события (напр. 'order_created')
     * @param int    $user_id     ID получателя
     * @param array  $data        Данные для замены в шаблоне ['user_name' => 'Иван', ...]
     * @param string $entity_type Тип сущности (опционально, для проверки EntitySubscription)
     * @param int    $entity_id   ID сущности (опционально, для проверки EntitySubscription)
     * @return array              Массив ID созданных записей в очереди
     */ 
    public function send($event_alias, $user_id, array $data = [], $entity_type = NULL, $entity_id = NULL)
    {
        $user = ORM::factory('User', $user_id);
        if ( ! $user->loaded()) {
            return [];
        }

        // Если не передан site_url, берем из глобальных настроек
        if ( ! isset($data['site_url'])) {
            $data['site_url'] = Kohana::$config->load('site_config.site_url');
        }

        // 1. Проверка подписки на конкретную сущность (если переданы параметры)
        if ($entity_type !== NULL && $entity_id !== NULL) {
            $entity_sub = ORM::factory('Erp217_EntitySubscription')
                ->where('user_id', '=', $user_id)
                ->where('entity_type', '=', $entity_type)
                ->where('entity_id', '=', $entity_id)
                ->where('is_deleted', '=', 0)
                ->find();

            if ( ! $entity_sub->loaded()) {
                return [];
            }
        }

        // 2. Получаем активные каналы подписки пользователя на это событие
        $subscriptions = ORM::factory('Erp217_Subscription')
            ->where('user_id', '=', $user_id)
            ->where('event_type', '=', $event_alias)
            ->where('is_enabled', '=', 1)
            ->where('is_deleted', '=', 0)
            ->find_all();
        

        $notification_ids = [];

        // Начинаем транзакцию для надежности постановки в очередь
        $db = Database::instance();
        $db->begin();
        
        try {
            foreach ($subscriptions as $sub) {
                $channel = $sub->channel;

                // 3. Ищем шаблон для данного канала
                $template = Model_Erp217_Template::find_by_alias($event_alias, $channel);
                
                // 4. Определяем адрес назначения (Destination)
                $destination = $this->_get_destination($user, $channel);
                if (empty($destination)) {
                    continue;
                }

                if ($template->loaded()) {
                    // 5. Рендерим контент на основе шаблона
                    $rendered_subject = $this->render_template($template->subject, $data);
                    $rendered_body    = $this->render_template($template->body, $data);
                    $template_id      = $template->id;
                } else {
                    // Если шаблона нет, отправляем Название события и Содержание данных в теле
                    $rendered_subject = $event_alias;
                    $rendered_body    = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    $template_id      = 0;
                }

                // 6. Создаем запись в очереди
                $notification = ORM::factory('Erp217_Notification');
                $notification->values([
                    'recipient_id' => $user_id,
                    'template_id'  => $template_id,
                    'channel'      => $channel,
                    'destination'  => $destination,
                    'subject'      => $rendered_subject,
                    'payload'      => [
                        'body' => $rendered_body,
                        'raw_data' => $data,
                        'entity_info' => [
                            'type' => $entity_type,
                            'id'   => $entity_id
                        ]
                    ],
                    'status'       => Model_Erp217_Notification::STATUS_PENDING,
                    'created_by'   => Auth::instance()->get_user() ? Auth::instance()->get_user()->id : 0,
                ]);
                $notification->save();
                
                $notification_ids[] = $notification->id;
            }

            $db->commit();
        
        } catch (Exception $e) {
            $db->rollback();
            Kohana_Log::instance()->add(Log::ERROR, 'Bus Error: ' . $e->getMessage());
        }

        return $notification_ids;
    }

    /**
     * Обработка очереди (вызывается через CRON)
     * 
     * @param int $limit Лимит записей за один проход
     */
    public function process_notification($limit = NULL)
    {
        if ($limit === NULL) {
            $limit = $this->_config->get('notification')['batch_limit'];
        }
        
        $items = ORM::factory('Erp217_Notification')->get_items_for_processing($limit);
        
        foreach ($items as $item) {
            $this->process_item($item);
        }
    }

    /**
     * Обработка конкретного элемента очереди
     * 
     * @param Model_Erp217_Notification $item
     * @return bool
     */
    public function process_item(Model_Erp217_Notification $item)
    {
        if ( ! $item->loaded()) {
            return FALSE;
        }

        $item->status = Model_Erp217_Notification::STATUS_PROCESSING;
        $item->save();
        
        try {
            // Выбор драйвера канала
            $driver = $this->_get_driver($item->channel);
            $result = $driver->send($item);

            if ($result) {
                $item->set_sent();
                $this->_log_transaction($item, '200', 'Success');
                return TRUE;
            } else {
                throw new Exception("Driver returned false");
            }
        } catch (Exception $e) {
            $item->log_error($e->getMessage());
            $this->_log_transaction($item, '500', $e->getMessage());
            return FALSE;
        }
    }

    /**
     * Рендеринг текста шаблона (замена {{key}} на value)
     * Поддерживает вложенные массивы через json_encode
     * 
     * @param string $text
     * @param array $data
     * @return string
     */
    public function render_template($text, array $data)
    {
        $placeholders = [];
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $placeholders['{{' . $key . '}}'] = $value;
            } elseif (is_array($value)) {
                $placeholders['{{' . $key . '}}'] = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }
        return strtr($text, $placeholders);
    }

    /**
     * Получение драйвера для канала
     * 
     * @param string $channel
     * @return object
     * @throws Kohana_Exception
     */
    protected function _get_driver($channel)
    {
        $class = 'Service_Erp217_' . ucfirst($channel);
        if (class_exists($class)) {
            $reflection = new ReflectionClass($class);
            return $reflection->newInstanceWithoutConstructor();
        }
        throw new Kohana_Exception('Driver for channel :channel not found', [':channel' => $channel]);
    }

    /**
     * Определение контактных данных пользователя в зависимости от канала
     */
    protected function _get_destination(Model_User $user, $channel)
    {
        switch ($channel) {
            case 'email':
                return $user->email;
            case 'telegram':
                return isset($user->profile->telegram_chat_id) ? $user->profile->telegram_chat_id : NULL;
            case 'push':
                return (string)$user->id;
            case 'max':
                return $user->username;
            default:
                return NULL;
        }
    }

    /**
     * Запись в лог транзакций
     */
    protected function _log_transaction(Model_Erp217_Notification $notification, $code, $body)
    {
        $log = ORM::factory('Erp217_Log');
        $log->queue_id        = $notification->id;
        $log->user_id         = $notification->recipient_id;
        $log->channel         = $notification->channel;
        $log->response_code   = (string) $code;
        $log->response_body   = is_scalar($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE);
        $log->created_by      = 0; // System
        $log->save();
    }
}