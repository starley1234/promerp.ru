<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Лента работы по сделке (erp221_crm): звонки, письма, встречи, задачи, файлы.
 *
 * Одна запись ленты — это факт: кто, когда и что сделал. Правки сохраняются
 * снимком в `erp_221_activities_history`, поэтому «было — стало» восстанавливается
 * даже после редактирования. Сделки без ленты не бывает: карточка продажи без
 * истории разговоров бесполезна для того, кто подхватит её завтра.
 *
 * @package    Erp221
 * @category   Services
 */
class Service_Erp221_Timeline
{
    /** Виды записей ленты. */
    const TYPES = array('comment', 'call', 'meeting', 'email', 'task', 'file', 'system', 'stage', 'note');

    /** Состояния записей: задача может ждать, быть выполненной или снятой. */
    const STATUSES = array('pending', 'completed', 'cancelled');

    /** @var Service_Erp221_Timeline|null */
    protected static $_instance;

    /**
     * @return  Service_Erp221_Timeline
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
     * Добавить запись в ленту сделки (или клиента, если сделки ещё нет).
     *
     * @param   integer $deal_id
     * @param   array   $data  type, subject, content, status, meta, scheduled_at, …
     * @param   integer $actor
     * @return  array
     * @throws  Kohana_Exception
     */
    public function add($deal_id, array $data, $actor = NULL)
    {
        $deal_id = (int) $deal_id;

        $counterparty_id = (int) Arr::get($data, 'counterparty_id');

        if ($deal_id)
        {
            $deal = Service_Erp221_Deals::instance()->get($deal_id);
            $counterparty_id = $counterparty_id ?: (int) $deal['counterparty_id'];
        }

        if ( ! $deal_id AND ! $counterparty_id)
        {
            throw new Kohana_Exception('Запись ленты должна быть привязана к сделке или к клиенту');
        }

        $type = strtolower(trim((string) Arr::get($data, 'type', 'comment')));

        if ( ! in_array($type, self::TYPES, TRUE))
        {
            throw new Kohana_Exception('Неизвестный вид записи ленты «:type»', array(':type' => $type));
        }

        $status = strtolower(trim((string) Arr::get($data, 'status', 'completed')));

        if ( ! in_array($status, self::STATUSES, TRUE))
        {
            throw new Kohana_Exception('Неизвестное состояние записи «:status»', array(':status' => $status));
        }

        $content = Service_Erp221_Cast::text(Arr::get($data, 'content'));
        $subject = Service_Erp221_Cast::text(Arr::get($data, 'subject'));

        // Пустая запись бесполезна: у файла и системной отметки текст не нужен,
        // у остальных достаточно темы — «Позвонить» это уже задача.
        if ($type !== 'file' AND $type !== 'system' AND $type !== 'stage'
            AND ($content === NULL OR $content === '') AND ($subject === NULL OR $subject === ''))
        {
            throw new Kohana_Exception('Заполните текст или тему записи');
        }

        $scheduled_at = Service_Erp221_Cast::text(Arr::get($data, 'scheduled_at'));

        if ($status === 'pending' AND ! $scheduled_at)
        {
            throw new Kohana_Exception('Для задачи укажите срок — иначе напоминание не сработает');
        }

        $values = array(
            'deal_id'         => $deal_id ?: NULL,
            'counterparty_id' => $counterparty_id ?: NULL,
            'type'            => $type,
            'status'          => $status,
            'subject'         => $subject,
            'content'         => $content,
            'storage_file_id' => (int) Arr::get($data, 'storage_file_id') ?: NULL,
            'meta'            => json_encode(Service_Erp221_Cast::json(Arr::get($data, 'meta', array())), JSON_UNESCAPED_UNICODE),
            'assigned_to'     => (int) Arr::get($data, 'assigned_to') ?: NULL,
            'scheduled_at'    => $scheduled_at,
            'completed_at'    => $status === 'completed' ? DB::expr('CURRENT_TIMESTAMP') : NULL,
            'created_by'      => $actor ? (int) $actor : NULL,
            'created_at'      => DB::expr('CURRENT_TIMESTAMP'),
            'updated_at'      => DB::expr('CURRENT_TIMESTAMP'),
        );

        $id = Service_Erp221_Cast::insert_id(
            DB::insert('erp_221_activities', array_keys($values))->values(array_values($values))->execute()
        );

        $this->snapshot($id, $actor);

        $this->event('activity.added', $actor, array(
            'deal_id'     => $deal_id ?: NULL,
            'activity_id' => (int) $id,
            'type'        => $type,
        ));

        return $this->get($id);
    }

