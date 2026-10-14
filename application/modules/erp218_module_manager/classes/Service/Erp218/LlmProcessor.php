<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Сервис интеграции с LLM для анализа модулей системы.
 * Инкапсулирует логику отправки запросов к нейросети и обработки результатов.
 * 
 * @package Erp218
 * @version 1.0.0
 */
class Service_Erp218_LlmProcessor {

    /**
     * @var Service_Erp218_LlmProcessor
     */
    protected static $_instance;

    /**
     * @var Config_Group
     */
    protected $_config;

    /**
     * Singleton instance
     * 
     * @return Service_Erp218_LlmProcessor
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Приватный конструктор для инициализации конфигурации
     */
    protected function __construct()
    {
        $this->_config = Kohana::$config->load('erp218_module_manager');
    }

    /**
     * Анализирует содержимое модуля (README или код) через LLM.
     * 
     * @param int $module_id ID модуля из таблицы erp_218_modules
     * @param string $content Текст для анализа (содержимое файлов)
     * @param int $user_id ID пользователя, инициировавшего процесс
     * @return Model_Erp218_Llm
     * @throws Exception
     */
    public function analyze_module_content($module_id, $content, $user_id)
    {
        // 1. Валидация входных данных
        $validation = Validation::factory(array(
            'module_id' => $module_id,
            'content'   => $content,
            'user_id'   => $user_id
        ))
        ->rule('module_id', 'not_empty')
        ->rule('module_id', 'numeric')
        ->rule('user_id', 'not_empty');

        if ( ! $validation->check())
        {
            throw new Kohana_Exception('Invalid LLM analysis data: :errors', array(
                ':errors' => json_encode($validation->errors('validation'))
            ));
        }
        

        try 
        {
            // 2. Поиск или создание записи анализа
            $analysis = Model_Erp218_Llm::find_or_create_by_module_id($module_id);
            $module = Model_Erp218_Module::get_by_id($module_id);

            $analysis->module_id = $module_id;
            $analysis->raw_content = $content;
            $analysis->analysis_status = 'pending';
            $analysis->created_by = $user_id;
            $analysis->save();

            // 3. Подготовка промпта и запрос к LLM через системную фабрику
            // Обновленный промпт для извлечения display_name и description
            $prompt = "Ты — эксперт по анализу модулей программного обеспечения. Твоя задача — проанализировать предоставленный текст (обычно это README.md или описание модуля) и извлечь из него следующую информацию в формате JSON:
            - `system_id`: Числовой префикс модуля, например, '218'. Если не указан явно, сгенерируй на основе названия модуля или префикса к таблицам.
            - `display_name`: Понятное название модуля (придумай общеупотребимое, отражающее его суть, например, `Управление проектами` или `Хранилище файлов` или `CRM система` и т.п), до 50 символов.
            - `description`: Краткое описание функционала, до 500 символов (на русском языке).
            - `icon`: Иконка модуля (например, 'fa-cog'). Если не указана, предложи подходящую.
            - `version`: Версия в формате SemVer, например, '1.0.0'. Если не указана, используй '1.0.0'.
            - `main_page_uri`: URI главной страницы модуля, например, '/erp218_index'. Если не указан, предложи логичный URI.
            - `functionality`: JSON-объект с перечнем функционала. Опиши основные функции модуля в виде массива строк.
            - `entities`: JSON-объект с перечнем сущностей модуля. Опиши основные сущности, с которыми работает модуль, в виде массива строк.

            Если какая-либо информация явно указана в тексте, используй её. В противном случае, сгенерируй её на основе содержимого.
            Форматируй свой ответ строго в формате JSON.

            Пример ожидаемого ответа:
            ```json
            {
                \"system_id\": \"218\",
                \"display_name\": \"CRM-система\",
                \"description\": \"Модуль для управления взаимоотношениями с клиентами, включающий контакты, сделки и задачи.\",
                \"icon\": \"fa-users\",
                \"version\": \"1.2.0\",
                \"main_page_uri\": \"/erp218_index\",
                \"functionality\": [
                    \"Управление контактами\",
                    \"Отслеживание сделок\",
                    \"Планирование задач\",
                    \"Генерация отчетов\"
                ],
                \"entities\": [
                   	 \"Customer\",
                     \"Contact\",
                     \"Deal\",
                     \"Task\"
                ]
            }
            ```
            Текст для анализа:
            ";
            $messages = array(
                array('role' => 'system', 'content' => $prompt),
                array('role' => 'user', 'content' => $content)
            );

            // Вызов системного ядра LLM (Llm_Factory из Controller_Base context)
            $llm_response = Llm_Factory::get()->chat($messages);
            
            if (isset($llm_response['choices'][0]['message']['content']))
            {
                $ai_summary = $llm_response['choices'][0]['message']['content'];
                
                // 4. Обновление результата
                $analysis->ai_summary = $ai_summary;
                $analysis->analysis_status = 'completed';
                $analysis->last_scan_at = date('Y-m-d H:i:s');
                $analysis->save();

                // 5. Заполнение полей модуля, если они пустые
                if ($module->loaded()) {
                    // Удаляем возможные блоки кода из ответа LLM, если они есть
                    $cleaned_ai_summary = preg_replace('/```json\s*(.*?)\s*```/s', '$1', $ai_summary);
                    $llm_data = json_decode($cleaned_ai_summary, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $fields_to_update = [
                            'system_id',
                            'display_name',
                            'description',
                            'icon',
                            'version',
                            'main_page_uri',
                            'functionality',
                            'entities'
                        ];

                        // Поля, которые не должны перезаписываться, если они уже заполнены
                        $protected_fields = ['main_page_uri', 'status', 'version', 'icon'];

                        $module_changed = false;
                        foreach ($fields_to_update as $field) {
                            if (isset($llm_data[$field])) {
                                // Если поле защищено и уже имеет значение, пропускаем обновление
                                if (in_array($field, $protected_fields) && !empty($module->$field)) {
                                    continue;
                                }

                                // Для JSON полей, убедимся, что это валидный JSON
                                if (in_array($field, ['functionality', 'entities'])) {
                                    $module->$field = json_encode($llm_data[$field]);
                                } else {
                                    $module->$field = $llm_data[$field];
                                }
                                $module_changed = true;
                            }
                        }

                        if ($module_changed) {
                            $module->save();
                            Audit::log('module_manager', 'module_updated_by_llm', $module_id);
                        }
                    } else {
                        Kohana::$log->add(Kohana::WARNING, 'LLM response is not valid JSON for module :id: :response', [
                            ':id' => $module_id,
                            ':response' => $ai_summary
                        ]);
                    }
                }

                // 6. Логирование и события
                Audit::log('module_manager', 'llm_analysis_success', $module_id);
                Event::fire('erp218.module.llm_completed', array(
                    'module_id' => $module_id,
                    'summary'   => $ai_summary
                ));
            }
            else
            {
                throw new Exception('LLM response format error');
            }

            return $analysis;
        }
        catch (Exception $e)
        {
            // Фиксация ошибки в БД если запись существует
            if (isset($analysis) AND $analysis->loaded())
            {
                $analysis->analysis_status = 'failed';
                $analysis->save();
            }

            Audit::log('module_manager', 'llm_analysis_failed', $module_id);
            throw $e;
        }
    }

