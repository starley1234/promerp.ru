<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Модель конфигурации драйверов хранилищ (Backend)
 * 
 * Отвечает за управление конфигурациями различных драйверов хранилищ:
 * - Local (локальная файловая система)
 * - S3 (Amazon S3 / Minio совместимые хранилища)
 * - FTP (FTP серверы)
 * 
 * @property int $id ID записи
 * @property string $name Уникальное имя конфигурации (например: 's3_main', 'local_temp')
 * @property string $driver Тип драйвера: 'local', 's3', 'ftp'
 * @property string $config_data JSON-строка с настройками драйвера (ключи доступа, бакеты, регионы, пути)
 * @property int $is_active Активна ли конфигурация (1/0)
 * @property Model_Erp212_File[] $files Связанные файлы, использующие этот бэкенд
 * @property-read string $config_array Декодированный массив настроек config_data (виртуальное свойство)
 */
class Model_Erp212_Backend extends ORM {
    
    /**
     * Имя таблицы в БД с префиксом модуля
     * @var string
     */
    protected $_table_name = 'erp_212_storage_backends';
    
    /**
     * Связь "один ко многим": один бэкенд может использоваться множеством файлов
     * @var array
     */
    protected $_has_many = array(
        'files' => array(
            'model' => 'Erp212_File',
            'foreign_key' => 'backend_id'
        )
    );
    
    /**
     * Связь "многие к одному": отсутствуют внешние ключи на другие таблицы
     * @var array
     */
    protected $_belongs_to = array();
    
    /**
     * Правила валидации для полей модели
     * @return array
     */
    public function rules() {
        return array(
            'name' => array(
                array('not_empty'),
                array('min_length', array(':value', 2)),
                array('max_length', array(':value', 50)),
                array(array($this, 'unique_name'), array(':value', ':validation', ':field'))
            ),
            'driver' => array(
                array('not_empty'),
                array('in_array', array(':value', array('local', 's3', 'ftp')))
            ),
            'config_data' => array(
                array('json_decode', array(':value', TRUE))
            ),
            'is_active' => array(
                array('boolean')
            )
        );
    }
    
    /**
     * Метод для проверки уникальности имени бэкенда (исключая текущую запись при обновлении)
     * 
     * @param string $name Проверяемое имя
     * @param Validation $validation Объект валидации
     * @param string $field Поле валидации
     * @return bool
     */
    public function unique_name($name, Validation $validation, $field) {
        $query = DB::select()
            ->from($this->_table_name)
            ->where('name', '=', $name);
            
        // При обновлении исключаем текущую запись
        if ($this->loaded()) {
            $query->where('id', '!=', $this->id);
        }
        
        return !$query->execute()->count();
    }
    
    /**
     * Геттер для декодирования config_data в массив
     * Возвращает пустой массив если config_data пуст или некорректный JSON
     * 
     * @return array
     */
    public function get_config_array() {
        if (empty($this->config_data)) {
            return array();
        }
        
        $config = json_decode($this->config_data, TRUE);
        return is_array($config) ? $config : array();
    }
    
    /**
     * Сеттер для автоматического кодирования массива настроек в JSON
     * 
     * @param array $config Массив настроек драйвера
     */
    public function set_config_array(array $config) {
        $this->config_data = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Переопределение before_save для автоматической валидации JSON
     * 
     * @return void
     */
    protected function before_save() {
        if (is_array($this->config_data)) {
            $this->set_config_array($this->config_data);
        }
        parent::before_save();
    }
    
    /**
     * Получение активных бэкендов определенного типа
     * 
     * @param string $driver Тип драйвера (local/s3/ftp)
     * @return ORM
     */
    public function get_active_by_driver($driver) {
        return $this->where('driver', '=', $driver)
                    ->where('is_active', '=', 1)
                    ->find_all();
    }
    
    /**
     * Проверка возможности использования бэкенда (активен и имеет корректную конфигурацию)
     * 
     * @return bool
     */
    public function is_usable() {
        if (!$this->is_active) {
            return FALSE;
        }
        
        $config = $this->get_config_array();
        
        switch ($this->driver) {
            case 'local':
                return !empty($config['path']) && is_writable($config['path']);
            case 's3':
                return !empty($config['key']) && 
                       !empty($config['secret']) && 
                       !empty($config['bucket']);
            case 'ftp':
                return !empty($config['host']) && 
                       !empty($config['username']) && 
                       !empty($config['password']);
            default:
                return FALSE;
        }
    }
}