    /**
     * Изменить запись ленты (текст задачи, срок, ответственного).
     *
     * @param   integer $activity_id
     * @param   array   $data
     * @param   integer $actor
     * @return  array
     * @throws  Kohana_Exception
     */
    public function update($activity_id, array $data, $actor = NULL)
    {
        $activity = $this->get($activity_id);

        $values = array('updated_at' => DB::expr('CURRENT_TIMESTAMP'));

        foreach (array('subject', 'content') as $field)
        {
            if (array_key_exists($field, $data))
            {
                $values[$field] = Service_Erp221_Cast::text($data[$field]);
            }
        }

        if (array_key_exists('scheduled_at', $data))
        {
            $values['scheduled_at'] = Service_Erp221_Cast::text($data['scheduled_at']);
        }

        if (array_key_exists('assigned_to', $data))
        {
            $values['assigned_to'] = (int) $data['assigned_to'] ?: NULL;
        }

        if (array_key_exists('meta', $data))
        {
            $values['meta'] = json_encode(array_merge($activity['meta'], Service_Erp221_Cast::json($data['meta'])), JSON_UNESCAPED_UNICODE);
        }

        if ( ! empty($data['status']))
        {
            $status = strtolower(trim((string) $data['status']));

            if ( ! in_array($status, self::STATUSES, TRUE))
            {
                throw new Kohana_Exception('Неизвестное состояние записи «:status»', array(':status' => $status));
            }

            $values['status']       = $status;
            $values['completed_at'] = ($status === 'completed') ? DB::expr('CURRENT_TIMESTAMP') : NULL;
        }

        DB::update('erp_221_activities')->set($values)->where('id', '=', (int) $activity['id'])->execute();

        $this->snapshot($activity['id'], $actor);

        return $this->get($activity['id']);
    }

    /**
     * Отметить задачу выполненной.
     *
     * @param   integer $activity_id
     * @param   integer $actor
     * @return  array
     */
    public function complete($activity_id, $actor = NULL)
    {
        return $this->update($activity_id, array('status' => 'completed'), $actor);
    }

    /**
     * Убрать запись из ленты (мягко).
     *
     * @param   integer $activity_id
     * @param   integer $actor
     * @return  boolean
     */
    public function delete($activity_id, $actor = NULL)
    {
        $activity = $this->get($activity_id);

        DB::update('erp_221_activities')
            ->set(array('is_deleted' => TRUE, 'updated_at' => DB::expr('CURRENT_TIMESTAMP')))
            ->where('id', '=', (int) $activity['id'])
            ->execute();

        $this->snapshot($activity['id'], $actor);

        return TRUE;
    }

    /**
     * Лента одной сделки, от свежих к старым.
     *
     * @param   integer $deal_id
     * @param   array   $filters  type, status, from, to
     * @param   integer $limit
     * @param   integer $offset
     * @return  array
     */
    public function timeline($deal_id, array $filters = array(), $limit = 50, $offset = 0)
    {
        return $this->journal($filters + array('deal_id' => (int) $deal_id), $limit, $offset);
    }

    /**
     * Журнал: все записи ленты с фильтрами (сделка, клиент, вид, состояние, автор).
     *
     * @param   array   $filters
     * @param   integer $limit
     * @param   integer $offset
     * @return  array  ['rows' => array, 'total' => int]
     */
    public function journal(array $filters = array(), $limit = 50, $offset = 0)
    {
        $total = (int) $this->query($filters, TRUE)->execute()->get('total');

        $rows = $this->query($filters)
            ->order_by('a.created_at', 'DESC')
            ->order_by('a.id', 'DESC')
            ->limit((int) $limit)
            ->offset((int) $offset)
            ->execute()->as_array();

        $result = array();

        foreach ($rows as $row)
        {
            $result[] = $this->prepare($row);
        }

        return array('rows' => $result, 'total' => $total);
    }

