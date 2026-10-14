<?php defined('SYSPATH') or die('No direct script access.'); 

/**
 * Универсальный сервис для взаимодействия с LLM.
 * Инкапсулирует логику выбора провайдера и вызова его метода chat.
 */
class Llm_Service { 
    protected $_default_provider_name;
    protected $_providers = []; // Массив экземпляров провайдеров
    protected $_model_aliases = []; // Массив для хранения алиасов моделей

    // Текущие выбранные провайдер и модель для цепочечных вызовов
    protected $_current_provider_name;
    protected $_current_model_name;

    // Временные свойства для хранения состояния между вызовами в цепочке
    public $temp_system_prompt;
    public $temp_prompt_content;
    public $temp_messages;
    public $temp_tools;
    protected $_temp_mcp_tools;
    public $temp_options = [];

    public function __construct(string $default_provider_name, array $providers_config, array $model_aliases) {
        $this->_default_provider_name = $default_provider_name;
        $this->_current_provider_name = $default_provider_name;
        $this->_model_aliases = $model_aliases;

        foreach ($providers_config as $provider_name => $config) {
            switch ($provider_name) {
                case 'openrouter':
                case 'openai':
                    $this->_providers[$provider_name] = new Llm_Provider_OpenAICompatible($provider_name, $config);
                    break;
                case 'local':
                case 'local_lms':
                    $this->_providers[$provider_name] = new Llm_Provider_Toolforge($provider_name, $config);
                    break;
                default:
                    Kohana::$log->add(Log::WARNING, 'Unknown LLM provider configured: :provider', [':provider' => $provider_name]);
                    break;
            }
        }

        if (!isset($this->_providers[$this->_default_provider_name])) {
            throw new Kohana_Exception('Default LLM provider ":provider" not found or not configured.', [':provider' => $this->_default_provider_name]);
        }
    }

    public function provider(string $provider_name): self {
        if (!isset($this->_providers[$provider_name])) {
            throw new Kohana_Exception('LLM provider ":provider" not found.', [':provider' => $provider_name]);
        }
        $this->_current_provider_name = $provider_name;
        $this->_current_model_name = null;
        return $this;
    }

    public function model(string $model_name): self {
        $this->_current_model_name = $model_name;
        return $this;
    }

    public function set_model(string $model_name): self {
        return $this->model($model_name);
    }

    public function system(string $content): self {
        $this->temp_system_prompt = $content;
        return $this;
    }

    public function messages(array $messages): self {
        $this->temp_messages = $messages;
        return $this;
    }

    public function tools(array $tools): self {
        $this->temp_tools = $tools;
        return $this;
    }

    public function mcp(array $config): self {
        $this->_temp_mcp_tools = $config;
        return $this;
    }

    public function options(array $options): self {
        $this->temp_options = Arr::merge($this->temp_options, $options);
        return $this;
    }

    public function reset(): self {
        $this->_current_provider_name = $this->_default_provider_name;
        $this->_current_model_name = null;
        $this->temp_system_prompt = null;
        $this->temp_prompt_content = null;
        $this->temp_messages = null;
        $this->temp_tools = null;
        $this->_temp_mcp_tools = null;
        $this->temp_options = [];
        return $this;
    }

