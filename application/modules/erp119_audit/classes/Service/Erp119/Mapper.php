<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Разбор событий шины (erp_208_events) в строки журнала действий.
 *
 * Событие называется по конвенции `<модуль>.<сущность>.<действие>`
 * (`erp228.card.updated`), поэтому правило выводится из имени: модуль — по
 * префиксу `erpNNN`, сущность — между модулем и действием, действие — из
 * глагола (`kind()`). Если имя сущности в журнале должно отличаться от
 * сегмента события (`card` → `tech_card`), правило задаётся в конфиге
 * модуля (`event_rules`).
 *
 * Отдельно решается вопрос просмотров: событие «посмотрели карточку» —
 * это шум, если сущность не помечена в справочнике `erp_119_audit_settings`
 * (колонка `track_reads`). Просмотры пользователей и ролей аудируются,
 * просмотры техкарт — нет.
 *
 * @package    Erp119
 * @category   Services
 */
class Service_Erp119_Mapper
{
    /** @var Service_Erp119_Mapper */
    protected static $_instance;

    /** @var Service_Erp119_Journal */
    protected $_journal;

    /** @var array Справочник аудита сущностей: «модуль|сущность» → настройки */
    protected $_settings;

    /** @var array Кэш имён пользователей: id → имя */
    protected $_names = array();

    /**
     * @return  Service_Erp119_Mapper
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    protected function __construct()
    {
        $this->_journal = Service_Erp119_Journal::instance();
    }

    /**
     * Нужно ли переносить событие в журнал (шум шины и миграций отбрасывается).
     *
     * @param   string  $event_name
     * @return  bool
     */
    public function wanted($event_name)
    {
        foreach ((array) $this->_journal->config('ignore_events', array()) as $prefix)
        {
            if (strpos($event_name, $prefix) === 0)
            {
                return FALSE;
            }
        }

        return TRUE;
    }

    /**
     * Глагол события → действие журнала.
     *
     * @param   string  $verb
     * @return  string
     */
    public function kind($verb)
    {
        return $this->_journal->normalize_action($verb);
    }

    /**
     * Событие шины → строка журнала.
     *
     * @param   string  $event_name
     * @param   array   $payload
     * @param   int|null $initiator_id
     * @return  array|null
     */
    public function parse($event_name, array $payload, $initiator_id = NULL)
    {
        if ( ! $this->wanted($event_name))
        {
            return NULL;
        }

        $rule = $this->rule($event_name);

        // Просмотры — только для сущностей, где аудит чтения включён
        if ($rule['action'] === 'read' AND ! $this->tracks_reads($rule['module'], $rule['entity_type']))
        {
            return NULL;
        }

        if ( ! $this->enabled($rule['module'], $rule['entity_type']))
        {
            return NULL;
        }

        $consumed = array('severity');

        $entry = array(
            'module'       => $rule['module'],
            'entity_type'  => $rule['entity_type'],
            'entity_id'    => '',
            'entity_label' => NULL,
            'action'       => $rule['action'],
            'severity'     => $this->severity($payload, $rule['action']),
            'source'       => 'event',
            'context'      => array('event' => (string) $event_name),
        );

        if ( ! empty($rule['qualifier']))
        {
            // «…stage_changed.api»: канал публикации — это деталь, а не сущность
            $entry['context']['qualifier'] = $rule['qualifier'];
        }

        $entity_id = $this->body_value($payload, $rule, $this->id_fields($rule), $consumed);

        if ($entity_id !== NULL)
        {
            $entry['entity_id'] = (string) $entity_id;
        }

        $label = $this->body_value($payload, $rule, $this->_journal->config('label_fields', array()), $consumed);

        if ($label !== NULL)
        {
            $entry['entity_label'] = (string) $label;
        }

        $actor_id = $this->body_value($payload, $rule, array('actor_id', 'user_id', 'initiator_id'), $consumed);

        if ($actor_id === NULL)
        {
            $actor_id = $initiator_id;
        }

        if ( ! empty($actor_id))
        {
            $entry['actor_id']   = (int) $actor_id;
            $entry['actor_name'] = $this->actor_name((int) $actor_id);
        }

        $entry['context']['payload'] = $this->payload_tail($payload, $consumed);

        if ($entry['context']['payload'] === array())
        {
            unset($entry['context']['payload']);
        }

        return $entry;
    }

