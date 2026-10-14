<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Группы подключения к PostgreSQL.
 *
 * Значения берутся из переменных окружения (.env в корне проекта, см. .env.example).
 * Новые по-групповые переменные имеют приоритет, но legacy-имена `DB_*` продолжают
 * работать: так уже настроенная установка открывает /migrate_pg до обновления .env.
 *
 *   DB_DEFAULT_{HOST,PORT,NAME,USER,PASS,SCHEMA} — основная база ERP (группа default),
 *                                                 legacy-имена: DB_{HOST,PORT,NAME,USER,PASS,SCHEMA};
 *   DB_MDM_{...}                                 — база MDM/онтологии (группа mdm, модуль erp230_mdm);
 *   DB_MEMORY_{...}                              — резервная группа postgresql_memory;
 *   DB_ONTOLOGY_{...}                            — резервная группа ontology.
 *
 * Группы mdm и default обязаны существовать: erp230_mdm создаёт свои таблицы в
 * группе mdm (в основной базе таблица users конфликтует со схемой MDM).
 * Базы создаются автоматически: bash bin/restore-env.sh --pg-only
 *
 * Это шаблон: setup.sh копирует его в application/config/database.php, если файла нет
 * (bash bin/setup.sh --force перезаписывает). Секреты окружения лежат в .env, он в git не попадает.
 */

$env = static function (array $keys, $default = NULL) {
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value !== FALSE AND $value !== '') return $value;
    }
    return $default;
};

$group = static function ($prefix, $fallback_name, array $fallback_prefixes = array()) use ($env) {
    $keys = static function ($suffix) use ($prefix, $fallback_prefixes) {
        $result = array($prefix.'_'.$suffix);
        foreach ($fallback_prefixes as $fallback) $result[] = $fallback.'_'.$suffix;
        return $result;
    };
    return array(
        'type' => 'PostgreSQL',
        'connection' => array(
            'hostname' => $env($keys('HOST'), '127.0.0.1'),
            'port' => $env($keys('PORT'), '5432'),
            'database' => $env($keys('NAME'), $fallback_name),
            'username' => $env($keys('USER'), 'promerp'),
            'password' => $env($keys('PASS'), 'promerp'),
            'persistent' => FALSE,
        ),
        'primary_key' => NULL,
        'schema' => $env($keys('SCHEMA'), 'public'),
        'table_prefix' => '', 'charset' => 'utf8', 'caching' => FALSE,
    );
};

$main = $env(array('DB_DEFAULT_NAME','DB_NAME'), 'promerp');
$default = $group('DB_DEFAULT', $main, array('DB'));
$mdm = $group('DB_MDM', $main.'_mdm', array('DB'));
// DB_NAME — это основная база; ключ DB_MDM_NAME задаёт MDM явно.
$mdm['connection']['database'] = $env(array('DB_MDM_NAME'), $main.'_mdm');
$memory = $group('DB_MEMORY', $main, array('DB_DEFAULT','DB'));
$memory['connection']['database'] = $env(array('DB_MEMORY_NAME','DB_DEFAULT_NAME','DB_NAME'), $main);
$ontology = $group('DB_ONTOLOGY', $mdm['connection']['database'], array('DB_MDM','DB'));
$ontology['connection']['database'] = $env(array('DB_ONTOLOGY_NAME','DB_MDM_NAME'), $mdm['connection']['database']);

return array(
    'default' => $default,
    // MDM остаётся отдельной базой bounded-context по умолчанию.
    'mdm' => $mdm,
    'postgresql_memory' => $memory,
    'ontology' => $ontology,
);
