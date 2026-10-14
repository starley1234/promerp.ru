<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Сервис для рекурсивного обхода папок, чтения README.md и парсинга метаданных модулей.
 * 
 * @package Erp218
 * @version 1.0.0
 */
class Service_Erp218_Scanner {

    /**
     * @var Service_Erp218_Scanner
     */
    protected static $_instance;

    /**
     * Singleton instance
     * 
     * @return Service_Erp218_Scanner
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }
        return self::$_instance;
    }

    protected function __construct() {}

    /**
     * Запуск процесса сканирования директории модулей и синхронизация с БД.
     * 
     * @param int $user_id ID пользователя, инициировавшего сканирование
     * @return array Статистика сканирования [created, updated, skipped]
     * @throws Exception
     */
    public function scan_directory($user_id = 0) // Добавлен параметр $user_id со значением по умолчанию
    {
        $config = Kohana::$config->load('erp218_module_manager');
        $modules_path = $config->get('scanner')['path'];
        $excluded = $config->get('excluded_directories');
        
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'found' => 0]; // Добавлен 'found'
        
        if ( ! is_dir($modules_path))
        {
            throw new Kohana_Exception('Directory :path not found', [':path' => $modules_path]);
        }

        $directories = new DirectoryIterator($modules_path);
        
        foreach ($directories as $fileinfo)
        {
            if ($fileinfo->isDot() || ! $fileinfo->isDir())
                continue;

            $system_name = $fileinfo->getFilename();

            if (in_array($system_name, $excluded))
                continue;

            $stats['found']++; // Увеличиваем счетчик найденных директорий

            $module_path = $fileinfo->getRealPath();
            $metadata = $this->_extract_metadata($module_path);
            
            $db = Database::instance();
            $db->begin();
            
            try 
            {
                $module = ORM::factory('Erp218_Module')
                    ->where('system_name', '=', $system_name)
                    ->find();

                $is_new = ! $module->loaded();
                
                $data = [
                    'system_name'  => $system_name,
                    'display_name' => Arr::get($metadata, 'name', $system_name),
                    // 'description'  => Arr::get($metadata, 'description'),
                    'version'      => Arr::get($metadata, 'version', '1.0.0'),
                    'path'         => $module_path,
                    'created_by'   => $user_id,
                ];

                if ($is_new)
                {
                    $module->values($data)->save();
                    $stats['created']++;
                    
                    // Создаем запись для LLM анализа
                    $this->_init_llm_analysis($module->id, Arr::get($metadata, 'raw_readme'), $user_id);
                    
                    Event::fire('erp218.scanner.module_discovered', $module);
                }
                else
                {
                    // Обновляем только путь и описание, если оно изменилось
                    $module->path = $module_path;
                    // if (empty($module->description)) $module->description = $data['description'];
                    $module->save();
                    $stats['updated']++;
                }

                $db->commit();
            }
            catch (Exception $e)
            {
                $db->rollback();
                Kohana_Exception::log($e);
                $stats['skipped']++;
            }
        }

        Audit::log('module_manager', 'scan_completed', NULL, $stats);
        
        return $stats;
    }

    /**
     * Извлечение метаданных из файлов модуля (README.md и др.)
     * 
     * @param string $path Путь к папке модуля
     * @return array
     */
    protected function _extract_metadata($path)
    {
        $metadata = [
            'name'        => NULL,
            'description' => NULL,
            'version'     => '1.0.0',
            'raw_readme'  => '',
        ];

        $readme_path = $path . DIRECTORY_SEPARATOR . 'README.md';
        
        if (file_exists($readme_path))
        {
            $content = file_get_contents($readme_path);
            $metadata['raw_readme'] = $content;

            // Простейший парсинг заголовка H1 как названия
            if (preg_match('/^#\s+(.+)$/m', $content, $matches))
            {
                $metadata['name'] = trim($matches[1]);
            }

            // Простейший парсинг версии
            if (preg_match('/Version:\s*([0-9\.]+)/i', $content, $matches))
            {
                $metadata['version'] = trim($matches[1]);
            }

            // Описание (первый абзац после заголовка)
            $lines = explode("\n", $content);
            foreach ($lines as $line)
            {
                $line = trim($line);
                if ( ! empty($line) && $line[0] !== '#')
                {
                    $metadata['description'] = Text::limit_chars($line, 250);
                    break;
                }
            }
        }

        return $metadata;
    }

    /**
     * Инициализация записи для последующего LLM анализа
     * 
     * @param int $module_id
     * @param string $content
     * @param int $user_id
     */
    protected function _init_llm_analysis($module_id, $content, $user_id)
    {
        if (empty($content)) return;

        ORM::factory('Erp218_Llm')
            ->values([
                'module_id'       => $module_id,
                'raw_content'     => $content,
                'analysis_status' => 'pending',
                'created_by'      => $user_id
            ])
            ->save();
    }

    /**
     * Регистрация зависимости между модулями
     * 
     * @param array $data [module_id, required_module_id, min_version]
     * @param int $user_id
     * @return Model_Erp218_Dependency
     * @throws Validation_Exception
     */
    public function add_dependency(array $data, $user_id)
    {
        $validation = Validation::factory($data)
            ->rule('module_id', 'not_empty')
            ->rule('required_module_id', 'not_empty')
            ->rule('module_id', 'digit')
            ->rule('required_module_id', 'digit');

        if ( ! $validation->check())
        {
            throw new Validation_Exception($validation);
        }

        $db = Database::instance();
        $db->begin();

        try
        {
            $dependency = ORM::factory('Erp218_Dependency');
            $dependency_values = [
                'module_id'          => $data['module_id'],
                'required_module_id' => $data['required_module_id'],
                'min_version'        => Arr::get($data, 'min_version'),
                'created_by'         => $user_id
            ];
            $dependency->values($dependency_values)->save();

            $db->commit();
            
            Event::fire('erp218.dependency.added', $dependency_values);
            
            return $dependency;
        }
        catch (Exception $e)
        {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Обновление статуса модуля.
     *
     * @param int $module_id ID модуля
     * @param string $status Новый статус (alpha, beta, stable, archived)
     * @return bool
     */
    public function update_status($module_id, $status)
    {
        $module = ORM::factory('Erp218_Module', $module_id);

        if ( ! $module->loaded())
        {
            return FALSE;
        }

        // Проверка на допустимые статусы
        $allowed_statuses = ['alpha', 'beta', 'stable', 'archived'];
        if ( ! in_array($status, $allowed_statuses))
        {
            Kohana_Exception::log(new Kohana_Exception('Invalid module status: :status', [':status' => $status]));
            return FALSE;
        }

        $module->status = $status;
        try
        {
            $module->save();
            return TRUE;
        }
        catch (Exception $e)
        {
            Kohana_Exception::log($e);
            return FALSE;
        }
    }

    /**
     * Запуск сканирования директории модулей.
     * Этот метод может быть вызван извне, например, из контроллера API.
     *
     * @param int $user_id ID пользователя, инициировавшего сканирование
     * @return array Статистика сканирования
     */
    public function scan_modules_directory($user_id = 0)
    {
        return $this->scan_directory($user_id);
    }
}