    /**
     * Правило разбора имени события.
     *
     * Явное правило из конфига побеждает. Иначе сегменты разбираются так:
     * модуль — по префиксу `erpNNN`, действие — последний «глагольный» сегмент,
     * сущность — то, что осталось между ними. Всё, что идёт после действия
     * (`…stage_changed.api`), считается уточнением и уезжает в контекст.
     *
     * @param   string  $event_name
     * @return  array   module, entity_type, action (+ id_field, qualifier)
     */
    public function rule($event_name)
    {
        $rules = (array) $this->_journal->config('event_rules', array());

        if (isset($rules[$event_name]))
        {
            return $rules[$event_name];
        }

        $parts  = explode('.', $event_name);
        $module = $this->module_of($parts[0]);

        $tail = array_slice($parts, 1);

        // Последний глагольный сегмент — действие
        $action_at = NULL;

        foreach ($tail as $index => $segment)
        {
            if ($this->is_verb($segment))
            {
                $action_at = $index;
            }
        }

        if ($action_at === NULL)
        {
            $action_at = max(0, count($tail) - 1);
        }

        $qualifier = array_slice($tail, $action_at + 1);

        $rule = array(
            'module'      => $module,
            'entity_type' => $this->entity_of($tail, $action_at, $parts[0], $module),
            'action'      => $this->kind(isset($tail[$action_at]) ? $tail[$action_at] : $event_name),
        );

        if ($qualifier)
        {
            $rule['qualifier'] = implode('.', $qualifier);
        }

        return $rule;
    }

    /**
     * Похож ли сегмент имени события на глагол.
     *
     * @param   string  $segment
     * @return  bool
     */
    public function is_verb($segment)
    {
        $verbs = (array) $this->_journal->config('verbs', array());

        if (isset($verbs[$segment]))
        {
            return TRUE;
        }

        // Приведение меняет слово: created → create, stock_received → receive
        if ($this->_journal->normalize_action($segment) !== $segment)
        {
            return TRUE;
        }

        // Готовые действия, которые не выводятся из глагола
        return in_array($segment, array(
            'create', 'update', 'delete', 'read', 'status', 'approve', 'send', 'fail',
            'publish', 'archive', 'login', 'logout', 'maintenance', 'export', 'move',
            'receive', 'issue', 'consume', 'release',
        ), TRUE);
    }

    /**
     * Сущность события: сегменты между модулем и действием. Если их нет
     * («erp401.stock_received», «erp217_notification_center.executed»), сущность
     * берётся из первого сегмента — из его «хвоста» после префикса модуля или
     * из слов перед глаголом.
     *
     * @param   array   $tail
     * @param   int     $action_at
     * @param   string  $module_segment
     * @param   string  $module
     * @return  string
     */
    public function entity_of(array $tail, $action_at, $module_segment, $module)
    {
        $entity = array_slice($tail, 0, $action_at);

        if ($entity)
        {
            return implode('.', $entity);
        }

        // «erp217_notification_center.executed» → notification_center
        $rest = ltrim(substr($module_segment, strlen($module)), '_');

        if ($rest !== '')
        {
            return $rest;
        }

        // «erp401.stock_received» → stock (слова перед глаголом)
        $segment = isset($tail[0]) ? $tail[0] : '';
        $words   = array_values(array_filter(explode('_', $segment), 'strlen'));

        if (count($words) > 1 AND $this->is_verb(end($words)))
        {
            array_pop($words);

            return implode('_', $words);
        }

        return ($segment === '') ? 'object' : $segment;
    }

    /**
     * Модуль по первому сегменту имени события.
     *
     * @param   string  $segment
     * @return  string
     */
    public function module_of($segment)
    {
        if (preg_match('/^(erp\d+)/', $segment, $matches))
        {
            return $matches[1];
        }

        $maps = (array) $this->_journal->config('category_modules', array());

        return isset($maps[$segment]) ? $maps[$segment] : 'app';
    }

    /**
     * Важность: приоритет у значения из тела события.
     *
     * @param   array   $payload
     * @param   string  $action
     * @return  string
     */
    public function severity(array $payload, $action)
    {
        $levels = (array) $this->_journal->config('severity_levels', array());

        if ( ! empty($payload['severity']) AND in_array($payload['severity'], $levels, TRUE))
        {
            return (string) $payload['severity'];
        }

        return $this->_journal->severity($action);
    }

