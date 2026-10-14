<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Шина событий (Event Bus) для модуля Erp208.
 * 
 * @package Erp208
 * @version 1.0.7
 */
class Service_Erp208_EventBus {

    /**
     * @var Service_Erp208_EventBus Singleton instance
     */
    protected static $_instance;

    /**
     * @var array Зарегистрированные слушатели
     */
    protected $_listeners = array();

    /**
     * @var array Конфигурация
     */
    protected $_config;

    /** @var int Писать каждое N-е событие при массовой синтетической загрузке. */
    protected $_sample_every = 1;

    /** @var int */
    protected $_sample_counter = 0;

    /**
     * Приватный конструктор.
     */
    protected function __construct()
    {
        $config_object = Kohana::$config->load('erp208_events');
        $config_array = ($config_object instanceof Kohana_Config_Group) 
            ? $config_object->as_array() 
            : (array) $config_object;

        $this->_config = isset($config_array['event_bus']) ? $config_array['event_bus'] : array();
        $this->_config['logging'] = isset($config_array['logging']) ? $config_array['logging'] : array();
        
        $this->_load_subscribers_from_db();
    }

    /**
     * @return Service_Erp208_EventBus
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Регистрация слушателя
     */
    public function listen($event_name, $callback)
    {
        if ( ! isset($this->_listeners[$event_name]))
        {
            $this->_listeners[$event_name] = array();
        }
        $this->_listeners[$event_name][] = $callback;

        $this->_log(Log::DEBUG, 'Registered listener for: :event_name', array(':event_name' => $event_name));
    }

    /**
     * Проверка наличия слушателей
     */
    public function has_listeners($event_name)
    {
        return ! empty($this->_listeners[$event_name]);
    }

    /**
     * Удаление слушателей
     */
    public function forget($event_name)
    {
        if (isset($this->_listeners[$event_name]))
        {
            unset($this->_listeners[$event_name]);
        }
    }

    /**
     * Ограничить только персистентный журнал массовой загрузки. Слушатели и
     * уведомления продолжают получать каждое событие, поэтому бизнес-логика не
     * меняется. Значение 1 возвращает обычный полный режим.
     */
    public function sample_persistence($every = 1)
    {
        $this->_sample_every = max(1, (int) $every);
        $this->_sample_counter = 0;
    }

    /**
     * Записать гарантированное сообщение в outbox. Если вызывающий сервис уже
     * открыл транзакцию Database::instance(), INSERT участвует в ней вместе с
     * бизнес-фактом. Фактическую доставку выполняет отдельный worker.
     */
    public function enqueue($event_name, array $payload, $idempotency_key, array $options = array())
    {
        return Service_Erp208_Outbox::instance()->enqueue($event_name, $payload, $idempotency_key, $options);
    }

