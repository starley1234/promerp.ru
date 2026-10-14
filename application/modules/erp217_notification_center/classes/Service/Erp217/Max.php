<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Драйвер интеграции с внешней системой Max.
 * Реализует отправку уведомлений через внешний API, указанный в конфигурации.
 * 
 * @package    ERP
 * @category   Service
 * @author     Development Team
 */
class Service_Erp217_Max {

    /**
     * @var Config_Group Настройки модуля
     */
    protected $_config;

    /**
     * Конструктор, загружающий конфигурацию драйвера
     */
    public function __construct()
    {
        $this->_config = Kohana::$config->load('erp217_notification_center.max');
    }

    /**
     * Основной метод отправки сообщения через систему Max
     * 
     * @param Model_Erp217_Notification $item Объект записи из очереди
     * @return bool Результат отправки
     */
    public function send(Model_Erp217_Notification $item)
    {
        if (empty($this->_config['api_key']) || empty($this->_config['endpoint'])) {
            $item->last_error = 'Max API configuration is missing (api_key or endpoint).';
            $item->save();
            return FALSE;
        }

        try {
            // Подготовка данных для внешнего API
            // Система Max ожидает идентификатор назначения (destination) и тело сообщения
            $post_data = array(
                'api_key'     => $this->_config['api_key'],
                'external_id' => $item->recipient_id,
                'target'      => $item->destination,
                'subject'     => $item->subject,
                'message'     => $item->payload, // Здесь уже отрендеренный текст или JSON
                'timestamp'   => time(),
            );

            // Выполнение запроса через cURL
            $response = $this->_execute_request($post_data);

            // Логирование ответа
            if (Kohana::$config->load('erp217_notification_center.notification.log_success') || $response['code'] != 200) {
                Model_Erp217_Log::record($item, $response['code'], $response['body']);
            }

            // Проверка успешности (ожидаем HTTP 200 и статус success в теле)
            $body = json_decode($response['body'], TRUE);
            
            if ($response['code'] == 200 && isset($body['status']) && $body['status'] === 'success') {
                return TRUE;
            } else {
                $item->last_error = 'Max API Error: ' . ($body['error'] ?? 'Unknown error') . ' (Code: ' . $response['code'] . ')';
                return FALSE;
            }

        } catch (Exception $e) {
            $item->last_error = 'Max Driver Exception: ' . $e->getMessage();
            return FALSE;
        }
    }

    /**
     * Выполнение HTTP POST запроса к API Max
     * 
     * @param array $data
     * @return array ['code' => int, 'body' => string]
     */
    protected function _execute_request(array $data)
    {
        $ch = curl_init($this->_config['endpoint']);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'X-Max-Auth: ' . $this->_config['api_key']
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            throw new Exception("CURL Error: " . $error_msg);
        }

        curl_close($ch);

        return array(
            'code' => $code,
            'body' => $body
        );
    }
}