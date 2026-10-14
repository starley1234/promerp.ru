<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Справочник кодов дефектов (erp405_quality).
 *
 * Один словарь на весь завод: приёмка, цех и разбор рекламаций называют дефект
 * одним кодом — поэтому «бюджет качества» и статистика по причинам считаются
 * без разбора свободного текста. Стартовые 12 кодов приходят миграцией 002;
 * дальше справочник живёт своей жизнью.
 *
 * Код дефекта не удаляется, если уже встречался в несоответствиях: его
 * выключают (`is_active = 0`) — история дефектов должна остаться читаемой.
 *
 * @package    Erp405
 * @category   Services
 */
class Service_Erp405_DefectCodes
{
    /** @var Service_Erp405_DefectCodes|null */
    protected static $_instance;

    /**
     * @return  Service_Erp405_DefectCodes
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    // =========================================================================
    //  Чтение
    // =========================================================================

    /**
     * @param   int $id
     * @return  Model_Erp405_DefectCode|null
     */
    public function find($id)
    {
        $code = ORM::factory('Erp405_DefectCode', (int) $id);

        return $code->loaded() ? $code : NULL;
    }

    /**
     * Поиск по справочнику.
     *
     * @param   array   $filters   term, category, severity, active_only
     * @param   int     $limit
     * @param   int     $offset
     * @return  ORM_Result
     */
    public function search(array $filters = array(), $limit = 50, $offset = 0)
    {
        $query = ORM::factory('Erp405_DefectCode');

        if ( ! empty($filters['category']) AND isset(Model_Erp405_DefectCode::$categories[$filters['category']]))
        {
            $query->where('category', '=', (string) $filters['category']);
        }

        if ( ! empty($filters['severity']))
        {
            $query->where('severity', '=', (int) $filters['severity']);
        }

        if ( ! empty($filters['term']))
        {
            $term = mb_strtolower(trim((string) $filters['term']));
            $query->where('search_text', 'LIKE', '%'.$term.'%');
        }

        if ( ! array_key_exists('active_only', $filters) OR $filters['active_only'])
        {
            $query->where('is_active', '=', 1);
        }

        return $query
            ->order_by('category', 'ASC')
            ->order_by('severity', 'ASC')
            ->order_by('name', 'ASC')
            ->limit($limit)
            ->offset($offset)
            ->find_all();
    }

    /**
     * Список для выпадающего меню: id => «КОД — Название».
     *
     * @param   bool $active_only
     * @return  array
     */
    public function options($active_only = TRUE)
    {
        $options = array();

        foreach ($this->search(array('active_only' => $active_only), 500, 0) as $code)
        {
            $options[(int) $code->id] = $code->code.' — '.$code->name;
        }

        return $options;
    }

    /**
     * Сколько раз код встречался в несоответствиях.
     *
     * @param   int $id
     * @return  int
     */
    public function usage($id)
    {
        $row = DB::select(array(DB::expr('COUNT(*)'), 'total'))
            ->from('erp_405_defects')
            ->where('defect_code_id', '=', (int) $id)
            ->execute()
            ->current();

        return (int) $row['total'];
    }

    // =========================================================================
    //  Запись
    // =========================================================================

    /**
     * Завести код дефекта.
     *
     * @param   array   $data
     * @param   int|null $user_id
     * @return  Model_Erp405_DefectCode
     * @throws  Exception_Erp405_Quality
     */
    public function create(array $data, $user_id = NULL)
    {
        $code = (string) Arr::get($data, 'code', '');
        $name = (string) Arr::get($data, 'name', '');

        if ($code === '' OR $name === '')
        {
            throw new Exception_Erp405_Quality('Коду дефекта нужны обозначение и название', NULL, 'invalid_argument');
        }

        $exists = ORM::factory('Erp405_DefectCode')->where('code', '=', mb_strtoupper($code))->find();

        if ($exists->loaded())
        {
            throw new Exception_Erp405_Quality('Код дефекта :code уже есть в справочнике', array(':code' => $code), 'duplicate_code');
        }

        $category = (string) Arr::get($data, 'category', 'other');

        if ( ! isset(Model_Erp405_DefectCode::$categories[$category]))
        {
            $category = 'other';
        }

        $severity = (int) Arr::get($data, 'severity', 2);

        if ($severity < 1 OR $severity > 3)
        {
            $severity = 2;
        }

        $model = ORM::factory('Erp405_DefectCode');
        $model->code        = mb_strtoupper($code);
        $model->name        = $name;
        $model->category    = $category;
        $model->severity    = $severity;
        $model->description = (string) Arr::get($data, 'description', '');
        $model->is_active   = 1;
        $model->search_text = mb_strtolower($model->code.' '.$name.' '.$model->description);
        $model->save();

        Event::fire('erp405.defect_code.created', array(
            'id'       => (int) $model->id,
            'code'     => (string) $model->code,
            'title'    => (string) $model->code.' — '.$model->name,
            'actor_id' => $user_id,
        ));

        return $model;
    }

    /**
     * Изменить код дефекта.
     *
     * @param   int     $id
     * @param   array   $data
     * @param   int|null $user_id
     * @return  Model_Erp405_DefectCode
     * @throws  Exception_Erp405_Quality
     */
    public function update($id, array $data, $user_id = NULL)
    {
        $model = $this->find($id);

        if ( ! $model)
        {
            throw new Exception_Erp405_Quality('Код дефекта не найден', array(':id' => $id), 'not_found');
        }

        $before = $model->as_array();

        if (array_key_exists('name', $data))
        {
            $model->name = (string) $data['name'];
        }

        if (array_key_exists('category', $data) AND isset(Model_Erp405_DefectCode::$categories[$data['category']]))
        {
            $model->category = (string) $data['category'];
        }

        if (array_key_exists('severity', $data) AND (int) $data['severity'] >= 1 AND (int) $data['severity'] <= 3)
        {
            $model->severity = (int) $data['severity'];
        }

        if (array_key_exists('description', $data))
        {
            $model->description = (string) $data['description'];
        }

        if (array_key_exists('is_active', $data))
        {
            $model->is_active = $data['is_active'] ? 1 : 0;
        }

        $model->search_text = mb_strtolower($model->code.' '.$model->name.' '.$model->description);
        $model->save();

        $this->_journal($model, 'update', $before, $model->as_array(), $user_id,
            'Код дефекта: '.$model->code);

        return $model;
    }

    /**
     * Выключить код: он остаётся в истории дефектов, но не предлагается в меню.
     *
     * @param   int     $id
     * @param   int|null $user_id
     * @return  Model_Erp405_DefectCode
     * @throws  Exception_Erp405_Quality
     */
    public function deactivate($id, $user_id = NULL)
    {
        return $this->update($id, array('is_active' => 0), $user_id);
    }

    /**
     * @param   Model_Erp405_DefectCode $model
     * @param   string                  $action
     * @param   array                   $before
     * @param   array                   $after
     * @param   int|null                $user_id
     * @param   string|null             $message
     * @return  void
     */
    protected function _journal($model, $action, array $before, array $after, $user_id = NULL, $message = NULL)
    {
        if ( ! class_exists('Service_Erp119_Journal'))
        {
            return;
        }

        try
        {
            Service_Erp119_Journal::instance()->change('defect_code', (string) $model->id, $action, $before, $after, array(
                'module'       => 'erp405',
                'entity_label' => (string) $model->code,
                'message'      => $message,
                'actor_id'     => $user_id,
            ));
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp405: журнал действий недоступен — :error', array(':error' => $e->getMessage()));
        }
    }
}
