<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Качество (erp405_quality): стартовый справочник кодов дефектов.
 *
 * Заводим только то, без чего модуль нельзя начать пользовать: 12 кодов
 * дефектов по категориям (размер, поверхность, материал, сборка, документы,
 * упаковка). Это данные, а не демонстрация: коды в справочнике — рабочий
 * инструмент контролёра, и выдуманные документы контроля здесь не создаются.
 *
 * Миграция идемпотентна: `ON CONFLICT (code) DO NOTHING` — повторный прогон
 * не задваивает и не перезаписывает правки пользователя.
 *
 * @package    Erp405
 * @category   Migrations
 */
class pgMigration_002_Erp405_Quality_Seed extends pgMigration
{
    /** @var array Код => [название, категория, важность 1..3] */
    protected $_codes = array(
        'SIZE-OUT'     => array('Отклонение размера', 'size', 2),
        'SIZE-GEOM'    => array('Нарушение геометрии', 'size', 2),
        'SURF-SCRATCH' => array('Царапина', 'surface', 3),
        'SURF-RUST'    => array('Коррозия', 'surface', 2),
        'SURF-PAINT'   => array('Дефект покрытия', 'surface', 3),
        'MAT-GRADE'    => array('Не та марка материала', 'material', 1),
        'MAT-QUALITY'  => array('Качество материала', 'material', 1),
        'ASM-GAP'      => array('Зазор при сборке', 'assembly', 2),
        'ASM-FASTEN'   => array('Незатянутый крепёж', 'assembly', 1),
        'DOC-MISS'     => array('Нет документа качества', 'document', 3),
        'PKG-DAMAGE'   => array('Повреждение упаковки', 'packaging', 3),
        'OTHER'        => array('Прочее', 'other', 3),
    );

    /**
     * @return  void
     */
    public function up()
    {
        foreach ($this->_codes as $code => $data)
        {
            list($name, $category, $severity) = $data;

            $this->run_query("
                INSERT INTO erp_405_defect_codes (code, name, category, severity, search_text, is_active)
                VALUES (
                    '".$this->_escape($code)."',
                    '".$this->_escape($name)."',
                    '".$this->_escape($category)."',
                    ".(int) $severity.",
                    '".$this->_escape(mb_strtolower($code.' '.$name))."',
                    1
                )
                ON CONFLICT (code) DO NOTHING
            ");
        }
    }

    /**
     * @return  void
     */
    public function down()
    {
        $codes = array();

        foreach (array_keys($this->_codes) as $code)
        {
            $codes[] = "'".$this->_escape($code)."'";
        }

        $this->run_query('DELETE FROM erp_405_defect_codes WHERE code IN ('.implode(', ', $codes).')');
    }

    /**
     * @param   string $value
     * @return  string
     */
    protected function _escape($value)
    {
        return str_replace("'", "''", (string) $value);
    }
}
