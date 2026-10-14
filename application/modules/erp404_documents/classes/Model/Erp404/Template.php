<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Шаблон печатной формы (erp404_documents).
 *
 * Шаблон — это тело документа на HTML с подстановками. Он принадлежит типу
 * объекта (`ref_type`), потому что «заказ поставщику» и «паспорт качества» —
 * разные бумаги с разными данными, и универсальный шаблон «на всё» быстро
 * превращается в свалку условий.
 *
 * Версия растёт при правке тела: выпущенный документ запоминает версию, по
 * которой его напечатали, поэтому переписанный шаблон не меняет уже отданные
 * людям бумаги.
 *
 * @package    Erp404
 * @category   Models
 */
class Model_Erp404_Template extends ORM
{
    protected $_table_name = 'erp_404_templates';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    /** @var array Виды форм */
    public static $kinds = array(
        'print' => 'Печатная форма',
        'label' => 'Этикетка',
        'act'   => 'Акт',
    );

    /**
     * Типы объектов, для которых модуль умеет собрать данные.
     *
     * Список — не «справочник», а обещание: всё, что здесь перечислено,
     * `Service_Erp404_Sources` умеет отдать, а шаблон без известного типа
     * модуль не примет (`unknown_ref`). Универсальный шаблон (пустой тип) тоже
     * допустим, но данные ему придёт передать руками.
     *
     * @return  array   тип => название
     */
    public static function ref_types()
    {
        return array(
            ''                 => 'Универсальный (данные передаются при выпуске)',
            'erp402_order'     => 'Производственный заказ (erp402)',
            'erp403_order'     => 'Заказ поставщику (erp403)',
            'erp403_receipt'   => 'Приёмка закупки (erp403)',
            'erp405_inspection'=> 'Контроль качества (erp405)',
            'erp408_operation' => 'Операция цеха (erp408)',
            'erp408_shift_day' => 'Сменное задание на день (erp408)',
        );
    }

    /**
     * Название типа объекта (в том числе для документа, шаблона рядом нет).
     *
     * @param   string  $ref_type
     * @return  string
     */
    public static function ref_type_label($ref_type)
    {
        $types = self::ref_types();

        return isset($types[$ref_type]) ? $types[$ref_type] : (string) $ref_type;
    }

    /**
     * Тип объекта, для которого предназначен шаблон.
     *
     * @return  string
     */
    public function type_label()
    {
        return self::ref_type_label($this->ref_type);
    }

    /**
     * Подпись вида формы.
     *
     * @return  string
     */
    public function kind_label()
    {
        return isset(self::$kinds[$this->kind]) ? self::$kinds[$this->kind] : (string) $this->kind;
    }

    /**
     * Шаблон выключен: новые документы по нему не выпускаются, старые читаются.
     *
     * @return  bool
     */
    public function is_active()
    {
        return Erp::bool($this->is_active);
    }

    /**
     * Список подстановок, встречающихся в теле (`ref.number`, `document.date`).
     *
     * Возвращает уникальные имена верхнего уровня с точкой пути — по нему
     * интерфейс показывает, какие данные шаблону нужны, а проверка объявлений
     * MCP понимает, что шаблон опирается на источник.
     *
     * @return  array
     */
    public function placeholders()
    {
        if ( ! preg_match_all('/\{\{\s*([\p{L}_][\p{L}\p{N}_]*(?:\.[\p{L}\p{N}_]+)+)\s*\}\}/u', (string) $this->body, $matches))
        {
            return array();
        }

        return array_values(array_unique($matches[1]));
    }

    /**
     * Имена блоков-повторов (`{{#rows ref.lines}}…{{/rows}}`).
     *
     * @return  array
     */
    public function row_blocks()
    {
        if ( ! preg_match_all('/\{\{\s*#rows\s+([\p{L}_][\p{L}\p{N}_.]*)\s*\}\}/iu', (string) $this->body, $matches))
        {
            return array();
        }

        return array_values(array_unique($matches[1]));
    }

    /**
     * Строка для поиска — собирается сервисом при сохранении.
     *
     * @return  string
     */
    public function search_text()
    {
        return trim((string) $this->code.' '.(string) $this->name.' '.$this->type_label());
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
