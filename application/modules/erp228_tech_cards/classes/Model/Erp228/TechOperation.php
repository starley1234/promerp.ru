<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Операция техпроцесса (erp_228_tech_operations) — один шаг изготовления.
 *
 * Операции создаются из ответа LLM (Service_Erp228_Parser) и пересоздаются
 * целиком при каждой новой версии документа: журнал версий хранит документ,
 * операции принадлежат именно ему.
 *
 * @package    Erp228
 * @category   Models
 *
 * @property int    $id
 * @property int    $document_id
 * @property int    $sort_order
 * @property string $op_name
 * @property string $op_description
 * @property int    $duration_sec
 * @property float  $cost_amount
 * @property string $params
 */
class Model_Erp228_TechOperation extends ORM
{
    protected $_table_name = 'erp_228_tech_operations';

    protected $_belongs_to = array(
        'document' => array(
            'model'       => 'Erp228_TechDocument',
            'foreign_key' => 'document_id',
        ),
    );

    public function rules()
    {
        return array(
            'document_id' => array(
                array('not_empty'),
                array('digit'),
            ),
            'op_name' => array(
                array('not_empty'),
                array('max_length', array(':value', 255)),
            ),
            'sort_order' => array(
                array('digit'),
            ),
            'duration_sec' => array(
                array('digit'),
            ),
        );
    }

    public function labels()
    {
        return array(
            'sort_order'     => 'Порядок',
            'op_name'        => 'Операция',
            'op_description' => 'Описание',
            'duration_sec'   => 'Длительность, с',
            'cost_amount'    => 'Стоимость',
            'params'         => 'Параметры',
        );
    }

    /**
     * Параметры операции (колонка jsonb) в виде массива.
     *
     * @return array
     */
    public function decode_params()
    {
        $params = $this->params;

        if (is_array($params))
        {
            return $params;
        }

        $decoded = json_decode((string) $params, TRUE);

        return is_array($decoded) ? $decoded : array();
    }

    /**
     * Параметры одной строкой для интерфейса: «ключ: значение».
     *
     * @return string
     */
    public function params_inline()
    {
        $parts = array();

        foreach ($this->decode_params() as $key => $value)
        {
            if (is_array($value))
            {
                $value = implode(', ', $value);
            }

            $parts[] = $key.': '.$value;
        }

        return implode(' · ', $parts);
    }
}
