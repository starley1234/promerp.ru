<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Модель временной ссылки на файл (erp_212_file_links)
 * 
 * Управляет генерацией, валидацией и отслеживанием временных токенов доступа к файлам.
 * Ссылки имеют ограничение по времени (expires_at) и счетчик использований (click_count).
 * 
 * @property int $id ID записи
 * @property int $file_id ID связанного файла (внешний ключ к erp_212_files.id)
 * @property string $token Уникальный токен для доступа (длина до 128 символов)
 * @property string $expires_at Дата и время истечения срока действия ссылки (DATETIME)
 * @property string $created_at Дата создания ссылки (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)
 * @property int $click_count Количество использований ссылки (по умолчанию 0)
 * @property Model_Erp212_File $file Связанная модель файла (belongs_to)
 */
class Model_Erp212_Link extends ORM {
    
    /**
     * Имя таблицы в базе данных с префиксом модуля
     * @var string
     */
    protected $_table_name = 'erp_212_file_links';
    
    /**
     * Связь "принадлежит" (belongs_to) с моделью файла
     * Каждая временная ссылка привязана к одному конкретному файлу
     * 
     * @var array
     */
    protected $_belongs_to = array(
        'file' => array(
            'model'       => 'Erp212_File',
            'foreign_key' => 'file_id',
        ),
    );
    
    /**
     * Отключаем автоматическое обновление поля updated_at,
     * так как в таблице erp_212_file_links нет такого поля
     * 
     * @var string|null
     */
    protected $_updated_column = NULL;
    
    /**
     * Правила валидации для модели
     * 
     * @return array
     */
    public function rules() {
        return array(
            'token' => array(
                array('not_empty'),
                array('min_length', array(':value', 32)),
                array('max_length', array(':value', 128)),
                array(array($this, 'unique'), array('token', ':value')),
            ),
            'expires_at' => array(
                array('not_empty'),
                array('date'),
            ),
            'file_id' => array(
                array('not_empty'),
                array('digit'),
                array('min', array(':value', 1)),
            ),
        );
    }
    
    /**
     * Метод для генерации уникального токена
     * Использует комбинацию случайных байтов и временной метки
     * 
     * @param int $length Длина токена (по умолчанию 64 символа)
     * @return string Уникальный токен
     */
    public static function generate_token($length = 64) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Создание новой временной ссылки для файла
     * 
     * @param int $file_id ID файла
     * @param int $ttl Время жизни ссылки в секундах (по умолчанию 3600 = 1 час)
     * @return Model_Erp212_Link|bool Созданная модель или FALSE при ошибке
     */
    public static function create_for_file($file_id, $ttl = 3600) {
        $link = ORM::factory('Erp212_Link');
        $link->file_id = (int)$file_id;
        $link->token = self::generate_token();
        $link->expires_at = date('Y-m-d H:i:s', time() + $ttl);
        $link->click_count = 0;
        
        if ($link->save()) {
            return $link;
        }
        
        return FALSE;
    }
    
    /**
     * Проверка валидности токена
     * 
     * @param string $token Токен для проверки
     * @return Model_Erp212_Link|bool Модель ссылки если токен валиден, иначе FALSE
     */
    public static function validate_token($token) {
        $link = ORM::factory('Erp212_Link')
            ->where('token', '=', $token)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->find();
            
        if ($link->loaded()) {
            // Увеличиваем счетчик использований
            $link->click_count = $link->click_count + 1;
            $link->save();
            return $link;
        }
        
        return FALSE;
    }
    
    /**
     * Переопределение метода delete для каскадного удаления
     * (хотя в БД уже настроен ON DELETE CASCADE)
     * 
     * @return bool
     */
    public function delete() {
        return parent::delete();
    }
    
    /**
     * Проверка, истек ли срок действия ссылки
     * 
     * @return bool TRUE если срок истек, FALSE если еще действительна
     */
    public function is_expired() {
        return strtotime($this->expires_at) <= time();
    }
    
    /**
     * Получение оставшегося времени жизни ссылки в секундах
     * 
     * @return int Оставшееся время в секундах (0 если истекла)
     */
    public function get_remaining_ttl() {
        $expires = strtotime($this->expires_at);
        $now = time();
        return max(0, $expires - $now);
    }
}