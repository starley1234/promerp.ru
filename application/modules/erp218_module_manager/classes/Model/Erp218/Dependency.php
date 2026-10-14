<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель для управления связями и зависимостями между модулями ERP системы.
 *
 * @property int        $id                 Идентификатор связи
 * @property int        $module_id          ID основного модуля (который зависит)
 * @property int        $required_module_id ID модуля, от которого зависит основной
 * @property string     $min_version        Минимально требуемая версия зависимого модуля (SemVer)
 * @property int        $created_by         ID пользователя, создавшего запись
 * @property string     $created_at         Дата и время создания
 * @property string     $updated_at         Дата и время последнего обновления
 * @property int        $is_deleted         Флаг мягкого удаления (0 - активен, 1 - удален)
 *
 * @property Model_Erp218_Module $module    Основной модуль (владелец зависимости)
 * @property Model_Erp218_Module $required  Требуемый модуль (цель зависимости)
 *
 * @package    ERP
 * @category   Model
 * @author     Kohana Programmer
 */
class Model_Erp218_Dependency extends ORM {

    protected $_table_name = 'erp_218_dependencies';

    protected $_belongs_to = array(
        'module' => array(
            'model'       => 'Erp218_Module',
            'foreign_key' => 'module_id',
        ),
        'required' => array(
            'model'       => 'Erp218_Module',
            'foreign_key' => 'required_module_id',
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
            'required_module_id' => array(
                array('not_empty'),
                array('digit'),
            ),
            'min_version' => array(
                array('max_length', array(':value', 50)),
                array('regex', array(':value', '/^(\d+\.\d+\.\d+)?$/')), // Базовая проверка SemVer
            ),
            'created_by' => array(
                array('not_empty'),
                array('digit'),
            ),
            'is_deleted' => array(
                array('range', array(':value', 0, 1)),
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
            'id'                 => 'ID записи',
            'module_id'          => 'Основной модуль',
            'required_module_id' => 'Зависит от модуля',
            'min_version'        => 'Минимальная версия',
            'created_by'         => 'Автор записи',
            'created_at'         => 'Дата создания',
            'updated_at'         => 'Дата обновления',
            'is_deleted'         => 'Удалено',
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
            'min_version' => array(
                array('trim'),
                array('strip_tags'),
            ),
        );
    }
}