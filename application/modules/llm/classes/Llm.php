<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Статический прокси-класс для удобного взаимодействия с LLM сервисом.
 * Адаптирован под новую структуру конфигурации (провайдер::модель).
 */
class Llm {

    /**
     * Магический метод для перенаправления статических вызовов в экземпляр Llm_Service.
     */
    public static function __callStatic($name, $arguments)
    {
        $service = Llm_Factory::get();

        if (method_exists($service, $name)) {
            return call_user_func_array([$service, $name], $arguments);
        }

        return self::_extended_methods($service, $name, $arguments);
    }

    /**
     * Реализация дополнительных удобных методов (ask, vision, extract и т.д.)
     */
    protected static function _extended_methods(Llm_Service $service, $name, $arguments)
    {
        switch ($name) {
            case 'ask':
                $question = $arguments[0] ?? '';
                $options = $arguments[1] ?? [];
                
                if (isset($service->temp_prompt_content)) {
                    $question = $service->temp_prompt_content;
                    unset($service->temp_prompt_content);
                }

                $messages = self::_get_messages_with_system($service, [['role' => 'user', 'content' => $question]]);
                $response = $service->chat($messages, $options);
                return $response['choices'][0]['message']['content'] ?? null;

            case 'system':
                $content = $arguments[0] ?? '';
                $service->temp_system_prompt = $content;
                return $service;

            case 'model':
                $model_name = $arguments[0] ?? null;
                if ($model_name) {
                    $service->set_model($model_name);
                }
                return $service;

            case 'prompt':
                $template_name = $arguments[0] ?? '';
                $variables = $arguments[1] ?? [];
                
                $template = Kohana::$config->load('llm_templates.' . $template_name);
                if (!$template) {
                    $template = $template_name;
                }

                foreach ($variables as $key => $value) {
                    $val_str = is_array($value) || is_object($value) ? self::_convert_to_text($value) : $value;
                    $template = str_replace('{{' . $key . '}}', $val_str, $template);
                }

                $service->temp_prompt_content = $template;
                return $service;
                
            case 'vision':
                $images = is_array($arguments[0]) ? $arguments[0] : [$arguments[0]];
                $question = $arguments[1] ?? 'Что на изображении?';
                $options = $arguments[2] ?? [];

                $content = [['type' => 'text', 'text' => $question]];
                foreach ($images as $img) {
                    $content[] = self::_prepare_image_content($img);
                }

                $messages = self::_get_messages_with_system($service, [['role' => 'user', 'content' => $content]]);
                $response = $service->chat($messages, $options);
                return $response['choices'][0]['message']['content'] ?? null;

            case 'extract':
                $text = $arguments[0] ?? '';
                $schema = $arguments[1] ?? [];
                $options = $arguments[2] ?? [];
                
                $prompt = "Extract data from the following text according to the schema: " . json_encode($schema, JSON_UNESCAPED_UNICODE) . "\n\nText: " . $text;
                $options['response_format'] = ['type' => 'json_object'];
                
                $messages = [['role' => 'user', 'content' => $prompt]];
                $response = $service->chat($messages, $options);
                $content = $response['choices'][0]['message']['content'] ?? '{}';
                return json_decode($content, true);

            case 'analyze':
            case 'summarize':
                $data = $arguments[0];
                $instruction = $arguments[1] ?? ($name === 'summarize' ? 'Summarize this' : 'Analyze this');
                
                $text_to_analyze = self::_convert_to_text($data);
                return self::ask($instruction . ":\n\n" . $text_to_analyze);

            case 'tools':
                $tools_callbacks = $arguments[0] ?? [];
                $service->temp_tools = $tools_callbacks;
                return $service;

            default:
                throw new Kohana_Exception('Method Llm:::method does not exist', [':method' => $name]);
        }
    }

    /**
     * Вспомогательный метод для подготовки контента изображения
     */
    protected static function _prepare_image_content($path)
    {
        if (strpos($path, 'http') === 0) {
            return ['type' => 'image_url', 'image_url' => ['url' => $path]];
        }
        
        if (file_exists($path)) {
            $type = pathinfo($path, PATHINFO_EXTENSION);
            $data = file_get_contents($path);
            $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
            return ['type' => 'image_url', 'image_url' => ['url' => $base64]];
        }

        return ['type' => 'image_url', 'image_url' => ['url' => $path]];
    }

    /**
     * Конвертация объектов (ORM и др) в текст
     */
    protected static function _convert_to_text($data)
    {
        if ($data instanceof ORM) {
            return json_encode($data->as_array(), JSON_UNESCAPED_UNICODE);
        }
        if (is_array($data) || is_object($data)) {
            return json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        return (string)$data;
    }

    /**
     * Добавление системного промпта в массив сообщений, если он был задан
     */
    protected static function _get_messages_with_system($service, $messages)
    {
        if (isset($service->temp_system_prompt)) {
            array_unshift($messages, ['role' => 'system', 'content' => $service->temp_system_prompt]);
            unset($service->temp_system_prompt);
        }
        return $messages;
    }
}