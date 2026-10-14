<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Настройки (пороги) витрин аналитики (erp801_analytics).
 *
 * Пороги — это то, что превращает число в решение: «покрытие 12 дней» само по
 * себе не плохо и не хорошо, а «12 дней при цели 30» — уже повод заказывать.
 * Поэтому пороги лежат в базе и одни на всю систему: директор и начальник цеха
 * смотрят на одни и те же границы.
 *
 * Диапазоны объявлены здесь, а не в форме: порог, который нельзя ввести руками,
 * так же нельзя ввести и через API. Неверное значение — не «приведём к
 * разумному», а честная ошибка `validation`: молчаливая правка настроек
 * аналитики меняет решения, а не только цифры на экране.
 *
 * @package    Erp801
 * @category   Services
 */
class Service_Erp801_Settings
{
    /** @var Service_Erp801_Settings|null */
    protected static $_instance;

    /** @var array|null Разобранные значения на время запроса */
    protected $_values;

    /**
     * @return  Service_Erp801_Settings
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    /**
     * Описание настроек: подпись, единица, значение по умолчанию и границы.
     *
     * @return  array
     */
    public function definitions()
    {
        return array(
            'cover_target_days' => array(
                'label' => 'Целевое покрытие запасов',
                'unit'  => 'дней',
                'value' => 30,
                'min'   => 1,
                'max'   => 365,
                'note'  => 'Ниже цели позиция попадает в «хватит ненадолго» на витрине запасов',
            ),
            'dead_stock_days' => array(
                'label' => 'Порог мёртвого запаса',
                'unit'  => 'дней без расхода',
                'value' => 90,
                'min'   => 7,
                'max'   => 1095,
                'note'  => 'Позиция с остатком и без расхода дольше срока — деньги, лежащие на полке',
            ),
            'abc_a_share' => array(
                'label' => 'Граница класса A',
                'unit'  => '% расхода',
                'value' => 80,
                'min'   => 10,
                'max'   => 95,
                'note'  => 'Накопительная доля расхода, до которой позиция считается классом A',
            ),
            'abc_b_share' => array(
                'label' => 'Граница класса B',
                'unit'  => '% расхода',
                'value' => 95,
                'min'   => 20,
                'max'   => 99,
                'note'  => 'До этой доли — класс B, дальше — класс C',
            ),
            'xyz_x_cv' => array(
                'label' => 'Граница класса X',
                'unit'  => '% вариации',
                'value' => 25,
                'min'   => 5,
                'max'   => 100,
                'note'  => 'Разброс месячного расхода ниже границы — ровное потребление',
            ),
            'xyz_y_cv' => array(
                'label' => 'Граница класса Y',
                'unit'  => '% вариации',
                'value' => 50,
                'min'   => 10,
                'max'   => 200,
                'note'  => 'Выше границы — класс Z: потребление непредсказуемо',
            ),
            'oee_target' => array(
                'label' => 'Цель OEE',
                'unit'  => '%',
                'value' => 65,
                'min'   => 10,
                'max'   => 100,
                'note'  => 'Ниже цели участок подсвечивается как требующий разбора',
            ),
            'defect_share_target' => array(
                'label' => 'Допустимая доля забракованных контролей',
                'unit'  => '%',
                'value' => 5,
                'min'   => 0,
                'max'   => 50,
                'note'  => 'Больше — качество уходит в красную зону сводки',
            ),
            'overdue_target' => array(
                'label' => 'Допустимая доля просроченных заказов',
                'unit'  => '%',
                'value' => 10,
                'min'   => 0,
                'max'   => 100,
                'note'  => 'Считается от открытых заказов поставщикам',
            ),
        );
    }

    /**
     * Все значения: значения по умолчанию, перекрытые сохранёнными.
     *
     * @return  array   код => значение (float|int)
     */
    public function all()
    {
        if ($this->_values !== NULL)
        {
            return $this->_values;
        }

        $values = array();

        foreach ($this->definitions() as $code => $definition)
        {
            $values[$code] = $definition['value'];
        }

        foreach ($this->_stored() as $code => $value)
        {
            if (isset($values[$code]))
            {
                $values[$code] = (strpos((string) $value, '.') === FALSE)
                    ? (int) $value
                    : (float) $value;
            }
        }

        return $this->_values = $values;
    }

    /**
     * Одно значение.
     *
     * @param   string  $code
     * @param   mixed   $default
     * @return  mixed
     */
    public function get($code, $default = NULL)
    {
        $values = $this->all();

        return isset($values[$code]) ? $values[$code] : $default;
    }

    /**
     * Настройки с подписями — для формы и API.
     *
     * @return  array
     */
    public function rows()
    {
        $stored = $this->_stored();
        $meta   = $this->_meta();
        $rows   = array();

        foreach ($this->definitions() as $code => $definition)
        {
            $rows[] = array(
                'code'        => $code,
                'label'       => $definition['label'],
                'unit'        => $definition['unit'],
                'note'        => $definition['note'],
                'min'         => $definition['min'],
                'max'         => $definition['max'],
                'value'       => $this->get($code),
                'is_default'  => ! isset($stored[$code]),
                'updated_at'  => isset($meta[$code]['updated_at']) ? $meta[$code]['updated_at'] : NULL,
                'updated_by'  => isset($meta[$code]['updated_by']) ? $meta[$code]['updated_by'] : NULL,
            );
        }

        return $rows;
    }

