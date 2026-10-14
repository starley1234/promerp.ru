<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель для хранения результатов LLM анализа модулей.
 * 
 * @package    ERP
 * @category   Model
 * @author     Kohana Programmer
 * 
 * @property int        $id              Идентификатор записи
 * @property int        $module_id       ID модуля, к которому относится анализ
 * @property string     $raw_content     Исходный текст из README или файлов, отправленный на анализ
 * @property string     $ai_summary      Сгенерированное нейросетью резюме
 * @property string     $analysis_status Статус анализа (pending, completed, failed)
 * @property string     $last_scan_at    Дата и время последнего сканирования
 * @property int        $created_by      ID пользователя, создавшего запись
 * @property string     $created_at      Дата и время создания
 * @property string     $updated_at      Дата и время последнего обновления
 * @property int        $is_deleted      Флаг мягкого удаления (0/1)
 * 
 * @property Model_Erp218_Module $module Модуль, к которому относится анализ
 */
class Model_Erp218_Llm extends ORM {

    protected $_table_name = 'erp_218_llm_analysis';

    protected $_belongs_to = array(
        'module' => array(
            'model'       => 'Erp218_Module',
            'foreign_key' => 'module_id',
        ),
    );

    protected $_created_column = array(
        'column' => 'created_at',
        'format' => 'Y-m-d H:i:s',
    );

    protected $_updated_column = array(
        'column' => 'updated_at',
        'format' => 'Y-m-d H:i:s',
    );

    /**
     * Правила валидации для полей модели
     * 
     * @return array
     */
    public function rules()
    {
        return array(
            'module_id' => array(
                array('not_empty'),
                array('digit'),
            ),
            'analysis_status' => array(
                array('in_array', array(':value', array('pending', 'completed', 'failed'))),
            ),
            'created_by' => array(
                array('not_empty'),
                array('digit'),
            ),
        );
    }

    /**
     * Человекопонятные метки полей
     * 
     * @return array
     */
    public function labels()
    {
        return array(
            'id'              => 'ID',
            'module_id'       => 'ID Модуля',
            'raw_content'     => 'Исходный контент',
            'ai_summary'      => 'Резюме AI',
            'analysis_status' => 'Статус анализа',
            'last_scan_at'    => 'Дата последнего сканирования',
            'created_by'      => 'Автор',
            'created_at'      => 'Дата создания',
            'updated_at'      => 'Дата обновления',
        );
    }

    /**
     * Фильтры очистки данных
     * 
     * @return array
     */
    public function filters()
    {
        return array(
            'raw_content' => array(
                array('trim'),
            ),
            'ai_summary' => array(
                array('trim'),
            ),
        );
    }

    /**
     * Находит запись анализа по module_id или создает новую, если не найдена.
     *
     * @param int $module_id
     * @return Model_Erp218_Llm
     */
    public static function find_or_create_by_module_id($module_id)
    {
        $analysis = ORM::factory('Erp218_Llm')
            ->where('module_id', '=', $module_id)
            ->find();

        if ( ! $analysis->loaded())
        {
            $analysis->module_id = $module_id;
        }
        return $analysis;
    }

    /**
     * Находит запись анализа по module_id.
     *
     * @param int $module_id
     * @return Model_Erp218_Llm
     */
    public static function find_by_module_id($module_id)
    {
        return ORM::factory('Erp218_Llm')
            ->where('module_id', '=', $module_id)
            ->find();
    }
}