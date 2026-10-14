<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Фабрика для создания экземпляров Llm_Service.
 * Адаптирована под новую структуру конфигурации с использованием default_model в формате provider::model.
 */
class Llm_Factory {

    protected static $_instance = null;

    /**
     * Возвращает единственный экземпляр Llm_Service.
     *
     * @return Llm_Service
     * @throws Kohana_Exception В случае, если конфигурация LLM некорректна.
     */
    public static function get(): Llm_Service {
        if (self::$_instance === null) {
            $config = Kohana::$config->load('llm');

            // Извлекаем глобальную модель по умолчанию (формат provider::model)
            $default_model_full = $config->get('default_model');
            
            if (!$default_model_full) {
                throw new Kohana_Exception('Default LLM model (default_model) is not specified in config/llm.php');
            }

            // Парсим провайдера и модель
            if (strpos($default_model_full, '::') !== false) {
                list($default_provider_name, $default_model_name) = explode('::', $default_model_full, 2);
            } else {
                // Фолбэк, если указан только провайдер (для обратной совместимости)
                $default_provider_name = $default_model_full;
                $default_model_name = null;
            }

            $providers_config = $config->get('providers') ?: [];
            $model_aliases = $config->get('model_aliases') ?: [];

            // Создаем сервис
            $service = new Llm_Service($default_provider_name, $providers_config, $model_aliases);

            // Если в конфиге была указана конкретная модель, устанавливаем её в сервисе
            if ($default_model_name) {
                $service->set_model($default_model_full);
            }

            self::$_instance = $service;
        }
        return self::$_instance;
    }
}