    /**
     * Сохранить пороги.
     *
     * Принимаются только известные коды; значения проверяются по границам из
     * `definitions()`. Пустая строка означает «вернуть значение по умолчанию» —
     * запись удаляется, а не превращается в ноль.
     *
     * @param   array   $values
     * @param   int|null $user_id
     * @return  array   сохранённые значения
     */
    public function save(array $values, $user_id = NULL)
    {
        $definitions = $this->definitions();
        $saved       = array();

        foreach ($values as $code => $value)
        {
            if ( ! isset($definitions[$code]))
            {
                continue;
            }

            if (is_string($value) AND trim($value) === '')
            {
                $this->_delete($code);
                $saved[$code] = $definitions[$code]['value'];
                continue;
            }

            if ( ! is_numeric($value))
            {
                throw new Exception_Erp801_Analytics(
                    'Значение «:value» настройки «:label» не число',
                    array(':value' => (string) $value, ':label' => $definitions[$code]['label']),
                    'validation'
                );
            }

            $number = (float) $value;

            if ($number < $definitions[$code]['min'] OR $number > $definitions[$code]['max'])
            {
                throw new Exception_Erp801_Analytics(
                    'Настройка «:label» вне диапазона: допустимо от :min до :max',
                    array(
                        ':label' => $definitions[$code]['label'],
                        ':min'   => $definitions[$code]['min'],
                        ':max'   => $definitions[$code]['max'],
                    ),
                    'validation'
                );
            }

            $this->_store($code, $number, $user_id);

            $saved[$code] = (strpos((string) $value, '.') === FALSE) ? (int) $number : $number;
        }

        if (isset($saved['abc_a_share'], $saved['abc_b_share']) AND $saved['abc_a_share'] >= $saved['abc_b_share'])
        {
            throw new Exception_Erp801_Analytics(
                'Граница класса A (:a %) должна быть меньше границы класса B (:b %)',
                array(':a' => $saved['abc_a_share'], ':b' => $saved['abc_b_share']),
                'validation'
            );
        }

        if (isset($saved['xyz_x_cv'], $saved['xyz_y_cv']) AND $saved['xyz_x_cv'] >= $saved['xyz_y_cv'])
        {
            throw new Exception_Erp801_Analytics(
                'Граница класса X (:x %) должна быть меньше границы класса Y (:y %)',
                array(':x' => $saved['xyz_x_cv'], ':y' => $saved['xyz_y_cv']),
                'validation'
            );
        }

        $this->_values = NULL;

        return $this->all();
    }

    /**
     * Сбросить настройку к значению по умолчанию.
     *
     * @param   string  $code
     * @return  void
     */
    public function reset($code)
    {
        $this->_delete($code);

        $this->_values = NULL;
    }

    /**
     * Сохранённые значения из базы.
     *
     * @return  array
     */
    protected function _stored()
    {
        $rows = array();

        try
        {
            foreach (DB::select('code', 'value')->from('erp_801_settings')->execute()->as_array() as $row)
            {
                $rows[(string) $row['code']] = (string) $row['value'];
            }
        }
        catch (Exception $e)
        {
            // Модуль ещё не мигрирован: работаем на значениях по умолчанию.
            Kohana::$log->add(Log::WARNING, 'erp801: настройки недоступны — :error', array(':error' => $e->getMessage()));
        }

        return $rows;
    }

    /**
     * Кто и когда менял настройки.
     *
     * @return  array
     */
    protected function _meta()
    {
        $rows = array();

        try
        {
            foreach (DB::select('code', 'updated_at', 'updated_by')->from('erp_801_settings')->execute()->as_array() as $row)
            {
                $rows[(string) $row['code']] = array(
                    'updated_at' => (string) $row['updated_at'],
                    'updated_by' => $row['updated_by'] === NULL ? NULL : (int) $row['updated_by'],
                );
            }
        }
        catch (Exception $e)
        {
            return array();
        }

        return $rows;
    }

    /**
     * @param   string      $code
     * @param   float|int   $value
     * @param   int|null    $user_id
     * @return  void
     */
    protected function _store($code, $value, $user_id = NULL)
    {
        $existing = DB::select('id')->from('erp_801_settings')->where('code', '=', (string) $code)->execute()->current();

        $data = array(
            'value'      => (string) $value,
            'updated_by' => $user_id ? (int) $user_id : NULL,
            'updated_at' => date('c'),
        );

        if ($existing)
        {
            DB::update('erp_801_settings')->set($data)->where('id', '=', (int) $existing['id'])->execute();
        }
        else
        {
            DB::insert('erp_801_settings', array('code', 'value', 'updated_by', 'updated_at'))
                ->values(array((string) $code, (string) $value, $user_id ? (int) $user_id : NULL, date('c')))
                ->execute();
        }
    }

    /**
     * @param   string  $code
     * @return  void
     */
    protected function _delete($code)
    {
        DB::delete('erp_801_settings')->where('code', '=', (string) $code)->execute();
    }
}
