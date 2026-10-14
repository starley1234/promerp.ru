<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Сценарии мобильного терминала (erp199_sqr).
 *
 * «Сервер решает, какую страницу показать телефону»: сценарий описывает форму
 * (`form`), требования (фото, комментарий) и эффект (`effect`) данными, а не
 * кодом приложения. Добавили сценарий в базу — он появился в терминале без
 * обновления Android-приложения.
 *
 * @package    Erp199
 * @category   Services
 */
class Service_Erp199_Scenarios
{
    /** @var Service_Erp199_Scenarios|null */
    protected static $_instance;

    /** @var array|null Кэш каталога на время запроса */
    protected $_cache;

    /**
     * @return  Service_Erp199_Scenarios
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Каталог сценариев: код => подготовленная запись.
     *
     * @param   boolean $active_only
     * @return  array
     */
    public function all($active_only = TRUE)
    {
        if ($this->_cache === NULL)
        {
            $rows = DB::select()->from('erp_199_scenarios')->order_by('sort', 'ASC')->order_by('id', 'ASC')->execute()->as_array();

            $this->_cache = array();

            foreach ($rows as $row)
            {
                $this->_cache[$row['code']] = $this->prepare($row);
            }
        }

        if ( ! $active_only)
        {
            return $this->_cache;
        }

        return array_filter($this->_cache, function ($scenario) {
            return (bool) $scenario['is_active'];
        });
    }

    /**
     * Сценарий по коду или id.
     *
     * @param   mixed  $ref
     * @return  array
     */
    public function get($ref)
    {
        foreach ($this->all(FALSE) as $scenario)
        {
            if ($scenario['code'] === (string) $ref OR (int) $scenario['id'] === (int) $ref)
            {
                return $scenario;
            }
        }

        throw new Kohana_Exception('Сценарий «:code» не найден', array(':code' => (string) $ref));
    }

    /**
     * Сценарии, применимые к виду цели.
     *
     * Пустой список `target_kinds` означает «для любой цели» — так сценарий
     * инвентаризации и фото работают и на объектах, заведённых с телефона.
     *
     * @param   string  $kind
     * @return  array
     */
    public function for_kind($kind)
    {
        $result = array();

        foreach ($this->all() as $scenario)
        {
            if ( ! $scenario['target_kinds'] OR in_array($kind, $scenario['target_kinds'], TRUE))
            {
                $result[] = $scenario;
            }
        }

        return $result;
    }

    /**
     * Добавить/обновить сценарий (админка).
     *
     * @param   array   $data  code, label, description, icon, target_kinds, form, effect, ...
     * @param   integer $actor
     * @return  array
     */
    public function save(array $data, $actor = NULL)
    {
        $code = strtolower(trim((string) Arr::get($data, 'code', '')));

        if ( ! preg_match('/^[a-z][a-z0-9_]{2,47}$/', $code))
        {
            throw new Kohana_Exception('Код сценария: латиница, цифры и подчёркивание, начиная с буквы');
        }

        $label = trim((string) Arr::get($data, 'label', ''));

        if ($label === '')
        {
            throw new Kohana_Exception('Укажите название сценария');
        }

        $form   = $this->json_field($data, 'form', array('fields' => array()));
        $effect = $this->json_field($data, 'effect', array('kind' => 'record'));

        if (empty($effect['kind']))
        {
            $effect['kind'] = 'record';
        }

        $kinds = Arr::get($data, 'target_kinds', array());

        if (is_string($kinds))
        {
            $kinds = array_values(array_filter(array_map('trim', explode(',', $kinds))));
        }

        $known = Service_Erp199_Targets::instance()->kinds();

        foreach ($kinds as $kind)
        {
            if ( ! isset($known[$kind]))
            {
                throw new Kohana_Exception('Неизвестный вид цели «:kind» в сценарии', array(':kind' => (string) $kind));
            }
        }

        $values = array(
            'code'             => $code,
            'label'            => mb_substr($label, 0, 120),
            'description'      => Arr::get($data, 'description') ?: NULL,
            'icon'             => Arr::get($data, 'icon') ?: NULL,
            'target_kinds'     => json_encode(array_values($kinds), JSON_UNESCAPED_UNICODE),
            'requires_photo'   => ! empty($data['requires_photo']),
            'requires_comment' => ! empty($data['requires_comment']),
            'allow_multiple'   => ! isset($data['allow_multiple']) OR ! empty($data['allow_multiple']),
            'form'             => json_encode($form, JSON_UNESCAPED_UNICODE),
            'effect'           => json_encode($effect, JSON_UNESCAPED_UNICODE),
            'result_note'      => Arr::get($data, 'result_note') ?: NULL,
            'sort'             => (int) Arr::get($data, 'sort', 100),
            'is_active'        => ! isset($data['is_active']) OR ! empty($data['is_active']),
            'updated_at'       => DB::expr('CURRENT_TIMESTAMP'),
        );

        $existing = DB::select('id')->from('erp_199_scenarios')->where('code', '=', $code)->execute()->current();

        if ($existing)
        {
            DB::update('erp_199_scenarios')->set($values)->where('id', '=', (int) $existing['id'])->execute();
        }
        else
        {
            $values['created_at'] = DB::expr('CURRENT_TIMESTAMP');
            DB::insert('erp_199_scenarios', array_keys($values))->values(array_values($values))->execute();
        }

        Service_Erp199_Tokens::instance()->log(NULL, 'scenario', $actor, 'ok', array('code' => $code));

        $this->_cache = NULL;

        return $this->get($code);
    }

    /**
     * Разобрать JSON-поле (принимает массив или строку JSON).
     *
     * @param   array   $data
     * @param   string  $field
     * @param   array   $default
     * @return  array
     */
    protected function json_field(array $data, $field, array $default)
    {
        $value = Arr::get($data, $field);

        if (is_array($value))
        {
            return $value;
        }

        $value = trim((string) $value);

        if ($value === '')
        {
            return $default;
        }

        $decoded = json_decode($value, TRUE);

        if ( ! is_array($decoded))
        {
            throw new Kohana_Exception('Поле :field должно быть корректным JSON', array(':field' => $field));
        }

        return $decoded;
    }

    /**
     * Подготовить запись: раскодировать JSON и собрать поля формы.
     *
     * @param   array  $row
     * @return  array
     */
    public function prepare(array $row)
    {
        foreach (array('target_kinds', 'form', 'effect') as $field)
        {
            $default = ($field === 'form') ? array('fields' => array()) : array();
            $row[$field] = Service_Erp199_Cast::json($row[$field], $default);
        }

        // boolean из PostgreSQL приходит строкой 't'/'f' — см. Service_Erp199_Cast.
        $row['is_active']        = Service_Erp199_Cast::boolean($row['is_active']);
        $row['requires_photo']   = Service_Erp199_Cast::boolean($row['requires_photo']);
        $row['requires_comment'] = Service_Erp199_Cast::boolean($row['requires_comment']);
        $row['allow_multiple']   = Service_Erp199_Cast::boolean($row['allow_multiple']);
        $row['fields']           = (array) Arr::get($row['form'], 'fields', array());

        return $row;
    }
}