    /**
     * Незакрытые задачи: срок подходит или уже прошёл.
     *
     * @param   integer $user_id  ответственный (NULL — все)
     * @param   array   $filters  deal_id, overdue, due_before
     * @return  array
     */
    public function tasks($user_id = NULL, array $filters = array())
    {
        $query = $this->query(array('status' => 'pending') + $filters);

        if ($user_id)
        {
            $query->where('a.assigned_to', '=', (int) $user_id);
        }

        if ( ! empty($filters['overdue']))
        {
            $query->where('a.scheduled_at', '<', DB::expr('CURRENT_TIMESTAMP'));
        }

        if ( ! empty($filters['due_before']))
        {
            $query->where('a.scheduled_at', '<=', (string) $filters['due_before']);
        }

        $tasks = array();

        foreach ($query->order_by('a.scheduled_at', 'ASC')->execute()->as_array() as $row)
        {
            $tasks[] = $this->prepare($row);
        }

        return $tasks;
    }

    /**
     * История правок одной записи ленты.
     *
     * @param   integer $activity_id
     * @return  array
     */
    public function history($activity_id)
    {
        $rows = DB::select()->from('erp_221_activities_history')
            ->where('activity_id', '=', (int) $activity_id)
            ->order_by('changed_at', 'DESC')
            ->order_by('id', 'DESC')
            ->execute()->as_array();

        foreach ($rows as &$row)
        {
            $row['id']         = (int) $row['id'];
            $row['snapshot']   = Service_Erp221_Cast::json($row['snapshot']);
            $row['changed_at'] = Service_Erp221_Cast::stamp($row['changed_at']);
        }

        return $rows;
    }

    /**
     * Запись ленты по id.
     *
     * @param   integer $activity_id
     * @return  array
     * @throws  Kohana_Exception
     */
    public function get($activity_id)
    {
        $row = $this->query(array('activity_id' => (int) $activity_id, 'include_deleted' => TRUE))->limit(1)->execute()->current();

        if ( ! $row)
        {
            throw new Kohana_Exception('Запись ленты #:id не найдена', array(':id' => (int) $activity_id));
        }

        return $this->prepare($row);
    }

    /**
     * Приложить файл к сделке: файл уходит в общее хранилище (erp212), а в ленте
     * остаётся ссылка на него и человеческое описание.
     *
     * @param   integer $deal_id
     * @param   array   $file       элемент $_FILES
     * @param   integer $actor
     * @param   string  $content
     * @return  array
     * @throws  Kohana_Exception
     */
    public function upload($deal_id, array $file, $actor = NULL, $content = NULL)
    {
        if ( ! class_exists('Storage'))
        {
            throw new Kohana_Exception('Хранилище файлов (erp212) недоступно — приложите файл другим способом');
        }

        $stored = Storage::upload($file, 'erp221_crm', FALSE);

        return $this->add($deal_id, array(
            'type'            => 'file',
            'subject'         => Arr::get($file, 'name'),
            'content'         => $content,
            'storage_file_id' => (int) $stored->id,
        ), $actor);
    }

    /**
     * Системное событие CRM: аудит (erp119) и шина событий (erp208).
     *
     * Записи в ленте здесь нет — событие не о продаже, а о работе модуля.
     *
     * @param   string  $name     например 'deal.created'
     * @param   integer $actor
     * @param   array   $payload
     * @return  void
     */
    public function event($name, $actor = NULL, array $payload = array())
    {
        $event = 'erp221.'.$name;

        if (class_exists('Audit'))
        {
            Audit::log($event, array('actor' => $actor) + $payload);
        }

        if (class_exists('Event'))
        {
            try
            {
                Event::fire($event, $payload + array('actor' => $actor), $actor);
            }
            catch (Exception $e)
            {
                // Подписчик не должен ронять продажу: событие — уведомление, а
                // не часть транзакции. Ошибку видно в файловом журнале.
                Kohana::$log->add(Log::WARNING, 'erp221: подписчик события :event упал — :error', array(
                    ':event' => $event,
                    ':error' => $e->getMessage(),
                ));
            }
        }
    }

    /**
     * Снимок записи для истории изменений.
     *
     * @param   integer $activity_id
     * @param   integer $actor
     * @return  void
     */
    protected function snapshot($activity_id, $actor = NULL)
    {
        $activity = DB::select()->from('erp_221_activities')->where('id', '=', (int) $activity_id)->execute()->current();

        if ( ! $activity)
        {
            return;
        }

        DB::insert('erp_221_activities_history', array('activity_id', 'changed_by', 'changed_at', 'snapshot'))
            ->values(array(
                (int) $activity_id,
                $actor ? (int) $actor : NULL,
                DB::expr('CURRENT_TIMESTAMP'),
                json_encode($activity, JSON_UNESCAPED_UNICODE),
            ))
            ->execute();
    }

