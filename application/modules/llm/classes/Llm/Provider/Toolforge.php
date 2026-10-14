<?php defined('SYSPATH') or die('No direct script access.');

class Llm_Provider_Toolforge extends Llm_Provider_ToolforgeLocal {

    public function chat(array $messages, array $options = []): array {
        // Если переданы mcp_tools, используем эндпоинт /responses
        if (isset($options['mcp_tools'])) {
            $data = [
                'instructions' => $this->_extractSystemPrompt($messages),
                'input'        => $this->_extractLastUserMessage($messages),
                'tools'        => $options['mcp_tools']
            ];
            
            // Передаем $options для корректной подстановки модели и таймаутов в _sendRequest
            return $this->responses($data, $options);
        }

        // В противном случае используем стандартный чат (с обработкой изображений из ToolforgeLocal)
        return parent::chat($messages, $options);
    }

    /**
     * Метод для получения эмбеддингов
     */
    public function embeddings($input, array $options = []): array {
        $data = [
            'input' => $input,
            'model' => $options['model'] ?? 'text-embedding-qwen3-embedding-0.6b'
        ];

        return $this->_sendRequest('embeddings', $data, $options);
    }

    /**
     * Переопределяем метод responses для трансформации ответа Toolforge в формат OpenAI
     */
    public function responses(array $data, array $options = []): array {
        $response = parent::responses($data, $options);

        // Если это успешный ответ от /v1/responses, трансформируем его в формат OpenAI
        if (isset($response['status']) && $response['status'] === 'completed' && isset($response['output'])) {
            $text = '';
            foreach ($response['output'] as $item) {
                if ($item['type'] === 'message' && isset($item['content'][0]['text'])) {
                    $text = $item['content'][0]['text'];
                    break;
                }
            }

            return [
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => $text
                        ],
                        'finish_reason' => 'stop'
                    ]
                ],
                'usage' => $response['usage'] ?? []
            ];
        }

        return $response;
    }

    /**
     * Извлекает системный промпт из массива сообщений.
     * 
     * @param array $messages
     * @return string
     */
    protected function _extractSystemPrompt(array $messages): string {
        foreach ($messages as $m) {
            if (isset($m['role']) && $m['role'] === 'system') {
                return is_array($m['content']) ? json_encode($m['content'], JSON_UNESCAPED_UNICODE) : (string)$m['content'];
            }
        }
        return '';
    }

    /**
     * Извлекает последнее сообщение пользователя.
     * 
     * @param array $messages
     * @return string
     */
    protected function _extractLastUserMessage(array $messages): string {
        $last = '';
        foreach ($messages as $m) {
            if (isset($m['role']) && $m['role'] === 'user') {
                $last = $m['content'];
            }
        }
        return is_array($last) ? json_encode($last, JSON_UNESCAPED_UNICODE) : (string)$last;
    }
}