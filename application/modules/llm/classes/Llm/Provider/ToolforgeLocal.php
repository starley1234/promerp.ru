<?php defined('SYSPATH') or die('No direct script access.');

class Llm_Provider_ToolforgeLocal extends Llm_Provider_Abstract {

    public function chat(array $messages, array $options = []): array {
        $processed_messages = [];

        foreach ($messages as $message) {
            $processed_content = [];
            if (isset($message['content']) && is_array($message['content'])) { // Если content - массив (для мультимодальных сообщений)
                foreach ($message['content'] as $content_part) {
                    if (isset($content_part['type']) && $content_part['type'] === 'image_url') {
                        if (!isset($content_part['image_url']['url'])) {
                            throw new Kohana_Exception('Image URL is missing in message content.');
                        }

                        $image_url = $content_part['image_url']['url'];

                        try {
                            // Определяем MIME-тип и получаем base64
                            list($base64_image, $mime_type) = $this->convertImageUrlToBase64($image_url);
                            $processed_content[] = [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => 'data:' . $mime_type . ';base64,' . $base64_image,
                                ],
                            ];
                        } catch (Exception $e) {
                            Kohana::$log->add(Log::ERROR, 'Failed to convert image URL to base64 for local LLM: :error', [':error' => $e->getMessage()]);
                            throw new Kohana_Exception('Failed to process image for local LLM: ' . $e->getMessage());
                        }

                    } else {
                        // Обычный текст или другие типы контента
                        $processed_content[] = $content_part;
                    }
                }
                $processed_messages[] = [
                    'role' => $message['role'],
                    'content' => $processed_content,
                ];
            } else {
                // Обычное текстовое сообщение или если content отсутствует/не массив
                $processed_messages[] = $message;
            }
        }

        $data = [
            'messages' => $processed_messages,
        ];
        
        // Передаем $options, чтобы _sendRequest мог использовать их (например, 'model')
        return $this->_sendRequest('chat/completions', $data, $options);
    }

    /**
     * Загружает изображение по URL, определяет его MIME-тип и кодирует в base64.
     *
     * @param string $image_url URL изображения.
     * @return array [base64_string, mime_type]
     * @throws Kohana_Exception Если не удалось загрузить или закодировать изображение.
     */
    protected function convertImageUrlToBase64(string $image_url): array {
        // Проверяем, является ли URL уже base64 data URI
        if (strpos($image_url, 'data:') === 0) {
            $parts = explode(',', $image_url, 2);
            if (count($parts) === 2) {
                // Извлекаем MIME-тип из data URI
                preg_match('/data:(?P<mime>[a-zA-Z0-9\/+\.-]+);base64/', $parts[0], $matches);
                $mime_type = $matches['mime'] ?? 'application/octet-stream'; // Дефолтный MIME-тип
                return [$parts[1], $mime_type];
            }
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $image_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Следовать редиректам
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->_config['image_download_timeout'] ?? 10); // Таймаут для загрузки изображения

        $image_data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $mime_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE); // Получаем MIME-тип из заголовков

        curl_close($ch);

        if ($image_data === false) {
            Kohana::$log->add(Log::ERROR, 'Failed to download image from URL via cURL: :url. Error: :error', [':url' => $image_url, ':error' => $curl_error]);
            throw new Kohana_Exception('Failed to download image from URL: ' . $image_url . '. Error: ' . $curl_error);
        }

        if ($http_code >= 400) {
            Kohana::$log->add(Log::ERROR, 'Image download URL returned HTTP error :code: :url', [':code' => $http_code, ':url' => $image_url]);
            throw new Kohana_Exception('Image download URL returned HTTP error ' . $http_code . ': ' . $image_url);
        }

        if (empty($image_data)) {
            throw new Kohana_Exception('Could not retrieve image data from URL: :url (empty response)', [':url' => $image_url]);
        }

        // Если MIME-тип не удалось получить из заголовков, попробуем определить его по содержимому
        if (empty($mime_type) || strpos($mime_type, 'text/') === 0) { // Если получили что-то вроде text/html
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $detected_mime_type = $finfo->buffer($image_data);
            if ($detected_mime_type !== false) {
                $mime_type = $detected_mime_type;
            } else {
                // Если совсем не удалось определить, используем дефолтный
                $mime_type = 'application/octet-stream';
            }
        }

        return [base64_encode($image_data), $mime_type];
    }
}