    /**
     * Очистка истории анализа для модуля
     * 
     * @param int $module_id
     * @return bool
     */
    public function reset_analysis($module_id)
    {
        try
        {
            $analysis = Model_Erp218_Llm::find_by_module_id($module_id);

            if ($analysis->loaded())
            {
                $analysis->delete();
                Audit::log('module_manager', 'llm_analysis_reset', $module_id);
            }

            return TRUE;
        }
        catch (Exception $e)
        {
            throw $e;
        }
    }

    /**
     * Запускает LLM анализ для конкретного модуля.
     * 
     * @param int $module_id ID модуля
     * @param int $user_id ID пользователя, инициировавшего анализ (по умолчанию 0 для системных вызовов)
     * @return Model_Erp218_Llm|null Результат анализа или null в случае ошибки
     */
    public function run_ai_analysis($module_id, $user_id = 0)
    {
        // Используем статический метод get_by_id из Model_Erp218_Module
        $module = Model_Erp218_Module::get_by_id($module_id);
        
        

        if ( ! $module->loaded())
        {
            throw new Kohana_Exception('Module with ID :id not found.', [':id' => $module_id]);
        }
        

        // Получаем содержимое для анализа. Например, README.md или код.
        // Для простоты, пока будем использовать заглушку или реальный README.md
        $content_to_analyze = $this->_get_module_content_for_analysis($module);

        if (empty($content_to_analyze))
        {
            // Если нет контента для анализа, можно пропустить или пометить как "нет данных"
            Audit::log('module_manager', 'llm_analysis_skipped_no_content', $module_id);
            return null;
        }

        try
        {
            return $this->analyze_module_content($module_id, $content_to_analyze, $user_id);
        }
        catch (Exception $e)
        {
            // Логируем ошибку, но не прерываем выполнение для других модулей в пакетном режиме
            Kohana::$log->add(Kohana::ERROR, 'LLM analysis failed for module :id: :error', [
                ':id' => $module_id,
                ':error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Обрабатывает все модули, которые ожидают LLM анализа.
     * 
     * @param int $user_id ID пользователя, инициировавшего пакетный анализ (по умолчанию 0 для системных вызовов)
     * @return int Количество обработанных модулей
     */
    public function process_pending_modules($user_id = 0)
    {
        $processed_count = 0;

        // Находим модули, которые еще не были проанализированы или анализ которых завершился ошибкой
        $modules_to_analyze = Model_Erp218_Module::get_modules_for_llm_analysis();
        

        foreach ($modules_to_analyze as $module)
        {
            // Проверяем, есть ли уже запись об анализе для этого модуля
            $existing_analysis = Model_Erp218_Llm::find_by_module_id($module->id);

            // Если анализ уже был и завершился успешно, пропускаем
            if ($existing_analysis->loaded() && $existing_analysis->analysis_status === 'completed')
            {
                // continue;
            }
            
            

            // Запускаем анализ для текущего модуля
            $result = $this->run_ai_analysis($module->id, $user_id);
            if ($result !== null)
            {
                $processed_count++;
            }
        }

        return $processed_count;
    }

    /**
     * Вспомогательный метод для получения контента модуля для анализа.
     * Может быть расширен для чтения README.md, файлов кода и т.д.
     * 
     * @param Model_Erp218_Module $module
     * @return string
     */
    protected function _get_module_content_for_analysis(Model_Erp218_Module $module)
    {
        $content = '';
        // Используем APPPATH для доступа к модулям в application/modules/
        $module_path = APPPATH . 'modules' . DIRECTORY_SEPARATOR . $module->system_name . DIRECTORY_SEPARATOR;

        // Попытка прочитать README.md
        $readme_path = $module_path . 'README.md';
        if (file_exists($readme_path))
        {
            $content .= file_get_contents($readme_path);
        }
        // Можно добавить чтение других файлов, например, основных классов контроллеров или моделей
        // Для примера, пока ограничимся README.md
        
        return $content;
    }
}