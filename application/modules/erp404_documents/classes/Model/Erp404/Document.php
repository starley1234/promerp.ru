<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Выпущенный документ (erp404_documents).
 *
 * Документ проходит короткий путь: `draft` (шапка заполнена, печатать нельзя) →
 * `issued` (данные собраны, снимок HTML зафиксирован) → `printed` (хотя бы раз
 * напечатан) → `archived`. Из черновика и выпущенного можно отказаться
 * (`cancelled`) — это не удаление: номер остаётся занятым, документ виден в
 * журнале с пометкой.
 *
 * Почему печать не «просто страница»: выпуск фиксирует данные и снимок, а печать
 * пишет строку в журнал (`erp_404_prints`). Благодаря этому видно, сколько раз
 * бумага выходила из принтера и по какой версии шаблона, и вчерашнюю накладную
 * нельзя подменить сегодняшней правкой шаблона.
 *
 * @package    Erp404
 * @category   Models
 */
class Model_Erp404_Document extends ORM
{
    protected $_table_name = 'erp_404_documents';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_has_many = array(
        'prints' => array('model' => 'Erp404_Print', 'foreign_key' => 'document_id'),
    );

    /** @var array Состояния документа */
    public static $statuses = array(
        'draft'     => 'Черновик',
        'issued'    => 'Выпущен',
        'printed'   => 'Напечатан',
        'archived'  => 'В архиве',
        'cancelled' => 'Отменён',
    );

    /** @var array Цветовая маркировка состояния */
    public static $status_classes = array(
        'draft'     => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        'issued'    => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
        'printed'   => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        'archived'  => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
        'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    );

    /**
     * Подпись состояния.
     *
     * @return  string
     */
    public function status_label()
    {
        return isset(self::$statuses[$this->status]) ? self::$statuses[$this->status] : (string) $this->status;
    }

    /**
     * Классы для плашки состояния.
     *
     * @return  string
     */
    public function status_class()
    {
        return isset(self::$status_classes[$this->status]) ? self::$status_classes[$this->status] : '';
    }

    /**
     * Шапку документа можно править только в черновике.
     *
     * @return  bool
     */
    public function is_editable()
    {
        return $this->status === 'draft';
    }

    /**
     * Выпущенный документ можно печатать (в том числе повторно).
     *
     * @return  bool
     */
    public function is_printable()
    {
        return in_array($this->status, array('issued', 'printed'), TRUE) AND (string) $this->rendered !== '';
    }

    /**
     * Документ можно сдать в архив: напечатан или выпущен и больше не нужен.
     *
     * @return  bool
     */
    public function is_archivable()
    {
        return in_array($this->status, array('issued', 'printed'), TRUE);
    }

    /**
     * Отказаться от документа можно, пока он не в архиве.
     *
     * @return  bool
     */
    public function is_cancellable()
    {
        return in_array($this->status, array('draft', 'issued', 'printed'), TRUE);
    }

    /**
     * Данные подстановки (payload), разобранные из JSONB.
     *
     * @return  array
     */
    public function payload_array()
    {
        $payload = $this->payload;

        if (is_array($payload))
        {
            return $payload;
        }

        if ( ! is_string($payload) OR trim($payload) === '')
        {
            return array();
        }

        $decoded = json_decode($payload, TRUE);

        return is_array($decoded) ? $decoded : array();
    }

    /**
     * Строка для поиска — собирается сервисом при сохранении.
     *
     * @return  string
     */
    public function search_text()
    {
        return trim((string) $this->number.' '.(string) $this->title.' '.(string) $this->template_code);
    }

    /**
     * Новая запись получает uuid до вставки.
     *
     * Колонку `uuid` заполняет и база (`gen_random_uuid()`), но выданное
     * значение модель не знает до перечитывания строки — а оно нужно тут же:
     * в журнале действий (`erp119`), событиях и ответе JSON API. Пустой uuid в
     * этих местах означает «ссылку на объект потеряли»: запись журнала
     * оказывается ни к чему не привязанной. Поэтому идентификатор выдаётся
     * помощником `Erp::uuid()` до сохранения, а серверный default остаётся
     * подстраховкой для вставок в обход ORM.
     *
     * @param   Validation|null $validation
     * @return  ORM
     */
    public function save(?Validation $validation = NULL)
    {
        if ( ! $this->loaded() AND (string) $this->uuid === '')
        {
            $this->uuid = Erp::uuid();
        }

        return parent::save($validation);
    }

}
