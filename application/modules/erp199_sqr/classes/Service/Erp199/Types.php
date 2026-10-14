<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Типы целей и категории объектов (erp199_sqr).
 *
 * Тип отвечает на два вопроса: каким адаптером читается цель (`adapter`) и
 * можно ли завести объект прямо с телефона (`allow_local`). Подсказка для
 * распознавания фото (`ai_prompt_specs`, `expected_attributes`) — только
 * помощник: без LLM-модуля тип продолжает работать.
 *
 * @package    Erp199
 * @category   Services
 */
class Service_Erp199_Types
{
    /** @var Service_Erp199_Types|null */
    protected static $_instance;

    /**
     * @return  Service_Erp199_Types
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
     * Каталог типов.
     *
     * @param   boolean $active_only
     * @param   string  $adapter  фильтр по адаптеру
     * @return  array
     */
    public function all($active_only = TRUE, $adapter = NULL)
    {
        $query = DB::select()->from('erp_199_types');

        if ($active_only)
        {
            $query->where('is_active', '=', TRUE);
        }

        if ($adapter)
        {
            $query->where('adapter', '=', (string) $adapter);
        }

        $rows = $query->order_by('sort', 'ASC')->order_by('label', 'ASC')->execute()->as_array();

        foreach ($rows as &$row)
        {
            $row = $this->prepare($row);
        }
        unset($row);

        return $rows;
    }

    /**
     * Тип по коду.
     *
     * @param   string  $code
     * @return  array
     */
    public function get($code)
    {
        $row = DB::select()->from('erp_199_types')->where('code', '=', (string) $code)->execute()->current();

        if ( ! $row)
        {
            throw new Kohana_Exception('Тип «:code» не найден', array(':code' => (string) $code));
        }

        return $this->prepare($row);
    }

    /**
     * Сохранить тип (админка).
     *
     * @param   array   $data
     * @param   integer $actor
     * @return  array
     */
    public function save(array $data, $actor = NULL)
    {
        $existing = NULL;

        if ( ! empty($data['id']))
        {
            $existing = DB::select('id', 'code')->from('erp_199_types')->where('id', '=', (int) $data['id'])->execute()->current();
        }
        elseif ( ! empty($data['code']))
        {
            $existing = DB::select('id', 'code')->from('erp_199_types')
                ->where('code', '=', strtolower(trim((string) $data['code'])))->execute()->current();
        }

        $code = strtolower(trim((string) Arr::get($data, 'code', $existing ? $existing['code'] : '')));

        if ( ! preg_match('/^[a-z][a-z0-9_]{1,31}$/', $code))
        {
            throw new Kohana_Exception('Код типа: латиница, цифры и подчёркивание, начиная с буквы');
        }

        $label = trim((string) Arr::get($data, 'label', ''));

        if ($label === '')
        {
            throw new Kohana_Exception('Укажите название типа');
        }

        $adapter = (string) Arr::get($data, 'adapter', 'local');

        if ( ! isset(Service_Erp199_Targets::instance()->kinds()[$adapter]))
        {
            throw new Kohana_Exception('Неизвестный адаптер «:adapter»', array(':adapter' => $adapter));
        }

        $attributes = Arr::get($data, 'expected_attributes', array());

        if (is_string($attributes))
        {
            $decoded = json_decode($attributes, TRUE);
            $attributes = is_array($decoded) ? $decoded : array_values(array_filter(array_map('trim', explode(',', $attributes))));
        }

        $values = array(
            'code'                => $code,
            'label'               => mb_substr($label, 0, 120),
            'adapter'             => $adapter,
            'icon'                => Arr::get($data, 'icon') ?: NULL,
            'hint'                => Arr::get($data, 'hint') ?: NULL,
            'ai_prompt_specs'     => Arr::get($data, 'ai_prompt_specs') ?: NULL,
            'expected_attributes' => json_encode(array_values($attributes), JSON_UNESCAPED_UNICODE),
            'allow_local'         => ! empty($data['allow_local']),
            'sort'                => (int) Arr::get($data, 'sort', 100),
            'is_active'           => ! isset($data['is_active']) OR ! empty($data['is_active']),
            'updated_at'          => DB::expr('CURRENT_TIMESTAMP'),
        );

        if ($existing)
        {
            DB::update('erp_199_types')->set($values)->where('id', '=', (int) $existing['id'])->execute();
        }
        else
        {
            $values['created_at'] = DB::expr('CURRENT_TIMESTAMP');
            DB::insert('erp_199_types', array_keys($values))->values(array_values($values))->execute();
        }

        Service_Erp199_Tokens::instance()->log(NULL, 'type', $actor, 'ok', array('code' => $code));

        return $this->get($code);
    }

    /**
     * Раскодировать JSON-поля.
     *
     * @param   array  $row
     * @return  array
     */
    protected function prepare(array $row)
    {
        $row['expected_attributes'] = Service_Erp199_Cast::json($row['expected_attributes']);
        $row['is_active']   = Service_Erp199_Cast::boolean($row['is_active']);
        $row['allow_local'] = Service_Erp199_Cast::boolean($row['allow_local']);
        $row['adapter_label'] = Arr::get(Service_Erp199_Targets::instance()->kinds(), $row['adapter'], $row['adapter']);

        return $row;
    }
}
