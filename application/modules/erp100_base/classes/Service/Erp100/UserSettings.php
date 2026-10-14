<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Личные настройки пользователя (ядро, erp100_base).
 *
 * Таблица `users_settings` хранит по одной строке на пользователя:
 * `settings_json` (jsonb) — набор «ключ → строка». Так новая настройка
 * (режим интерфейса, плотность, домашняя страница) не требует миграции.
 *
 * ORM-модель `Model_Erp100_User_Setting` читает список колонок запросом, который есть только в MySQL, и
 * на PostgreSQL не работает, поэтому здесь — прямые запросы.
 *
 * @package    Erp100
 * @category   Services
 */
class Service_Erp100_UserSettings
{
    /** @var Service_Erp100_UserSettings */
    protected static $_instance;

    /**
     * @return  Service_Erp100_UserSettings
     */
    public static function instance()
    {
        return self::$_instance ?: (self::$_instance = new self);
    }

    protected function __construct() {}

    /**
     * Значение настройки.
     *
     * @param   integer  $user_id
     * @param   string   $key
     * @param   mixed    $default  что вернуть, если настройки нет
     * @return  mixed
     */
    public function get($user_id, $key, $default = NULL)
    {
        $row = DB::query(Database::SELECT,
            'SELECT settings_json ->> :key AS value FROM users_settings WHERE user_id = :id')
            ->param(':key', (string) $key)
            ->param(':id', (int) $user_id)
            ->execute()
            ->current();

        return ($row AND $row['value'] !== NULL) ? $row['value'] : $default;
    }

    /**
     * Записать настройку, не трогая остальные.
     *
     * @param   integer  $user_id
     * @param   string   $key
     * @param   string   $value
     * @return  void
     */
    public function set($user_id, $key, $value)
    {
        DB::query(Database::INSERT,
            "INSERT INTO users_settings (user_id, settings_json, updated_at)
             VALUES (:id, jsonb_build_object(CAST(:key AS text), CAST(:value AS text)), CURRENT_TIMESTAMP)
             ON CONFLICT (user_id) DO UPDATE SET
                 settings_json = COALESCE(users_settings.settings_json, CAST('{}' AS jsonb)) || EXCLUDED.settings_json,
                 updated_at    = CURRENT_TIMESTAMP")
            ->param(':id', (int) $user_id)
            ->param(':key', (string) $key)
            ->param(':value', (string) $value)
            ->execute();
    }
}
