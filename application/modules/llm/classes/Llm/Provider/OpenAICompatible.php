<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Провайдер LLM для OpenAI-совместимых API (OpenAI, OpenRouter).
 * Поддерживает работу с инструментами (tools).
 */
class Llm_Provider_OpenAICompatible extends Llm_Provider_Abstract {

    protected function _initModelCapabilities() {
        parent::_initModelCapabilities();

        // Информация о моделях GPT-5 и их особенностях
        $this->_model_capabilities = array_merge($this->_model_capabilities, [
            'gpt-5' => [
                'unsupported_params' => ['temperature', 'top_p', 'logprobs'],
                'default_params' => ['reasoning_effort' => 'medium'],
                'is_reasoning_model' => true,
                'supports_tools' => true, // Добавляем флаг поддержки инструментов
            ],
            'gpt-5-mini' => [
                'unsupported_params' => ['temperature', 'top_p', 'logprobs'],
                'default_params' => ['reasoning_effort' => 'medium'],
                'is_reasoning_model' => true,
                'supports_tools' => true,
            ],
            'gpt-5-nano' => [
                'unsupported_params' => ['temperature', 'top_p', 'logprobs'],
                'default_params' => ['reasoning_effort' => 'medium'],
                'is_reasoning_model' => true,
                'supports_tools' => true,
            ],
            'o3' => [
                'unsupported_params' => ['temperature', 'top_p', 'logprobs'],
                'default_params' => ['reasoning_effort' => 'medium'],
                'is_reasoning_model' => true,
                'supports_tools' => true,
            ],
            'o3-mini' => [
                'unsupported_params' => ['temperature', 'top_p', 'logprobs'],
                'default_params' => ['reasoning_effort' => 'medium'],
                'is_reasoning_model' => true,
                'supports_tools' => true,
            ],
            // Добавляем поддержку инструментов для актуальных моделей OpenAI
            'gpt-4o-2024-08-06' => [
                'unsupported_params' => [],
                'default_params' => [],
                'is_reasoning_model' => false,
                'supports_tools' => true, // gpt-4o и gpt-4o-mini поддерживают инструменты
            ],
            'gpt-4o-mini-2024-07-18' => [
                'unsupported_params' => [],
                'default_params' => [],
                'is_reasoning_model' => false,
                'supports_tools' => true,
            ],
            // Пример для OpenRouter моделей, которые могут поддерживать инструменты
            // Важно: для OpenRouter нужно будет добавить все модели, которые вы планируете использовать
            // и указать для них 'supports_tools' => true, если они их поддерживают.
            // Например, для Google Gemini через OpenRouter:
            'google/gemini-2.0-flash-001' => [
                'unsupported_params' => [],
                'default_params' => [],
                'is_reasoning_model' => false, // Или true, если это так
                'supports_tools' => true,
            ],
            'mistralai/mistral-7b-instruct' => [
                'unsupported_params' => [],
                'default_params' => [],
                'is_reasoning_model' => false,
                'supports_tools' => false, // Пример модели без поддержки инструментов
            ],
            // ... другие модели
        ]);
    }

    public function chat(array $messages, array $options = []): array {
        $data = [
            'messages' => $messages,
        ];

        // Добавляем 'tools' в тело запроса, если они присутствуют в $options
        // и модель поддерживает инструменты.
        if (isset($options['tools']) && !empty($options['tools'])) {
            $model_info = $this->getModelInfo($options['model'] ?? $this->getDefaultModel());
            if (($model_info['supports_tools'] ?? false) === true) {
                $data['tools'] = $options['tools'];
                // OpenRouter требует, чтобы 'tool_choice' был явно указан, если есть 'tools'
                // OpenAI по умолчанию делает 'auto', но явное указание не повредит.
                // Если 'tool_choice' не передан, устанавливаем 'auto'.
                if (!isset($options['tool_choice'])) {
                    $data['tool_choice'] = 'auto';
                }
            } else {
                Kohana::$log->add(Log::WARNING, 'LLM parameter "tools" was provided, but model ":model" does not support tools and they will be ignored.', [
                    ':model' => $options['model'] ?? $this->getDefaultModel()
                ]);
            }
        }
        
        // Добавляем 'tool_choice' если он есть в $options
        if (isset($options['tool_choice'])) {
            $data['tool_choice'] = $options['tool_choice'];
        }

        return $this->_sendRequest('chat/completions', $data, $options);
    }
}