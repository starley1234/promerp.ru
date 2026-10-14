<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Документ техпроцесса (erp_228_tech_documents) — результат генерации.
 *
 * Актуальная версия у карты всегда одна: это гарантирует частичный уникальный
 * индекс erp_228_tech_documents_actual_uniq (tech_card_id WHERE is_actual = 1).
 * Предыдущие версии остаются в истории (is_actual = 0).
 *
 * @package    Erp228
 * @category   Models
 *
 * @property int    $id
 * @property int    $tech_card_id
 * @property int    $is_actual
 * @property string $content_markdown
 * @property string $structured_data
 * @property string $llm_model
 * @property int    $tokens_total
 * @property int    $gen_time_ms
 * @property string $created_at
 */
class Model_Erp228_TechDocument extends ORM
{
    protected $_table_name = 'erp_228_tech_documents';

    protected $_belongs_to = array(
        'card' => array(
            'model'       => 'Erp228_TechCard',
            'foreign_key' => 'tech_card_id',
        ),
    );

    protected $_has_many = array(
        'operations' => array(
            'model'       => 'Erp228_TechOperation',
            'foreign_key' => 'document_id',
        ),
    );

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');

    public function rules()
    {
        return array(
            'tech_card_id' => array(
                array('not_empty'),
                array('digit'),
            ),
            'is_actual' => array(
                array('in_array', array(':value', array(0, 1, '0', '1'))),
            ),
        );
    }

    public function labels()
    {
        return array(
            'tech_card_id'     => 'Технологическая карта',
            'is_actual'        => 'Актуальная версия',
            'content_markdown' => 'Описание техпроцесса',
            'structured_data'  => 'Структурированные данные',
            'llm_model'        => 'Модель',
            'tokens_total'     => 'Токены',
            'gen_time_ms'      => 'Время генерации',
        );
    }

    /**
     * Структурированные данные документа (колонка jsonb) в виде массива.
     *
     * @return array
     */
    public function decode_structured()
    {
        $data = $this->structured_data;

        if (is_array($data))
        {
            return $data;
        }

        $decoded = json_decode((string) $data, TRUE);

        return is_array($decoded) ? $decoded : array();
    }

    /**
     * Операции документа по порядку выполнения.
     *
     * @return ORM|Model_Erp228_TechOperation[]
     */
    public function operations_ordered()
    {
        return $this->operations
            ->order_by('sort_order', 'ASC')
            ->find_all();
    }

    /**
     * Суммарная длительность операций, секунды.
     *
     * @return int
     */
    public function duration_total()
    {
        $row = DB::select(array(DB::expr('COALESCE(SUM(duration_sec), 0)'), 'total'))
            ->from('erp_228_tech_operations')
            ->where('document_id', '=', $this->id)
            ->execute()
            ->current();

        return (int) $row['total'];
    }

    /**
     * Суммарная стоимость операций.
     *
     * @return float
     */
    public function cost_total()
    {
        $row = DB::select(array(DB::expr('COALESCE(SUM(cost_amount), 0)'), 'total'))
            ->from('erp_228_tech_operations')
            ->where('document_id', '=', $this->id)
            ->execute()
            ->current();

        return (float) $row['total'];
    }

    /**
     * Человекочитаемая длительность (ч мин).
     *
     * @param  int $seconds
     * @return string
     */
    public static function human_duration($seconds)
    {
        $seconds = (int) $seconds;

        if ($seconds < 60)
        {
            return $seconds.' с';
        }

        $minutes = floor($seconds / 60);
        $hours   = floor($minutes / 60);

        if ($hours < 1)
        {
            return $minutes.' мин';
        }

        return $hours.' ч '.($minutes % 60).' мин';
    }
}
