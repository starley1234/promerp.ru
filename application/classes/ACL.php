<?php defined('SYSPATH') or die('No direct script access.');

class ACL {

    /**
     * Проверяет, есть ли у ролей пользователя указанное разрешение
     */
    public static function check(array $user_roles, $permission)
    {
        $config = Kohana::$config->load('acl');
        
        // 1. Прямое совпадение (например, 'client.delete')
        if (self::_verify($user_roles, $permission, $config)) {
            return TRUE;
        }

        // 2. Проверка по маске модуля (например, 'client.*')
        $parts = explode('.', $permission);
        if (count($parts) > 1) {
            $wildcard = $parts[0] . '.*';
            if (self::_verify($user_roles, $wildcard, $config)) {
                return TRUE;
            }
        }

        return FALSE;
    }

    private static function _verify($user_roles, $key, $config)
    {
        $allowed_roles = $config->get($key);
        if (is_array($allowed_roles)) {
            // Если хотя бы одна роль пользователя совпадает с разрешенными
            return (bool) array_intersect($user_roles, $allowed_roles);
        }
        return FALSE;
    }
    
            /**
     * Возвращает список всех публичных методов данного класса
     * @return array
     */
    public static function get_methods()
    {
        $class = new ReflectionClass(__CLASS__);
        $methods = $class->getMethods(ReflectionMethod::IS_PUBLIC);

        $result = [];
        foreach ($methods as $method) {
            $result[] = $method->name;
        }

        return $result;
    }
    
}