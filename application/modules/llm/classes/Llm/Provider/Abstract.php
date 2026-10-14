<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Абстрактный базовый класс для провайдеров LLM.
 * Предоставляет общую логику и свойства.
 */
abstract class Llm_Provider_Abstract implements Interface_LlmProvider {
    protected $_config;
    protected $_name; // Имя провайдера (например, 'openai', 'openrouter')

    // Массив для хранения информации о моделях, специфичных для провайдера
    // Может быть переопределен в дочерних классах
    protected $_model_capabilities = [];

    public function __construct(string $name, array $config) {
        $this->_name = $name;
        $this->_config = $config;
        $this->_initModelCapabilities();
    }

    public function getName(): string {
        return $this->_name;
    }

    public function getDefaultModel(): string {
        return $this->_config['default_model'] ?? '';
    }

    public function getVisionModel(): string {
        return $this->_config['vision_model'] ?? $this->getDefaultModel();
    }

    protected function _initModelCapabilities() {
        // Базовая реализация, может быть пустой или содержать общие данные
    }

    public function getModelInfo(string $model_name): array {
        // Возвращаем информацию, специфичную для модели, или пустой массив, если нет данных
        return $this->_model_capabilities[$model_name] ?? [
            'unsupported_params' => [],
            'default_params' => [],
            'is_reasoning_model' => false,
            'supports_tools' => true, // По умолчанию считаем, что модель поддерживает инструменты, если не указано обратное
        ];
    }

    public function supportsTools(?string $model_name = null): bool {
        $model_to_check = $model_name ?? $this->getDefaultModel();
        $model_info = $this->getModelInfo($model_to_check);
        return $model_info['supports_tools'] ?? true;
    }

    /**
     * Поддержка эндпоинта /v1/responses для MCP-запросов
     */
    public function responses(array $data, array $options = []): array {
        // return $this->_sendRequest('', $data, $options);
        return $this->_sendRequest('responses', $data, $options);
    }

    protected function _sendRequest(string $endpoint_path, array $data, array $options = []): array {
        $url = rtrim($this->_config['base_url'], '/') . '/' . ltrim($endpoint_path, '/');
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if (!empty($this->_config['api_key'])) {
            $headers['Authorization'] = 'Bearer ' . $this->_config['api_key'];
        }

        $model = $options['model'] ?? $this->getDefaultModel();
        $data['model'] = $model;

        // Добавляем остальные параметры запроса из $options.
        // $options уже содержит отфильтрованные Llm_Service параметры.
        // Важно: 'tools' и 'tool_choice' уже должны быть обработаны в chat() методе провайдера,
        // поэтому здесь мы их не добавляем напрямую из $options, чтобы избежать дублирования
        // или некорректной обработки, если модель не поддерживает инструменты.
        foreach ($options as $key => $value) {
            if ($key !== 'model' && $key !== 'tools' && $key !== 'tool_choice' && $key !== 'mcp_tools') {
                $data[$key] = $value;
            }
        }

        // Если это запрос к эндпоинту responses (MCP), переносим mcp_tools в ключ tools,
        // так как прокси-сервер ожидает именно такое именование в теле запроса.
        if ($endpoint_path === 'responses' && isset($options['mcp_tools'])) {
            $data['tools'] = $options['mcp_tools'];
        }

        return CurlRequest::post($url, $data, $headers, $this->_config['timeout'] ?? 30, $this->_config['proxy']?? null);
    }
}