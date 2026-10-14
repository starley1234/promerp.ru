<?php defined('SYSPATH') or die('No direct script access.');

class CurlRequest {
	
  
    /**
     * @param string $url
     * @param array $data
     * @param array $headers
     * @param int $timeout
     * @param string|null $proxy Адрес прокси, например '11.197.204.169:8081'
     * @return array
     */
    public static function post(string $url, array $data, array $headers = [], int $timeout = 30, string $proxy = null): array
    {
        $ch = curl_init();
      

        // Автоматически добавляем JSON заголовок, если его нет
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json';
        }
      	
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, self::formatHeaders($headers));
		// curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaders);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

        // --- Настройки прокси ---
        if ($proxy) {
			
			// Прокси настройки
			curl_setopt($ch, CURLOPT_PROXY, $proxy);
			curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
			curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);

			// Важно для работы через прокси с HTTPS
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
          
        }
        // ------------------------
      
      	

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error_msg = curl_error($ch);
        $error_no = curl_errno($ch);

        curl_close($ch);

        $log_message_prefix = 'LLM API Request Error: ';
        $log_message_suffix = ' URL: ' . $url;

        if ($response === false) {
            throw new Kohana_Exception($log_message_prefix . 'cURL Error (:error_no): :error_msg' . $log_message_suffix, [
                ':error_no' => $error_no,
                ':error_msg' => $error_msg,
            ]);
        }

        if ($http_code >= 400) {
          
          	$error_details = json_decode($response, true);
            // Выводим то, что прислал сервер, а не свою строку
            $error_message = $error_details['error']['message'] ?? "Raw response: " . $response;
            $error_type = $error_details['error']['type'] ?? 'http_error';
          
          	// Для 401/403 ошибок, сообщение может быть "Invalid API key" или "Unauthorized".
            // Мы не хотим логировать сам ключ, поэтому просто указываем тип ошибки.
            if ($http_code === 401 || $http_code === 403) {
                $error_message = 'Authentication/Authorization failed. Check API key and permissions.';
            }

            throw new Kohana_Exception($log_message_prefix . 'HTTP Error :http_code (:error_type): :message', [
                ':http_code' => $http_code,
                ':error_type' => $error_type,
                ':message' => $error_message,
            ]);
          
        }

        $decoded_response = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Kohana_Exception($log_message_prefix . 'Invalid JSON response' . $log_message_suffix);
        }
        
        // var_dump ($decoded_response);

        return $decoded_response;
    }

    protected static function formatHeaders(array $headers): array
    {
        $formatted_headers = [];
        foreach ($headers as $key => $value) {
            $formatted_headers[] = $key . ': ' . $value;
        }
        return $formatted_headers;
    }
}