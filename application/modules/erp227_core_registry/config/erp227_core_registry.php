<?php 
return array(
    /**
     * Настройки векторного поиска (pgvector)
     */
    'vector_search' => array(
        'enabled'    => TRUE,
        'model_name' => 'text-embedding-qwen3-embedding-0.6b',
    ),

    /**
     * Настройки полнотекстового поиска
     */
    'fulltext_search' => array(
        'language' => 'russian',
    ),

    /**
     * Настройки валидации и схем
     */
    'validation' => array(
        'strict_mode' => FALSE,
    ),
);