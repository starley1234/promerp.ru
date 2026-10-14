<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Конфигурация модуля MDM (Master Data Management)
 * Единый реестр эталонных данных
 */
return array(
    /**
     * Настройка весов доверия источников (Trust Levels)
     * Используется при формировании Golden Record (Золотой записи).
     * Значение от 0 до 100. Чем выше, тем приоритетнее данные источника.
     */
    'trust_levels' => array(
        'plm'    => 90, // Инженерные данные (Teamcenter/Windchill) - наивысший приоритет
        'erp'    => 70, // Учетные данные (1C/SAP)
        'manual' => 50, // Ручной ввод оператором
        'ai'     => 30, // Автоматическое распознавание/сопоставление
        'legacy' => 10, // Устаревшие мигрированные системы
    ),

    /**
     * Параметры векторных моделей (pg_vector)
     * Для семантического поиска по изделиям и компонентам.
     */
    'vector_search' => array(
        'model_name'     => 'text-embedding-3-small', // Идентификатор модели
        'dimensions'     => 1024,                    // Размерность вектора (согласно схеме БД)
        'distance_type'  => 'cosine',                // Тип метрики: cosine, euclidean, inner_product
        'index_type'     => 'hnsw',                  // Тип индекса: hnsw или ivfflat
        'ef_search'      => 64,                      // Параметр точности поиска HNSW
        'min_confidence' => 0.75,                    // Порог схожести для выдачи результатов
    ),

    /**
     * Лимиты и параметры партиционирования (Partitioning)
     * Настройки для автоматического управления секциями таблиц.
     */
    'partitioning' => array(
        'enabled'         => TRUE,
        'strategy'        => 'range_year',           // Секционирование по годам (created_at)
        'tables'          => array(
            'objects',
            'object_properties',
            'baselines'
        ),
        'precreate_years' => 1,                      // На сколько лет вперед создавать секции
        'default_suffix'  => 'default',              // Суффикс для секции по умолчанию
    ),

    /**
     * Настройки арбитража (Data Stewardship)
     */
    'stewardship' => array(
        'auto_resolve_threshold' => 0.95,            // Порог уверенности для авто-принятия изменений
        'require_manual_on_conflict' => TRUE,        // Требовать вмешательства при равном trust score
        'lock_timeout' => 3600,                      // Время блокировки записи при ручном редактировании (сек)
    ),

    /**
     * Настройки целостности (Blockchain-like chain)
     */
    'integrity' => array(
        'hash_algorithm' => 'sha256',                // Алгоритм для snapshot_hash
        'genesis_block'  => 'GENESIS_BLOCK_MDM_V5',  // Соль для первого блока в цепочке
    ),
);