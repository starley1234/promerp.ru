<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Модель управления правами доступа пользователей к конкретным файлам в хранилище.
 * 
 * Отвечает за точечный контроль доступа (ACL) к файлам, позволяя настраивать
 * индивидуальные разрешения для каждого пользователя на каждый файл.
 * 
 * @property int $id ID записи о разрешении
 * @property int $file_id ID файла (внешний ключ к erp_212_files.id)
 * @property int $user_id ID пользователя (внешний ключ к users.id)
 * @property bool $can_read Разрешение на чтение файла
 * @property bool $can_write Разрешение на запись/изменение файла
 * @property bool $can_delete Разрешение на удаление файла
 * @property Model_Erp212_File $file Связанный файл (ORM relation)
 * @property Model_User $user Связанный пользователь (ORM relation)
 * 
 * @property-read DateTime $created_at Дата создания записи (автоматически)
 * @property-read DateTime $updated_at Дата последнего обновления (автоматически)
 */
class Model_Erp212_Permission extends ORM {
    
    /**
     * Имя таблицы в базе данных с префиксом модуля.
     * @var string
     */
    protected $_table_name = 'erp_212_file_permissions';
    
    /**
     * Связь "многие-ко-многим" через промежуточную таблицу.
     * В данной модели используется связь "принадлежит" (belongs_to).
     * 
     * @var array
     */
    protected $_belongs_to = array(
        'file' => array(
            'model' => 'Erp212_File',
            'foreign_key' => 'file_id',
            'cascade_save' => FALSE,
            'cascade_delete' => FALSE,
        ),
        'user' => array(
            'model' => 'User',
            'foreign_key' => 'user_id',
            'cascade_save' => FALSE,
            'cascade_delete' => FALSE,
        ),
    );
    
    /**
     * Правила валидации для полей модели.
     * 
     * @return array
     */
    public function rules() {
        return array(
            'file_id' => array(
                array('not_empty'),
                array('digit'),
            ),
            'user_id' => array(
                array('not_empty'),
                array('digit'),
            ),
            'can_read' => array(
                array('in_array', array(':value', array(0, 1, TRUE, FALSE, '0', '1'))),
            ),
            'can_write' => array(
                array('in_array', array(':value', array(0, 1, TRUE, FALSE, '0', '1'))),
            ),
            'can_delete' => array(
                array('in_array', array(':value', array(0, 1, TRUE, FALSE, '0', '1'))),
            ),
        );
    }
    
    /**
     * Метки полей для форм.
     * 
     * @return array
     */
    public function labels() {
        return array(
            'file_id' => 'Файл',
            'user_id' => 'Пользователь',
            'can_read' => 'Разрешить чтение',
            'can_write' => 'Разрешить запись',
            'can_delete' => 'Разрешить удаление',
        );
    }
    
    /**
     * Фильтры для полей перед сохранением.
     * 
     * @return array
     */
    public function filters() {
        return array(
            'can_read' => array(
                array(array($this, 'cast_bool')),
            ),
            'can_write' => array(
                array(array($this, 'cast_bool')),
            ),
            'can_delete' => array(
                array(array($this, 'cast_bool')),
            ),
        );
    }

    /**
     * Вспомогательный метод для фильтрации булевых значений
     * 
     * @param mixed $value
     * @return int
     */
    public function cast_bool($value) {
        return (bool) $value ? 1 : 0;
    }
    
    /**
     * Проверяет, существует ли уже запись о разрешении для указанного файла и пользователя.
     * 
     * @param int $file_id ID файла
     * @param int $user_id ID пользователя
     * @return bool
     */
    public function exists_for($file_id, $user_id) {
        return (bool) DB::select(array('COUNT(*)', 'count'))
            ->from($this->_table_name)
            ->where('file_id', '=', $file_id)
            ->where('user_id', '=', $user_id)
            ->execute()
            ->get('count');
    }
    
    /**
     * Получает все разрешения для указанного файла.
     * 
     * @param int $file_id ID файла
     * @return ORM_Iterator
     */
    public function for_file($file_id) {
        return $this->where('file_id', '=', $file_id)->find_all();
    }
    
    /**
     * Получает все разрешения для указанного пользователя.
     * 
     * @param int $user_id ID пользователя
     * @return ORM_Iterator
     */
    public function for_user($user_id) {
        return $this->where('user_id', '=', $user_id)->find_all();
    }
    
    /**
     * Удаляет все разрешения для указанного файла (при удалении файла).
     * 
     * @param int $file_id ID файла
     * @return int Количество удаленных записей
     */
    public function delete_for_file($file_id) {
        return DB::delete($this->_table_name)
            ->where('file_id', '=', $file_id)
            ->execute();
    }
    
    /**
     * Удаляет все разрешения для указанного пользователя (при удалении пользователя).
     * 
     * @param int $user_id ID пользователя
     * @return int Количество удаленных записей
     */
    public function delete_for_user($user_id) {
        return DB::delete($this->_table_name)
            ->where('user_id', '=', $user_id)
            ->execute();
    }
    
    /**
     * Создает или обновляет разрешение для пары файл-пользователь.
     * 
     * @param int $file_id ID файла
     * @param int $user_id ID пользователя
     * @param array $permissions Массив разрешений: can_read, can_write, can_delete
     * @return Model_Erp212_Permission
     */
    public function set_permission($file_id, $user_id, array $permissions) {
        $permission = $this->where('file_id', '=', $file_id)
            ->where('user_id', '=', $user_id)
            ->find();
            
        if (!$permission->loaded()) {
            $permission = ORM::factory('Erp212_Permission');
            $permission->file_id = $file_id;
            $permission->user_id = $user_id;
        }
        
        if (isset($permissions['can_read'])) {
            $permission->can_read = $permissions['can_read'];
        }
        if (isset($permissions['can_write'])) {
            $permission->can_write = $permissions['can_write'];
        }
        if (isset($permissions['can_delete'])) {
            $permission->can_delete = $permissions['can_delete'];
        }
        
        $permission->save();
        return $permission;
    }
}