    /**
     * Запрос журнала с фильтрами.
     *
     * @param   array  $filters
     * @return  Database_Query_Builder_Select
     */
    protected function query(array $filters, $count = FALSE)
    {
        if ($count)
        {
            // Для итога колонки не нужны: считаем строки тем же набором условий.
            $query = DB::select(array(DB::expr('COUNT(*)'), 'total'));
        }
        else
        {
            $query = DB::select(
                'a.*',
                array('d.number', 'deal_number'),
                array('d.title', 'deal_title'),
                array('d.assigned_to', 'deal_owner'),
                array('c.name', 'client_name'),
                array('u.username', 'author_name')
            );
        }

        $query
            ->from(array('erp_221_activities', 'a'))
            ->join(array('erp_221_deals', 'd'), 'LEFT')->on('d.id', '=', 'a.deal_id')
            ->join(array('erp_301_counterparties', 'c'), 'LEFT')->on('c.id', '=', 'a.counterparty_id')
            ->join(array('users', 'u'), 'LEFT')->on('u.id', '=', 'a.created_by');

        if ( ! empty($filters['activity_id']))
        {
            $query->where('a.id', '=', (int) $filters['activity_id']);
        }

        if (empty($filters['include_deleted']))
        {
            $query->where('a.is_deleted', '=', FALSE);
        }

        if ( ! empty($filters['deal_id']))
        {
            $query->where('a.deal_id', '=', (int) $filters['deal_id']);
        }

        if ( ! empty($filters['counterparty']))
        {
            $query->where('a.counterparty_id', '=', (int) $filters['counterparty']);
        }

        if ( ! empty($filters['type']))
        {
            $query->where('a.type', '=', (string) $filters['type']);
        }

        if ( ! empty($filters['status']))
        {
            $query->where('a.status', '=', (string) $filters['status']);
        }

        if ( ! empty($filters['assigned_to']))
        {
            $query->where('a.assigned_to', '=', (int) $filters['assigned_to']);
        }

        if ( ! empty($filters['author']))
        {
            $query->where('a.created_by', '=', (int) $filters['author']);
        }

        if ( ! empty($filters['from']))
        {
            $query->where('a.created_at', '>=', (string) $filters['from']);
        }

        if ( ! empty($filters['to']))
        {
            $query->where('a.created_at', '<=', (string) $filters['to']);
        }

        if ( ! empty($filters['q']))
        {
            $query->where_open()
                ->where('a.subject', 'ILIKE', '%'.trim((string) $filters['q']).'%')
                ->or_where('a.content', 'ILIKE', '%'.trim((string) $filters['q']).'%')
                ->or_where('d.number', 'ILIKE', '%'.trim((string) $filters['q']).'%')
                ->where_close();
        }

        return $query;
    }

    /**
     * Подготовить запись журнала к показу.
     *
     * @param   array  $row
     * @return  array
     */
    public function prepare(array $row)
    {
        $row['id']         = (int) $row['id'];
        $row['deal_id']    = $row['deal_id'] === NULL ? NULL : (int) $row['deal_id'];
        $row['meta']       = Service_Erp221_Cast::json($row['meta']);
        $row['created_at'] = Service_Erp221_Cast::stamp($row['created_at']);
        $row['updated_at'] = Service_Erp221_Cast::stamp($row['updated_at']);
        $row['scheduled_at'] = Service_Erp221_Cast::stamp($row['scheduled_at']);
        $row['completed_at'] = Service_Erp221_Cast::stamp($row['completed_at']);
        $row['is_overdue'] = $row['status'] === 'pending'
            AND $row['scheduled_at'] !== NULL
            AND strtotime((string) $row['scheduled_at']) < time();

        return $row;
    }

    /**
     * Человеческие названия видов записей.
     *
     * @return  array
     */
    public function types()
    {
        return array(
            'comment' => 'Комментарий',
            'call'    => 'Звонок',
            'meeting' => 'Встреча',
            'email'   => 'Письмо',
            'task'    => 'Задача',
            'file'    => 'Файл',
            'note'    => 'Заметка',
            'stage'   => 'Смена стадии',
            'system'  => 'Системная запись',
        );
    }

    /**
     * Человеческие названия состояний.
     *
     * @return  array
     */
    public function statuses()
    {
        return array(
            'pending'   => 'Ждёт выполнения',
            'completed' => 'Выполнено',
            'cancelled' => 'Снято',
        );
    }
}