    /**
     * Публикация события
     * 
     * @param string $event_name
     * @param array $payload
     * @param int|null $initiator_id
     * @return bool
     */
    public function publish($event_name, array $payload = array(), $initiator_id = NULL, $strict = FALSE)
    {
        $this->_log(Log::INFO, 'Publishing event: :event_name by user :user', array(
            ':event_name' => $event_name,
            ':user' => $initiator_id
        ));
		
        // 1. В compact-режиме прореживается только журнал в БД. Доставка
        // уведомлений и вызов слушателей ниже выполняются для каждого события.
        $this->_sample_counter++;
        $persist_event = $this->_sample_every <= 1
            OR (($this->_sample_counter - 1) % $this->_sample_every) === 0;
        if ( ! empty($this->_config['log_published_events']) AND $persist_event)
        {
            try
            {
                $event_model = ORM::factory('Erp208_Event');
                $event_model->event_name = $event_name;
                $event_model->initiator_id = $initiator_id;
                
                // Кодируем в JSON для сохранения в текстовое поле БД
                $json_payload = json_encode($payload, JSON_UNESCAPED_UNICODE);
                
                $event_model->values(array(
                    'payload' => $json_payload
                ));
                
                $event_model->save(NULL);
            }
            catch (Exception $e)
            {
                $this->_log(Log::ERROR, 'DB Log Error: :error', array(':error' => $e->getMessage()));
            }
        }

        // 2. Wildcard проксирование в Центр Уведомлений по атрибутам payload
        if ( ! empty($payload['is_notification']) OR isset($payload['target_user_id']))
        {
            $this->_proxy_to_notification_center($payload, $event_name, $initiator_id);
        }

        // 3. Уведомление слушателей
        if (isset($this->_listeners[$event_name]))
        {
            foreach ($this->_listeners[$event_name] as $callback)
            {
                try
                {
                    // Передаем массив (payload), имя события и инициатора в callback
                    call_user_func($callback, $payload, $event_name, $initiator_id);
                }
                catch (Throwable $e)
                {
                    $this->_log(Log::ERROR, 'Listener Error in ":event_name": :error', array(
                        ':event_name' => $event_name,
                        ':error'      => $e->getMessage()
                    ));
                    // Outbox worker must see delivery failures and retry them;
                    // legacy synchronous publishers keep the non-throwing mode.
                    if ($strict) throw $e;
                }
            }
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Загрузка активных подписчиков из БД.
     *
     * `is_active` — колонка BOOLEAN, поэтому сравнение обязано быть с
     * boolean-литералом: `->where('is_active', '=', 1)` на PostgreSQL даёт
     * «operator does not exist: boolean = integer», исключение ловилось ниже и
     * уходило в лог — подписчики из базы (уведомления и callback-методы) просто
     * не подключались, и это было не видно ни в интерфейсе, ни в тестах.
     *
     * Флаги читаются через `Erp::bool()`: драйвер отдаёт boolean строкой
     * `t`/`f`, а `'f'` в PHP — истина (см. `AGENTS.md` §6 п. 38).
     */
    protected function _load_subscribers_from_db()
    {
        try
        {
            $subscribers = ORM::factory('Erp208_Subscriber')
                ->where('is_active', '=', TRUE)
                ->find_all();

            foreach ($subscribers as $subscriber)
            {
                // Если это уведомление, можно добавить специальную логику проксирования
                if (Erp::bool($subscriber->is_notification)) {
                    $this->listen($subscriber->event_name, array($this, '_proxy_to_notification_center'));
                }

                if ($subscriber->callback_method) {
                    $callback = $this->_create_callable_from_string($subscriber->callback_method);
                    if ($callback !== NULL)
                    {
                        $this->listen($subscriber->event_name, $callback);
                    }
                }
            }
        }
        catch (Exception $e)
        {
            // Подписчиков нет — события уходят «в пустоту»: это не тихая мелочь,
            // а пропавшие уведомления и не сработавшая бизнес-логика.
            $this->_log(Log::ERROR, 'Failed to load DB subscribers: :error (события останутся без подписчиков из базы)', array(
                ':error' => $e->getMessage(),
            ));
        }
    }
    
    
    /**
     * Прокси-метод для автоматической отправки в Notification Center
     * Теперь поддерживает массивы и исключает инициатора.
     */
    protected function _proxy_to_notification_center($payload, $event_name, $initiator_id)
    {
        // 1. Собираем всех потенциальных получателей в один массив
        $targets = array();

        if (isset($payload['target_user_ids']) && is_array($payload['target_user_ids'])) {
            $targets = $payload['target_user_ids'];
        } elseif (isset($payload['target_user_id'])) {
            $targets[] = $payload['target_user_id'];
        }

        // 2. Если получателей нет - выходим
        if (empty($targets)) {
            return;
        }

        // 3. ФИЛЬТРАЦИЯ: Убираем дубли и исключаем того, кто совершил действие (initiator_id)
        $targets = array_unique($targets);

        foreach ($targets as $user_id) {
            // ГЛАВНОЕ ПРАВИЛО: Не уведомляем инициатора о его собственных действиях
            if ($initiator_id !== NULL && (int)$user_id === (int)$initiator_id) {
                // Если в событии нет флага "принудительно", пропускаем
                if (empty($payload['force_notification'])) {
                    continue; 
                }
            }

            // 4. Отправка в Notify (ERP217)
            // Мы вызываем это в цикле, чтобы Notify просто делал свою работу для конкретного ID
            Service_Erp217_Bus::instance()->send($event_name, $user_id, $payload);
        }
    }

    /**
     * Преобразование строки callback в callable
     */
    protected function _create_callable_from_string($callback_string)
    {
        // Статический метод: Class::method
        if (strpos($callback_string, '::') !== FALSE)
        {
            list($class, $method) = explode('::', $callback_string, 2);
            return (class_exists($class) && method_exists($class, $method)) ? array($class, $method) : NULL;
        }
        
        // Метод объекта: Class->method
        if (strpos($callback_string, '->') !== FALSE)
        {
            list($class, $method) = explode('->', $callback_string, 2);
            if (class_exists($class))
            {
                // Если это контроллер Kohana, он требует Request/Response в конструкторе.
                // Для событийной модели лучше использовать статические методы или Service-классы.
                // Пытаемся создать объект без параметров, если это возможно.
                try {
                    $reflection = new ReflectionClass($class);
                    $constructor = $reflection->getConstructor();
                    
                    // Если конструктора нет или он не требует обязательных параметров
                    if ($constructor === NULL || $constructor->getNumberOfRequiredParameters() === 0) {
                        $obj = new $class();
                        return method_exists($obj, $method) ? array($obj, $method) : NULL;
                    } else {
                        $this->_log(Log::ERROR, 'Cannot instantiate class :class for callback :method: constructor requires parameters', array(
                            ':class' => $class,
                            ':method' => $method
                        ));
                        return NULL;
                    }
                } catch (Exception $e) {
                    return NULL;
                }
            }
        }

        return function_exists($callback_string) ? $callback_string : NULL;
    }

    /**
     * Системное логирование
     */
    protected function _log($level, $message, ?array $values = NULL)
    {
        $log_cfg = isset($this->_config['logging']) ? $this->_config['logging'] : array();
        
        if ( ! empty($log_cfg['enabled']) && $level <= $log_cfg['level'])
        {
            $log_file = APPPATH . 'logs/' . $log_cfg['file'];
            Log::instance()->add($level, $message, $values)->write($log_file);
        }
    }
}