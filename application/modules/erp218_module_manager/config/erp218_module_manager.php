<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Конфигурация модуля ERP218 "Module Manager (Registry)"
 * ID: 218
 * Slug: module_manager
 */
return array(
    /**
     * Настройки сканирования директорий
     */
    'scanner' => array(
        // Базовый путь для поиска модулей (обычно MODPATH)
        'path' => APPPATH . 'modules',
        
        // Файлы, из которых извлекаются метаданные модуля
        'metadata_files' => array(
            'README.md',
            'composer.json',
            'module.json',
            'init.php',
        ),
        
        // Глубина рекурсии при поиске (1 — только папки в корне modules)
        'max_depth' => 1,
    ),

    /**
     * Список исключаемых директорий (черный список)
     * Эти папки будут игнорироваться сканером.
     */
    'excluded_directories' => array(
        '.git',
        '.svn',
        'cache',
        'logs',
        'vendor',
        'unittest',
        'userguide',
    ),
    
    /**
     * Настройки жизненного цикла (статусы)
     */
    'statuses' => array(
        'alpha'    => array('label' => 'Alpha', 'color' => '#6c757d'),
        'beta'     => array('label' => 'Beta', 'color' => '#17a2b8'),
        'stable'   => array('label' => 'Stable', 'color' => '#28a745'),
        'archived' => array('label' => 'Archived', 'color' => '#dc3545'),
    ),
);