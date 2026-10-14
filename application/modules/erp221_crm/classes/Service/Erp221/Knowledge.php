<?php defined('SYSPATH') or die('No direct script access.');

/**
 * База знаний менеджера (erp221_crm).
 *
 * Типовые вопросы клиентов и готовые ответы: что сказать про сроки, цены и
 * гарантию. Это шпаргалка сотрудника, а не переписка с клиентом, поэтому
 * статьи правит человек, а поиск — простой и предсказуемый: по вопросу, ответу
 * и категории. Полнотекстовый индекс `to_tsvector('russian', …)` на таблице
 * доступен для тяжёлых выборок, но здесь он не нужен: статей десятки.
 *
 * @package    Erp221
 * @category   Services
 */
class Service_Erp221_Knowledge
{
    /** @var Service_Erp221_Knowledge|null */
    protected static $_instance;

    /**
     * @return  Service_Erp221_Knowledge
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    protected function __construct() {}

    /**
     * Поиск статей: свежие сверху, пустой запрос — весь справочник.
     *
     * @param   string  $term
     * @param   integer $limit
     * @return  array
     */
    public function search($term = NULL, $limit = 200)
    {
        $term  = trim((string) $term);
        $query = DB::select()->from('erp_221_knowledge_base')
            ->where('is_deleted', '=', FALSE);

        if ($term !== '')
        {
            $query->where_open()
                ->where('answer_text', 'ILIKE', '%'.$term.'%')
                ->or_where('question_pattern', 'ILIKE', '%'.$term.'%')
                ->or_where('category', 'ILIKE', '%'.$term.'%')
                ->where_close();
        }

        $rows = $query
            ->order_by('category', 'ASC')
            ->order_by('id', 'DESC')
            ->limit((int) $limit)
            ->execute()->as_array();

        $result = array();

        foreach ($rows as $row)
        {
            $result[] = $this->prepare($row);
        }

        return $result;
    }

    /**
     * Одна статья (без исключения: отсутствие — это NULL).
     *
     * @param   integer $id
     * @return  array|null
     */
    public function get($id)
    {
        $row = DB::select()->from('erp_221_knowledge_base')
            ->where('id', '=', (int) $id)
            ->where('is_deleted', '=', FALSE)
            ->execute()->current();

        return $row ? $this->prepare($row) : NULL;
    }

    /**
     * Добавить или изменить статью.
     *
     * @param   array   $data  id, category, question_pattern, answer_text, tags
     * @param   integer $actor
     * @return  integer  id статьи
     * @throws  Kohana_Exception
     */
    public function save(array $data, $actor = NULL)
    {
        $answer = trim((string) Arr::get($data, 'answer_text', ''));

        if ($answer === '')
        {
            throw new Kohana_Exception('Заполните ответ');
        }

        $values = array(
            'category'         => mb_substr(trim((string) Arr::get($data, 'category', 'general')), 0, 64) ?: 'general',
            'question_pattern' => Service_Erp221_Cast::text(Arr::get($data, 'question_pattern')),
            'answer_text'      => $answer,
            'tags'             => $this->tags(Arr::get($data, 'tags', array())),
            'updated_at'       => DB::expr('CURRENT_TIMESTAMP'),
        );

        $id = (int) Arr::get($data, 'id');

        if ($id)
        {
            DB::update('erp_221_knowledge_base')->set($values)->where('id', '=', $id)->execute();
        }
        else
        {
            $values['created_at'] = DB::expr('CURRENT_TIMESTAMP');

            $id = Service_Erp221_Cast::insert_id(
                DB::insert('erp_221_knowledge_base', array_keys($values))
                    ->values(array_values($values))
                    ->execute()
            );
        }

        $this->event($id, 'saved', $actor);

        return $id;
    }

    /**
     * Убрать статью из справочника (мягко: ссылки из ленты не рвутся).
     *
     * @param   integer $id
     * @param   integer $actor
     * @return  boolean
     */
    public function delete($id, $actor = NULL)
    {
        DB::update('erp_221_knowledge_base')
            ->set(array('is_deleted' => TRUE, 'updated_at' => DB::expr('CURRENT_TIMESTAMP')))
            ->where('id', '=', (int) $id)
            ->execute();

        $this->event((int) $id, 'deleted', $actor);

        return TRUE;
    }

    /**
     * Метки для записи: массив или строка «через запятую» → TEXT[].
     *
     * @param   mixed   $tags
     * @return  string  SQL-литерал массива
     */
    public function tags($tags)
    {
        if (is_string($tags))
        {
            $tags = explode(',', $tags);
        }

        $clean = array();

        foreach ((array) $tags as $tag)
        {
            $tag = trim((string) $tag);

            if ($tag !== '')
            {
                // Кавычки и обратные слэши в метке ломают литерал массива.
                $clean[] = '"'.str_replace(array('"', '\\'), '', $tag).'"';
            }
        }

        return '{'.implode(',', $clean).'}';
    }

    /**
     * Привести строку БД к виду, удобному видам и API.
     *
     * @param   array  $row
     * @return  array
     */
    public function prepare(array $row)
    {
        $row['id']         = (int) $row['id'];
        $row['tags']       = $this->parse_tags($row['tags']);
        $row['created_at'] = Service_Erp221_Cast::stamp($row['created_at']);
        $row['updated_at'] = Service_Erp221_Cast::stamp($row['updated_at']);

        return $row;
    }

    /**
     * TEXT[] из PG (строка `{a,b}`) → список значений.
     *
     * @param   mixed  $value
     * @return  array
     */
    protected function parse_tags($value)
    {
        if (is_array($value))
        {
            return $value;
        }

        $value = trim((string) $value, '{} ');

        if ($value === '')
        {
            return array();
        }

        $tags = array();

        foreach (explode(',', $value) as $tag)
        {
            $tag = trim(trim($tag), '"');

            if ($tag !== '')
            {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    /**
     * Запись в журнал аудита: правка шпаргалки тоже след в системе.
     *
     * @param   integer $id
     * @param   string  $action
     * @param   integer $actor
     * @return  void
     */
    protected function event($id, $action, $actor = NULL)
    {
        if (class_exists('Audit'))
        {
            Audit::log('erp221.kb', $action, $id, array('actor' => $actor ? (int) $actor : NULL));
        }
    }
}
