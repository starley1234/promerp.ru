<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Сервис для взаимодействия с Telegram Bot API.
 * Инкапсулирует логику отправки сообщений, валидацию данных и логирование ответов.
 * 
 * @package Erp217
 * @version 1.0.0
 */
class Service_Erp217_Telegram {

    /**
     * @var Service_Erp217_Telegram
     */
    protected static $_instance;

    /**
     * @var Config_Group
     */
    protected $_config;

    /**
     * Приватный конструктор (Singleton)
     */
    private function __construct()
    {
        $this->_config = Kohana::$config->load('erp217_notification_center.telegram');
    }

    /**
     * Получение экземпляра сервиса
     * 
     * @return Service_Erp217_Telegram
     */
    public static function instance()
    {
        if (self::$_instance === NULL) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Отправка сообщения в Telegram через Bot API
     * 
     * @param Model_Erp217_Notification $item Объект из очереди
     * @return bool
     * @throws Exception
     */
    public function send(Model_Erp217_Notification $item)
    {
        if ($item->channel !== 'telegram') {
            throw new Kohana_Exception('Invalid channel for Telegram service: :channel', [
                ':channel' => $item->channel
            ]);
        }

        $db = Database::instance();
        $db->begin();

        try {
            // 1. Валидация данных перед отправкой
            $validation = Validation::factory(['chat_id' => $item->destination, 'text' => $item->payload])
                ->rule('chat_id', 'not_empty')
                ->rule('text', 'not_empty');

            if (!$validation->check()) {
                throw new Kohana_Exception('Invalid Telegram payload: :errors', [
                    ':errors' => json_encode($validation->errors('validation'))
                ]);
            }

            // 2. Подготовка запроса
            $url = $this->_config['api_url'] . $this->_config['bot_token'] . '/sendMessage';
            $post_data = [
                'chat_id'    => $item->destination,
                'text'       => $item->payload,
                'parse_mode' => $this->_config['parse_mode']
            ];

            // 3. Выполнение HTTP запроса
            $response = $this->_execute_curl($url, $post_data);
            $result = json_decode($response, TRUE);

            // 4. Обработка результата
            if (isset($result['ok']) && $result['ok'] === TRUE) {
                $item->status = 'sent';
                $item->sent_at = Date::formatted_time();
                $item->save();

                // Логирование успеха
                Model_Erp217_Log::record($item, 200, $result);
                
                Event::fire('erp217.telegram.sent', ['item' => $item, 'response' => $result]);
            } else {
                $error_code = isset($result['error_code']) ? $result['error_code'] : 'unknown';
                throw new Kohana_Exception('Telegram API Error: :msg', [':msg' => $response], (int) $error_code);
            }

            $db->commit();
            return TRUE;

        } catch (Exception $e) {
            $db->rollback();

            $item->status = 'failed';
            $item->attempts += 1;
            $item->last_error = $e->getMessage();
            $item->save();

            Model_Erp217_Log::record($item, $e->getCode(), $e->getMessage());
            
            Event::fire('erp217.telegram.failed', ['item' => $item, 'error' => $e->getMessage()]);
            
            throw $e;
        }
    }

    /**
     * Внутренний метод для выполнения CURL запросов
     * 
     * @param string $url
     * @param array $data
     * @return string
     * @throws Kohana_Exception
     */
    protected function _execute_curl($url, array $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        if ($this->_config['proxy']['enabled']) {
            curl_setopt($ch, CURLOPT_PROXY, $this->_config['proxy']['host']);
            curl_setopt($ch, CURLOPT_PROXYPORT, $this->_config['proxy']['port']);
            if (!empty($this->_config['proxy']['user'])) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->_config['proxy']['user'] . ':' . $this->_config['proxy']['pass']);
            }
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            throw new Kohana_Exception('CURL Error: :error', [':error' => $error]);
        }

        return $response;
    }

    /**
     * Метод для регистрации Webhook (утилитарный)
     * 
     * @param string $webhook_url
     * @return mixed
     */
    public function set_webhook($webhook_url)
    {
        $url = $this->_config['api_url'] . $this->_config['bot_token'] . '/setWebhook';
        $response = $this->_execute_curl($url, ['url' => $webhook_url]);
        
        Audit::log('telegram_webhook_setup', ['url' => $webhook_url, 'response' => $response]);
        
        return json_decode($response, TRUE);
    }
}