    /**
     * Значение поля из тела события по списку кандидатов.
     *
     * @param   array   $payload
     * @param   array   $rule
     * @param   array   $candidates
     * @param   array   $consumed     сюда пишутся имена использованных полей
     * @return  mixed
     */
    protected function body_value(array $payload, array $rule, array $candidates, array & $consumed)
    {
        foreach ($candidates as $field)
        {
            if (array_key_exists($field, $payload) AND is_scalar($payload[$field]) AND $payload[$field] !== '')
            {
                $consumed[] = $field;

                return $payload[$field];
            }
        }

        return NULL;
    }

    /**
     * Поля-идентификаторы: сначала конвенция «<сущность>_id», затем общий список.
     *
     * @param   array   $rule
     * @return  array
     */
    protected function id_fields(array $rule)
    {
        $entity = explode('.', $rule['entity_type']);
        $entity = end($entity);

        // uuid впереди: он не меняется и совпадает с идентификатором, которым
        // пользуется сервис модуля — история объекта не рвётся после правок
        $fields = array();

        if ( ! empty($rule['id_field']))
        {
            $fields[] = $rule['id_field'];
        }

        $fields[] = $entity.'_id';

        foreach ((array) $this->_journal->config('id_fields', array()) as $field)
        {
            $fields[] = $field;
        }

        return array_unique($fields);
    }

    /**
     * Тело события минус уже разобранные поля.
     *
     * @param   array   $payload
     * @param   array   $consumed
     * @return  array
     */
    protected function payload_tail(array $payload, array $consumed)
    {
        $tail = array();

        foreach ($payload as $field => $value)
        {
            if (in_array($field, $consumed, TRUE))
            {
                continue;
            }

            $tail[$field] = $value;
        }

        // Очень большое тело (выдача LLM, дерево BOM) в журнал не тянем
        if (mb_strlen(json_encode($tail, JSON_UNESCAPED_UNICODE)) > 2000)
        {
            return array('keys' => array_keys($tail), 'notice' => 'тело события больше 2000 символов');
        }

        return $tail;
    }

    /**
     * Аудируется ли чтение этой сущности (erp_119_audit_settings.track_reads).
     *
     * @param   string  $module
     * @param   string  $entity_type
     * @return  bool
     */
    public function tracks_reads($module, $entity_type)
    {
        $settings = $this->settings($module.'|'.$entity_type);

        return $settings AND (int) $settings['track_reads'] === 1;
    }

    /**
     * Включён ли аудит сущности (по умолчанию да — «не описан» ≠ «выключен»).
     *
     * @param   string  $module
     * @param   string  $entity_type
     * @return  bool
     */
    public function enabled($module, $entity_type)
    {
        $settings = $this->settings($module.'|'.$entity_type);

        return ( ! $settings) ? TRUE : (int) $settings['enabled'] === 1;
    }

    /**
     * Настройка аудита сущности из справочника.
     *
     * @param   string  $key
     * @return  array|null
     */
    protected function settings($key)
    {
        if ($this->_settings === NULL)
        {
            $this->_settings = array();

            try
            {
                foreach (DB::select()->from(Service_Erp119_Journal::SETTINGS)->execute() as $row)
                {
                    $this->_settings[$row['module'].'|'.$row['entity_type']] = $row;
                }
            }
            catch (Exception $e)
            {
                // Справочника может не быть до миграции — считаем, что аудит включён
                $this->_settings = array();
            }
        }

        return isset($this->_settings[$key]) ? $this->_settings[$key] : NULL;
    }

    /**
     * Имя пользователя по идентификатору (снимок на момент записи).
     *
     * @param   int     $user_id
     * @return  string|null
     */
    public function actor_name($user_id)
    {
        if ( ! $user_id)
        {
            return NULL;
        }

        if ( ! array_key_exists($user_id, $this->_names))
        {
            $this->_names[$user_id] = NULL;

            try
            {
                $row = DB::select('username', 'email')->from('users')
                    ->where('id', '=', $user_id)->execute()->current();

                if ($row)
                {
                    $this->_names[$user_id] = (string) ($row['username'] ? $row['username'] : $row['email']);
                }
            }
            catch (Exception $e)
            {
                // Таблицы пользователей может не быть на этапе миграций
            }
        }

        return $this->_names[$user_id];
    }
}
