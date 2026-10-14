<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Запись журнала печати (erp404_documents).
 *
 * Печать — событие, а не поле документа: по журналу видно, кто и когда выводил
 * бумагу и сколько копий ушло. Поле `print_count` в документе — быстрый счётчик
 * для списков; истина живёт здесь.
 *
 * @package    Erp404
 * @category   Models
 */
class Model_Erp404_Print extends ORM
{
    protected $_table_name = 'erp_404_prints';

    protected $_created_column = array('column' => 'printed_at', 'format' => 'Y-m-d H:i:s');

    protected $_belongs_to = array(
        'document' => array('model' => 'Erp404_Document', 'foreign_key' => 'document_id'),
    );

    /**
     * Сколько копий ушло в этой записи.
     *
     * @return  int
     */
    public function copies()
    {
        return max(1, (int) $this->copies);
    }

    /**
     * Кто печатал: имя учётной записи или пустая строка.
     *
     * Печать бывает и без автора (CLI, внешний воркер) — тогда «система», а не
     * исключение: журнал должен читаться и в этом случае.
     *
     * @return  string
     */
    public function printed_by_name()
    {
        $id = (int) $this->printed_by;

        if ($id <= 0)
        {
            return '';
        }

        try
        {
            $user = ORM::factory('User', $id);

            return $user->loaded() ? (string) $user->username : '';
        }
        catch (Exception $e)
        {
            return '';
        }
    }
}