    /**
     * Упрощенный метод для получения текстового ответа на запрос.
     */
    public function ask(string $prompt = null, array $options = []): string {
        $messages = [];
        
        if ($this->temp_messages) {
            $messages = $this->temp_messages;
        } else {
            if ($this->temp_system_prompt) {
                $messages[] = ['role' => 'system', 'content' => $this->temp_system_prompt];
            }

            $final_prompt = $prompt ?? $this->temp_prompt_content;
            if (!$final_prompt) {
                throw new Kohana_Exception('Prompt content is missing for ask() method.');
            }

            $messages[] = ['role' => 'user', 'content' => $final_prompt];
        }

        // Объединяем опции из шаблона и переданные в метод
        $merged_options = Arr::merge($this->temp_options, $options);

        $response = $this->chat($messages, $merged_options);
        return $response['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Универсальный метод для взаимодействия с LLM.
     */
    public function chat(array $messages, array $options = []): array {
        $start_time = microtime(true);
        $resolved_provider_name = $this->_current_provider_name;
        $resolved_model_name = $this->_current_model_name;
        $status = 'success';
        $error_message = null;

        if ($resolved_model_name !== null && strpos($resolved_model_name, '::') !== false) {
            $parts = explode('::', $resolved_model_name, 2);
            if (count($parts) === 2 && isset($this->_providers[$parts[0]])) {
                $resolved_provider_name = $parts[0];
                $resolved_model_name = $parts[1];
            }
        }
		
		// Если есть замена старой модели, на аналогичную, новую
        if ($resolved_model_name !== null) {
            $alias_key = $resolved_provider_name . '::' . $resolved_model_name;
            if (isset($this->_model_aliases[$alias_key])) {
				
                $alias_info = $this->_model_aliases[$alias_key];
                $resolved_model_name = $alias_info['model'];
				
				if ($resolved_model_name !== null && strpos($resolved_model_name, '::') !== false) {
					$parts = explode('::', $resolved_model_name, 2);
					if (count($parts) === 2 && isset($this->_providers[$parts[0]])) {
						$resolved_provider_name = $parts[0];
						$resolved_model_name = $parts[1];
					}
				}
            }
        }

        if (!isset($this->_providers[$resolved_provider_name])) {
            $error_message = 'LLM provider "' . $resolved_provider_name . '" not found.';
            $status = 'error';
            $this->_saveRequestLog($resolved_provider_name, $resolved_model_name ?? 'unknown', $messages, $options, [], $start_time, $status, $error_message);
            throw new Kohana_Exception($error_message);
        }

        $provider = $this->_providers[$resolved_provider_name];
        
        // Объединяем переданные опции с накопленными в temp_options
        $final_options = Arr::merge($this->temp_options, $options);

        if ($resolved_model_name !== null) {
            // Модель задана явно через Llm::model() или в $options
            $final_options['model'] = $resolved_model_name;
        } else {
            // Модель НЕ задана явно. Проверяем, нет ли в сообщениях изображений
            $has_images = false;
            foreach ($messages as $msg) {
                if (isset($msg['content']) && is_array($msg['content'])) {
                    foreach ($msg['content'] as $part) {
                        if (isset($part['type']) && $part['type'] === 'image_url') {
                            $has_images = true;
                            break 2;
                        }
                    }
                }
            }

            if ($has_images) {
                // Пытаемся взять vision_model из корня конфига
                $global_vision = Kohana::$config->load('llm.vision_model');
                if ($global_vision) {
                    // Применяем логику алиасов для vision_model
                    if (isset($this->_model_aliases[$global_vision])) {
                        $global_vision = $this->_model_aliases[$global_vision]['model'];
                    }

                    // Если в vision_model указан провайдер (provider::model)
                    if (strpos($global_vision, '::') !== false) {
                        list($v_provider, $v_model) = explode('::', $global_vision, 2);
                        if (isset($this->_providers[$v_provider])) {
                            $provider = $this->_providers[$v_provider];
                            $resolved_provider_name = $v_provider;
                            $final_options['model'] = $v_model;
                        } else {
                            $final_options['model'] = $v_model;
                        }
                    } else {
                        $final_options['model'] = $global_vision;
                    }
                } else {
                    $final_options['model'] = $provider->getDefaultModel();
                }
            } else {
                // Обычный текстовый запрос
                $final_options['model'] = $provider->getDefaultModel();
            }
        }

        // Сохраняем инструменты во временную переменную, чтобы они были доступны в _handleToolCalls
        $active_tools = $this->temp_tools;

        if ($active_tools !== null && !isset($final_options['tools'])) {
            $formatted_tools = [];
            foreach ($active_tools as $name => $callback) {
                $formatted_tools[] = [
                    'type' => 'function',
                    'function' => [
                        'name' => $name,
                        'parameters' => $this->_getFunctionParameters($callback)
                    ]
                ];
            }
            $final_options['tools'] = $formatted_tools;
        }

        if ($this->_temp_mcp_tools) {
            $final_options['mcp_tools'] = $this->_temp_mcp_tools;
        }

        $model_info = $provider->getModelInfo($final_options['model']);

        if (isset($model_info['unsupported_params'])) {
            foreach ($model_info['unsupported_params'] as $param) {
                unset($final_options[$param]);
            }
        }

        if (isset($model_info['default_params'])) {
            foreach ($model_info['default_params'] as $param => $value) {
                if (!isset($final_options[$param])) {
                    $final_options[$param] = $value;
                }
            }
        }

        if (!empty($final_options['tools'])) {
            // Если инструменты переданы явно, мы доверяем пользователю.
            // Проверяем только если модель заведомо известна как не поддерживающая tools.
            if (!$provider->supportsTools($final_options['model'])) {
                unset($final_options['tools']);
                unset($final_options['tool_choice']);
            } elseif (!isset($final_options['tool_choice'])) {
                $final_options['tool_choice'] = 'auto';
            }
        }

        try {
            $this->reconnect_db(); 
            $response = $provider->chat($messages, $final_options);
        } catch (Exception $e) {
            $status = 'error';
            $error_message = $e->getMessage();
            $this->_saveRequestLog($resolved_provider_name, $final_options['model'], $messages, $final_options, [], $start_time, $status, $error_message);
            throw $e;
        }

        $this->_saveRequestLog($resolved_provider_name, $final_options['model'], $messages, $final_options, $response, $start_time, $status, $error_message);
        
        // Вызываем локальные инструменты только если это НЕ MCP запрос
        if (!$this->_temp_mcp_tools && isset($response['choices'][0]['message']['tool_calls']) && !empty($response['choices'][0]['message']['tool_calls'])) {
            $response = $this->_handleToolCalls($provider, $messages, $response, $final_options, $active_tools);
        }

        $this->reset();

        return $response;
    }

    /**
     * Получение векторного представления текста (embedding).
     */
    public function get_embedding(string $input, array $options = []): array { 
        $start_time = microtime(true);
        $resolved_provider_name = $this->_current_provider_name;
        $status = 'success';
        $error_message = null;

        // Пытаемся взять embedding_model из конфига
        $embedding_model = Kohana::$config->load('llm.embedding_model');
        
        if ($embedding_model) {
            // Применяем логику алиасов
            if (isset($this->_model_aliases[$embedding_model])) {
                $embedding_model = $this->_model_aliases[$embedding_model]['model'];
            }

            // Если указан провайдер (provider::model)
            if (strpos($embedding_model, '::') !== false) {
                list($e_provider, $e_model) = explode('::', $embedding_model, 2);
                if (isset($this->_providers[$e_provider])) {
                    $resolved_provider_name = $e_provider;
                    $options['model'] = $e_model;
                } else {
                    $options['model'] = $e_model;
                }
            } else {
                $options['model'] = $embedding_model;
            }
        }

        if (!isset($this->_providers[$resolved_provider_name])) {
            throw new Kohana_Exception('LLM provider ":provider" for embeddings not found.', [':provider' => $resolved_provider_name]);
        }

        $provider = $this->_providers[$resolved_provider_name];
        
        // Если провайдер Toolforge (local), он поддерживает embeddings через LM Studio
        // Если метод не существует, проверяем, не является ли это провайдером, который мы можем вызвать
        if (!method_exists($provider, 'embeddings') && !($provider instanceof Llm_Provider_Toolforge)) {
            throw new Kohana_Exception('Provider ":provider" does not support embeddings.', [':provider' => $resolved_provider_name]);
        }

        try {
            $this->reconnect_db();
            
            // Для Toolforge/OpenAICompatible вызываем embeddings
            $response = $provider->embeddings($input, $options);
            
            // Логируем запрос
            $this->_saveRequestLog($resolved_provider_name, $options['model'] ?? 'default-embedding', [['role' => 'user', 'content' => $input]], $options, $response, $start_time, $status, $error_message);
            
            return $response['data'][0]['embedding'] ?? [];
        } catch (Exception $e) {
            $status = 'error';
            $error_message = $e->getMessage();
            $this->_saveRequestLog($resolved_provider_name, $options['model'] ?? 'default-embedding', [['role' => 'user', 'content' => $input]], $options, [], $start_time, $status, $error_message);
            throw $e;
        } finally {
            $this->reset();
        }
    }

    /**
     * Анализ изображений.
     */
    public function vision($images, string $prompt, array $options = []): string {
        $images = is_array($images) ? $images : [$images];
        $content = [['type' => 'text', 'text' => $prompt]];

        foreach ($images as $image) {
            if (strpos($image, 'http') === 0) {
                $content[] = ['type' => 'image_url', 'image_url' => ['url' => $image]];
            } elseif (file_exists($image)) {
                $type = pathinfo($image, PATHINFO_EXTENSION);
                $data = base64_encode(file_get_contents($image));
                $content[] = ['type' => 'image_url', 'image_url' => ['url' => "data:image/$type;base64,$data"]];
            } else {
                $content[] = ['type' => 'image_url', 'image_url' => ['url' => $image]];
            }
        }

        $messages = [['role' => 'user', 'content' => $content]];
        $response = $this->chat($messages, $options);
        return $response['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Извлечение структурированных данных в формате JSON.
     */
    public function extract(string $text, array $schema, array $options = []): array {
        $schema_str = json_encode($schema, JSON_UNESCAPED_UNICODE);
        $prompt = "Extract data from the following text according to this JSON schema: $schema_str\n\nText: $text\n\nReturn ONLY valid JSON.";
        
        // Исправлено: OpenAI API требует 'text' или 'json_schema' (в некоторых случаях 'json_object').
        // 'json_object' поддерживается не всеми моделями и требует слова "JSON" в промпте.
        $options['response_format'] = ['type' => 'text'];
        
        $response_text = $this->ask($prompt, $options);
        
        // Очистка от markdown-разметки, если модель вернула ```json ... ```
        $response_text = trim($response_text);
        if (strpos($response_text, '```') !== false) {
            $response_text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $response_text);
        }

        $data = json_decode($response_text, true);
        
        if (is_array($data)) {
            // Если LLM вернула объект, где данные лежат внутри ключа (например, "data" или "result")
            if (count($data) === 1) {
                $first_val = reset($data);
                if (is_array($first_val)) {
                    return $first_val;
                }
            }
            return $data;
        }
        
        return [];
    }

    /**
     * Анализ объекта (ORM или массив).
     */
    public function analyze($object, string $instruction, array $options = []): string {
        $data = ($object instanceof ORM) ? $object->as_array() : $object;
        $json_data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $prompt = "Instruction: $instruction\n\nData:\n$json_data";
        return $this->ask($prompt, $options);
    }

    /**
     * Краткое резюме объекта.
     */
    public function summarize($object, array $options = []): string {
        return $this->analyze($object, "Provide a concise summary of this data.", $options);
    }

    /**
     * Использование именованного шаблона из базы данных.
     */
    public function prompt(string $template_name, array $variables = []): self {
        $template = ORM::factory('Llm_Prompt')->where('name', '=', $template_name)->find();
        
        if (!$template->loaded()) {
            throw new Kohana_Exception('LLM prompt template ":name" not found in database.', [':name' => $template_name]);
        }

        $this->temp_prompt_content = $template->compile_prompt($variables);

        if ($template->system) {
            $this->system($template->system);
        }

        if ($template->provider_name) {
            $this->provider($template->provider_name);
        }

        if ($template->model_name) {
            $this->model($template->model_name);
        }

        $this->temp_options = $template->get_options();

        return $this;
    }

    protected function _handleToolCalls(Interface_LlmProvider $provider, array $messages, array $response, array $options, ?array $active_tools): array {
        $message = $response['choices'][0]['message'];
        $messages[] = $message;

        foreach ($message['tool_calls'] as $tool_call) {
            $function_name = $tool_call['function']['name'];
            $function_args = json_decode($tool_call['function']['arguments'], true);

            $result = null;
            if (isset($active_tools[$function_name]) && is_callable($active_tools[$function_name])) {
                try {
                    $result = call_user_func_array($active_tools[$function_name], $function_args);
                } catch (Exception $e) {
                    $result = "Error executing tool: " . $e->getMessage();
                }
            } else {
                $result = "Tool '$function_name' not found or not callable.";
            }

            $messages[] = [
                'tool_call_id' => $tool_call['id'],
                'role'         => 'tool',
                'name'         => $function_name,
                'content'      => is_array($result) || is_object($result) ? json_encode($result, JSON_UNESCAPED_UNICODE) : (string)$result,
            ];
        }

        return $provider->chat($messages, $options);
    }

    protected function _getFunctionParameters($callback): array {
        try {
            if (is_array($callback)) {
                $ref = new ReflectionMethod($callback[0], $callback[1]);
            } elseif ($callback instanceof Closure || is_string($callback)) {
                $ref = new ReflectionFunction($callback);
            } else {
                return ['type' => 'object', 'properties' => (object)[]];
            }

            $properties = [];
            $required = [];

            foreach ($ref->getParameters() as $param) {
                $name = $param->getName();
                $properties[$name] = ['type' => 'string']; // По умолчанию string
                if (!$param->isOptional()) {
                    $required[] = $name;
                }
            }

            return [
                'type' => 'object',
                'properties' => $properties,
                'required' => $required
            ];
        } catch (Exception $e) {
            return ['type' => 'object', 'properties' => (object)[]];
        }
    }
    
    protected function reconnect_db($group = 'default') {
        
        try {
            $db = Database::instance($group);
            // Проверка живое ли соединение
            $db->query(Database::SELECT, 'SELECT 1');
        } catch (Exception $e) {
            // Если упало - жестко удаляем инстанс
            if (isset(Database::$instances[$group])) {
                Database::$instances[$group]->disconnect();
                unset(Database::$instances[$group]);
            }
            // Kohana создаст новое при следующем вызове instance()
        }
        
        
    }

    protected function _saveRequestLog(string $provider_name, string $model_name, array $request_messages, array $request_options, array $response_data, float $start_time, string $status, ?string $error_message): void {
        try {
            $this->reconnect_db();
            $end_time = microtime(true);
            $usage = $response_data['usage'] ?? [];

            $log = ORM::factory('Llm_Request');
            $log->provider_name      = $provider_name;
            $log->model_name         = $model_name;
            $log->request_messages   = json_encode($request_messages, JSON_UNESCAPED_UNICODE);
            $log->request_options    = json_encode($request_options, JSON_UNESCAPED_UNICODE);
            $log->response_data      = ($status === 'success') ? json_encode($response_data, JSON_UNESCAPED_UNICODE) : null;
            $log->prompt_tokens      = $usage['prompt_tokens'] ?? null;
            $log->completion_tokens  = $usage['completion_tokens'] ?? null;
            $log->total_tokens       = $usage['total_tokens'] ?? null;
            $log->request_timestamp  = (int)$start_time;
            $log->response_timestamp = ($status === 'success') ? (int)$end_time : null;
            $log->duration_ms        = (int)(($end_time - $start_time) * 1000);
            $log->status             = $status;
            $log->error_message      = $error_message;
            $log->session_id         = Session::instance()->id();
            $log->save();
        } catch (Exception $e) {
            Kohana::$log->add(Log::ERROR, 'Failed to save LLM log: :error', [':error' => $e->getMessage()]);
        }
    }

    public function getProvider(string $provider_name = null): Interface_LlmProvider {
        $name = $provider_name ?? $this->_default_provider_name;
        if (!isset($this->_providers[$name])) {
            throw new Kohana_Exception('LLM provider ":provider" not found.', [':provider' => $name]);
        }
        return $this->_providers[$name];
    }
}