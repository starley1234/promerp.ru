<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Сервис журнала действий (erp119_audit).
 *
 * Единственная точка записи. Правила модуля:
 *
 *  - журнал только на запись: в БД стоит триггер, UPDATE и DELETE запрещены;
 *  - сбой журнала не ломает бизнес-операцию: запись уходит в файл-страховку
 *    (`application/logs/audit-fallback.log`), откуда её забирает `replay()`
 *    при следующем запуске `erp119:sync`;
 *  - секреты (пароли, токены, ключи) не попадают в журнал ни в значении поля,
 *    ни в JSON-структуре произвольной вложенности;
 *  - в журнал идёт только то, что реально изменилось: сравнение «до/после»
 *    делает diff(), «строка 5» и «число 5» считаются одним значением.
 *
 * @package    Erp119
 * @category   Services
 */
class Service_Erp119_Journal
{
    /** @var string Таблица журнала */
    const TABLE = 'erp_119_audit_log';

    /** @var string Таблица указателей чтения событий */
    const CURSORS = 'erp_119_audit_cursors';

    /** @var string Таблица настроек аудита сущностей */
    const SETTINGS = 'erp_119_audit_settings';

    /** @var string Имя указателя «последнее прочитанное событие шины» */
    const CURSOR_EVENTS = 'events';

    /** @var Service_Erp119_Journal */
    protected static $_instance;

    /** @var array Конфигурация модуля */
    protected $_config;

    /** @var string Идентификатор текущего запроса (сквозная трассировка) */
    protected $_request_id;

    /** @var string|null Текст последней ошибки записи */
    protected $_last_error;

    /** @var array Контекст текущего запроса, добавляемый к записям */
    protected $_context = array();

    /** @var bool Признак «о сбое журнала уже предупреждали» */
    protected $_warned = FALSE;

    /** @var int Писать каждую N-ю запись при массовой синтетической загрузке. */
    protected $_sample_every = 1;

    /** @var int Счётчик записей для детерминированной выборки. */
    protected $_sample_counter = 0;

    /**
     * @return  Service_Erp119_Journal
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
        $config = Kohana::$config->load('erp119_audit');

        $this->_config = ($config instanceof Kohana_Config_Group) ? $config->as_array() : (array) $config;
    }

    /**
     * Настройка модуля (по ключу или весь конфиг).
     *
     * @param   string  $key
     * @param   mixed   $default
     * @return  mixed
     */
    public function config($key = NULL, $default = NULL)
    {
        if ($key === NULL)
        {
            return $this->_config;
        }

        return array_key_exists($key, $this->_config) ? $this->_config[$key] : $default;
    }

    /**
     * Разбор событий шины (erp208_events) — тот же singleton.
     *
     * @return  Service_Erp119_Mapper
     */
    public function mapper()
    {
        return Service_Erp119_Mapper::instance();
    }

    /**
     * @return  string
     */
    public function table()
    {
        return self::TABLE;
    }

    /**
     * @return  string|null
     */
    public function last_error()
    {
        return $this->_last_error;
    }

    // -------------------------------------------------------------------------
    // Кто, когда, откуда
    // -------------------------------------------------------------------------

    /**
     * Идентификатор запроса: одна операция пользователя — одна цепочка записей.
     *
     * @return  string
     */
    public function request_id()
    {
        if ($this->_request_id === NULL)
        {
            $this->_request_id = bin2hex(random_bytes(8));
        }

        return $this->_request_id;
    }

    /**
     * Откуда запись: web, api, cli, cron, system.
     *
     * @return  string
     */
    public function source()
    {
        if (PHP_SAPI === 'cli')
        {
            return 'cli';
        }

        $request = Request::$current;

        if ($request AND preg_match('#(^|/)api(/|$)#', (string) $request->uri()))
        {
            return 'api';
        }

        return 'web';
    }

    /**
     * Текущий запрос (для колонки route).
     *
     * @return  string|null
     */
    public function route()
    {
        $request = Request::$current;

        if ( ! $request)
        {
            return (PHP_SAPI === 'cli') ? 'cli' : NULL;
        }

        $route = (string) $request->uri();

        if ($query = $request->query())
        {
            $route .= '?'.http_build_query($query);
        }

        return $route;
    }

    /**
     * Кто выполняет действие (в CLI и cron пользователя может не быть).
     *
     * @return  array   array('id' => int|null, 'name' => string|null)
     */
    public function actor()
    {
        $actor = array('id' => NULL, 'name' => NULL);

        try
        {
            if ( ! class_exists('Auth'))
            {
                return $actor;
            }

            $auth = Auth::instance();

            if ($auth->logged_in())
            {
                $user = $auth->get_user();

                if ($user)
                {
                    $actor['id']   = (int) $user->id;
                    $actor['name'] = (string) (isset($user->username) ? $user->username : $user->email);
                }
            }
        }
        catch (Exception $e)
        {
            // Сессии может не быть — это норма для CLI
        }

        return $actor;
    }

    /**
     * IP клиента.
     *
     * @return  string|null
     */
    public function actor_ip()
    {
        if (PHP_SAPI === 'cli' OR empty($_SERVER['REMOTE_ADDR']))
        {
            return NULL;
        }

        return substr((string) $_SERVER['REMOTE_ADDR'], 0, 45);
    }

    /**
     * Контекст текущего запроса (модуль, страница, признак теста).
     *
     * @param   array|null  $context
     * @return  void
     */
    public function set_context_array(?array $context = NULL)
    {
        $this->_context = ($context === NULL) ? array() : $context;
    }

    /**
     * Контекст текущего запроса — статическая форма (удобна контроллерам).
     *
     * @param   array|null  $context
     * @return  void
     */
    public static function set_context(?array $context = NULL)
    {
        self::instance()->set_context_array($context);
    }

    /**
     * Сбросить контекст запроса (в конце обработки, в задачах, в тестах).
     *
     * @return  void
     */
    public static function reset_context()
    {
        self::instance()->set_context_array(NULL);
    }

    // -------------------------------------------------------------------------
    // Сравнение значений и маскирование секретов
    // -------------------------------------------------------------------------

    /**
     * Что изменилось: список полей «было / стало».
     *
     * Строка «5» и число 5 — одно значение, одинаковые массивы — не изменение,
     * NULL и пустая строка — разные значения. Список ограничен: при массовой
     * правке (импорт, миграция) в журнал идёт 50 полей и пометка «*».
     *
     * @param   array   $before
     * @param   array   $after
     * @return  array   array(array('field', 'old', 'new'), …)
     */
    public function diff(array $before, array $after)
    {
        $changes = array();

        foreach (array_keys($before + $after) as $field)
        {
            $old = array_key_exists($field, $before) ? $before[$field] : NULL;
            $new = array_key_exists($field, $after) ? $after[$field] : NULL;

            if ($this->_same($old, $new))
            {
                continue;
            }

            $changes[] = array(
                'field' => $field,
                'old'   => $this->is_secret($field) ? $this->_mask() : $this->_plain($old),
                'new'   => $this->is_secret($field) ? $this->_mask() : $this->_plain($new),
            );
        }

        $limit = (int) $this->config('max_changes', 50);

        if (count($changes) > $limit)
        {
            $changes = array_slice($changes, 0, $limit);
            $changes[] = array('field' => '*', 'old' => NULL, 'new' => NULL);
        }

        return $changes;
    }

    /**
     * Значение для журнала: массивы и объекты остаются структурой, скаляры — как есть.
     *
     * @param   mixed   $value
     * @return  mixed
     */
    protected function _plain($value)
    {
        if (is_object($value))
        {
            return get_object_vars($value);
        }

        return $value;
    }

    /**
     * Одинаковые ли значения.
     *
     * @param   mixed   $old
     * @param   mixed   $new
     * @return  bool
     */
    protected function _same($old, $new)
    {
        if (is_array($old) OR is_array($new))
        {
            return json_encode($old) === json_encode($new);
        }

        if (is_bool($old) OR is_bool($new))
        {
            return (bool) $old === (bool) $new;
        }

        if ($old === NULL OR $new === NULL)
        {
            return $old === $new;
        }

        if (is_numeric($old) AND is_numeric($new))
        {
            return (string) $old === (string) $new;
        }

        return (string) $old === (string) $new;
    }

    /**
     * Секретное ли поле (маскируется и в changes, и в context).
     *
     * Правило широкое намеренно: «лишняя» маска дешевле утечки токена.
     *
     * @param   string  $field
     * @return  bool
     */
    public function is_secret($field)
    {
        $field = mb_strtolower((string) $field);

        if ($field === '')
        {
            return FALSE;
        }

        $redact = $this->config('redact');

        if (in_array($field, $redact['keys'], TRUE))
        {
            return TRUE;
        }

        return (bool) preg_match($redact['pattern'], $field);
    }

    /**
     * @return  string
     */
    protected function _mask()
    {
        $redact = $this->config('redact');

        return $redact['mask'];
    }

    /**
     * Маскирование секретов в структуре произвольной вложенности.
     *
     * @param   array   $data
     * @param   int     $depth
     * @return  array
     */
    public function redact(array $data, $depth = 0)
    {
        if ($depth > 5)
        {
            return array('…' => $this->_mask());
        }

        $clean = array();

        foreach ($data as $key => $value)
        {
            if (is_array($value))
            {
                $clean[$key] = $this->redact($value, $depth + 1);
            }
            elseif ($this->is_secret($key))
            {
                $clean[$key] = $this->_mask();
            }
            else
            {
                $clean[$key] = $this->_plain($value);
            }
        }

        return $clean;
    }

    // -------------------------------------------------------------------------
    // Запись
    // -------------------------------------------------------------------------

    /**
     * Включить детерминированную выборку для массовой загрузки.
     * Значение 1 возвращает полный аудит. Режим действует только в текущем
     * PHP-запросе и используется генератором синтетики, не обычными операциями.
     *
     * @param int $every
     * @return void
     */
    public function sample_writes($every = 1)
    {
        $this->_sample_every = max(1, (int) $every);
        $this->_sample_counter = 0;
    }

    /**
     * Записать событие в журнал.
     *
     * Сбой записи не выбрасывается наружу: строка уходит в файл-страховку,
     * а бизнес-операция продолжается (журнал не имеет права её ломать).
     *
     * @param   array   $entry
     * @return  array   записанная строка (с ключом id — NULL, если ушла в файл)
     */
    public function record(array $entry)
    {
        $row = $this->normalize($entry);
        $this->_last_error = NULL;

        $this->_sample_counter++;
        if ($this->_sample_every > 1 AND (($this->_sample_counter - 1) % $this->_sample_every) !== 0)
        {
            $row['id'] = NULL;
            return $row;
        }

        try
        {
            list($sql, $params) = $this->_insert(array($row));

            list($id) = DB::query(Database::INSERT, $sql)->parameters($params)->execute();

            $row['id'] = ($id === NULL) ? NULL : (int) $id;
        }
        catch (Exception $e)
        {
            $row['id'] = NULL;

            $this->fallback($row, $e);

            if ($this->strict())
            {
                // Контуры, где потеря записи недопустима (тесты, аудит доступа)
                throw $e;
            }
        }

        return $row;
    }

    /**
     * Записать пачку записей одним запросом (перенос шины, регламент, replay).
     *
     * @param   array   $entries
     * @return  int     сколько строк принято
     */
    public function record_many(array $entries)
    {
        if ( ! $entries)
        {
            return 0;
        }

        $rows = array();

        foreach ($entries as $entry)
        {
            $rows[] = $this->normalize($entry);
        }

        return $this->write_rows($rows);
    }

    /**
     * Записать уже разобранные строки (файл-страховка, replay).
     *
     * @param   array   $rows
     * @return  int
     */
    public function write_rows(array $rows)
    {
        $written = 0;

        // 500 строк на запрос: и быстрее, и параметров меньше лимита драйвера
        foreach (array_chunk($rows, 500) as $chunk)
        {
            try
            {
                list($sql, $params) = $this->_insert($chunk);

                DB::query(Database::INSERT, $sql)->parameters($params)->execute();

                $written += count($chunk);
            }
            catch (Exception $e)
            {
                $this->fallback($chunk, $e);

                if ($this->strict())
                {
                    throw $e;
                }
            }
        }

        return $written;
    }

    /**
     * Записать изменение объекта (сравнение «до/после» внутри).
     *
     * @param   string  $entity_type
     * @param   string  $entity_id
     * @param   string  $action
     * @param   array   $before
     * @param   array   $after
     * @param   array   $options    module, entity_label, message, severity, context, force
     * @return  array|null          запись либо NULL, если менять было нечего
     */
    public function change($entity_type, $entity_id, $action, array $before, array $after, array $options = array())
    {
        $changes = $this->diff($before, $after);

        if ( ! $changes AND $action === 'update' AND empty($options['force']))
        {
            // Пустая правка (пересохранение формы без изменений) — не событие
            return NULL;
        }

        $entry = array(
            'entity_type' => $entity_type,
            'entity_id'   => (string) $entity_id,
            'action'      => $action,
            'changes'     => $changes,
        );

        foreach ($options as $key => $value)
        {
            $entry[$key] = $value;
        }

        if (empty($entry['message']))
        {
            $label = isset($entry['entity_label']) ? $entry['entity_label'] : $entity_type;

            $entry['message'] = Erp119_Ui::action_label($action).': '.$label;
        }

        return $this->record($entry);
    }

    /**
     * Запись из «старого» Audit::log().
     *
     * За годы накопилось несколько разных порядков аргументов (первоначальная
     * заглушка просто игнорировала их), поэтому вызывающий код не переписывается:
     * разбираем все встречающиеся виды вызовов.
     *
     *   1. Audit::log('email_direct_send_attempt', array('to' => …));
     *   2. Audit::log('module_manager', 'status_changed', 42, array(…));
     *   3. Audit::log('erp221.deal.updated', 7, array('после'), array('до'));
     *   4. Audit::log('erp301.counterparty', 'created', 'uuid', NULL);
     *
     * @param   string  $category
     * @param   mixed   $action
     * @param   mixed   $object_id
     * @param   mixed   $data
     * @return  array|null
     */
    public function legacy($category, $action = NULL, $object_id = NULL, $data = NULL)
    {
        $category = (string) $category;

        $entry = array(
            'module'      => preg_match('/^(erp\d+)/', $category, $matches) ? $matches[1] : 'app',
            'entity_type' => $category,
            'entity_id'   => '',
            'action'      => 'other',
            'severity'    => 'notice',
            'context'     => array('legacy_call' => TRUE, 'category' => $category),
        );

        // Вид 3: «до» и «после» (идентификатор числом, оба значения массивами)
        if (is_numeric($action) AND is_array($object_id) AND is_array($data))
        {
            $entry['entity_id'] = (string) $action;
            $entry['action']    = 'update';
            $entry['changes']   = $this->diff($data, $object_id);
            $entry['severity']  = $this->severity('update');

            return $this->record($entry);
        }

        // Вид 1: второй аргумент — данные
        if (is_array($action) AND $object_id === NULL AND $data === NULL)
        {
            $entry['entity_id'] = $this->id_from($action);
            $entry['context']['data'] = $this->redact($action);

            return $this->record($entry);
        }

        $verb = (string) $action;

        $entry['action']   = $this->normalize_action($verb);
        $entry['severity'] = (strpos($verb, 'status') !== FALSE OR $verb === 'status_changed')
            ? $this->severity('status')
            : $this->severity($entry['action']);

        if (is_array($object_id))
        {
            // Данные об объекте переданы третьим аргументом
            $entry['context']['data'] = $this->redact($object_id);
            $entry['entity_id']       = $this->id_from($object_id);
        }
        else
        {
            $entry['entity_id'] = ($object_id === NULL) ? '' : (string) $object_id;
        }

        if ($data !== NULL)
        {
            $entry['context']['data'] = is_array($data) ? $this->redact($data) : (string) $data;
        }

        return $this->record($entry);
    }

    /**
     * Идентификатор объекта из массива данных.
     *
     * @param   array   $data
     * @return  string
     */
    public function id_from(array $data)
    {
        foreach ($this->config('id_fields') as $field)
        {
            if (array_key_exists($field, $data) AND is_scalar($data[$field]))
            {
                return (string) $data[$field];
            }
        }

        return '';
    }

    /**
     * «Человеческое» имя объекта из массива данных.
     *
     * @param   array   $data
     * @return  string|null
     */
    public function label_from(array $data)
    {
        foreach ($this->config('label_fields') as $field)
        {
            if ( ! empty($data[$field]) AND is_string($data[$field]))
            {
                return $data[$field];
            }
        }

        return NULL;
    }

    /**
     * Известные действия журнала: значения правил глаголов и таблица важности.
     *
     * @param   array   $verbs
     * @return  array
     */
    protected function _known_actions(array $verbs)
    {
        return array_unique(array_merge(
            array_values($verbs),
            array_keys((array) $this->config('severity', array())),
            array('other', 'create', 'update', 'delete', 'read', 'status', 'approve', 'send', 'fail', 'publish')
        ));
    }

    /**
     * Глагол → действие журнала (created → create, approved → approve, sent → send).
     *
     * @param   string  $verb
     * @return  string
     */
    public function normalize_action($verb)
    {
        $verb = mb_strtolower(trim((string) $verb));
        $verb = preg_replace('/[^a-z0-9_]/', '_', $verb);

        $verbs = $this->config('verbs', array());

        if (isset($verbs[$verb]))
        {
            return $verbs[$verb];
        }

        // Уже готовое действие не «глазируется» заново: status ≠ statu.
        // Иначе повторная нормализация (rule → normalize) портит результат.
        if (in_array($verb, $this->_known_actions($verbs), TRUE))
        {
            return $verb;
        }

        // Составные глаголы («status_changed») разбираются по последнему слову
        if (strpos($verb, '_') !== FALSE)
        {
            $parts = explode('_', $verb);
            $last  = array_pop($parts);

            if (isset($verbs[$last]))
            {
                return $verbs[$last];
            }

            return $this->normalize_action($last);
        }

        // Общее правило: «-ed» → «-e», «-ed» с удвоением → без него
        if (substr($verb, -3) === 'ied')
        {
            return substr($verb, 0, -3).'y';
        }

        if (substr($verb, -2) === 'ed')
        {
            $stem = substr($verb, 0, -2);

            if (preg_match('/([a-z])\1$/', $stem, $matches))
            {
                return substr($stem, 0, -1);
            }

            return substr($verb, 0, -1);
        }

        if (substr($verb, -1) === 's')
        {
            return substr($verb, 0, -1);
        }

        if ($verb === '')
        {
            return 'other';
        }

        return $verb;
    }

    /**
     * Важность действия.
     *
     * @param   string  $action
     * @return  string
     */
    public function severity($action)
    {
        $map = $this->config('severity', array());

        return isset($map[$action]) ? $map[$action] : $this->config('severity_default', 'info');
    }

    // -------------------------------------------------------------------------
    // Строка таблицы
    // -------------------------------------------------------------------------

    /**
     * Дополнить запись служебными полями и обрезать по длинам из конфига.
     *
     * @param   array   $entry
     * @return  array   строка таблицы журнала
     */
    public function normalize(array $entry)
    {
        $actor   = $this->actor();
        $lengths = $this->config('lengths', array());

        $action = isset($entry['action']) ? $entry['action'] : 'other';

        $row = array(
            'occurred_at'  => isset($entry['occurred_at']) ? (string) $entry['occurred_at'] : date('c'),
            'module'       => isset($entry['module']) ? (string) $entry['module'] : $this->module_of($entry),
            'entity_type'  => (string) $entry['entity_type'],
            'entity_id'    => isset($entry['entity_id']) ? (string) $entry['entity_id'] : '',
            'entity_label' => (isset($entry['entity_label']) AND $entry['entity_label'] !== '') ? (string) $entry['entity_label'] : NULL,
            'action'       => $this->normalize_action($action),
            'severity'     => isset($entry['severity']) ? (string) $entry['severity'] : $this->severity($this->normalize_action($action)),
            'actor_id'     => isset($entry['actor_id']) ? $entry['actor_id'] : $actor['id'],
            'actor_name'   => isset($entry['actor_name']) ? $entry['actor_name'] : $actor['name'],
            'actor_ip'     => isset($entry['actor_ip']) ? $entry['actor_ip'] : $this->actor_ip(),
            'source'       => isset($entry['source']) ? (string) $entry['source'] : $this->source(),
            'request_id'   => isset($entry['request_id']) ? (string) $entry['request_id'] : $this->request_id(),
            // Номер события шины, из которого выведена запись (NULL для
            // записей, сделанных кодом напрямую). По нему перенос из шины
            // идемпотентен: одно событие — ровно одна запись журнала.
            'event_id'     => (isset($entry['event_id']) AND $entry['event_id'] !== NULL) ? (int) $entry['event_id'] : NULL,
            'route'        => isset($entry['route']) ? (string) $entry['route'] : $this->route(),
            'message'      => (isset($entry['message']) AND $entry['message'] !== '') ? (string) $entry['message'] : NULL,
            'changes'      => isset($entry['changes']) ? json_encode($this->clean_changes(isset($entry['changes']) ? $entry['changes'] : array()), JSON_UNESCAPED_UNICODE) : '[]',
            'context'      => json_encode($this->clean_context($entry), JSON_UNESCAPED_UNICODE),
        );

        // Обрезка строк: одна слишком длинная запись не должна ломать журнал
        foreach ($lengths as $field => $length)
        {
            if (isset($row[$field]) AND $row[$field] !== NULL AND is_string($row[$field]) AND mb_strlen($row[$field]) > $length)
            {
                $row[$field] = mb_substr($row[$field], 0, $length);
            }
        }

        $row['search_text'] = $this->search_text($row);

        return $row;
    }

    /**
     * Модуль-владелец записи: явно, из префикса сущности или «app».
     *
     * @param   array   $entry
     * @return  string
     */
    public function module_of(array $entry)
    {
        if (preg_match('/^(erp\d+)/', (string) $entry['entity_type'], $matches))
        {
            return $matches[1];
        }

        $maps = $this->config('category_modules', array());

        return isset($maps[$entry['entity_type']]) ? $maps[$entry['entity_type']] : 'app';
    }

    /**
     * Изменения приводятся к списку и маскируются.
     *
     * @param   mixed   $changes
     * @return  array
     */
    public function clean_changes($changes)
    {
        if ( ! is_array($changes))
        {
            return array();
        }

        $clean = array();

        foreach ($changes as $change)
        {
            if ( ! is_array($change) OR ! isset($change['field']))
            {
                continue;
            }

            $field = (string) $change['field'];
            $secret = $this->is_secret($field);

            $clean[] = array(
                'field' => $field,
                'old'   => $secret ? $this->_mask() : (array_key_exists('old', $change) ? $this->_plain($change['old']) : NULL),
                'new'   => $secret ? $this->_mask() : (array_key_exists('new', $change) ? $this->_plain($change['new']) : NULL),
            );
        }

        return $clean;
    }

    /**
     * Контекст записи: переданный плюс контекст текущего запроса, без секретов.
     *
     * @param   array   $entry
     * @return  array
     */
    public function clean_context(array $entry)
    {
        $context = array();

        if ( ! empty($entry['context']) AND is_array($entry['context']))
        {
            $context = $entry['context'];
        }
        elseif ( ! empty($entry['context']) AND is_string($entry['context']))
        {
            $context = array('data' => $entry['context']);
        }

        return $this->redact(array_merge($this->_context, $context));
    }

    /**
     * Строка для поиска: по ней ищет фильтр «Поиск» в ленте.
     *
     * Регистр складывается в PHP (mb_strtolower): локаль кластера C, поэтому
     * lower()/ILIKE в базе кириллицу не складывают.
     *
     * @param   array   $row
     * @return  string
     */
    public function search_text(array $row)
    {
        $parts = array(
            isset($row['entity_label']) ? $row['entity_label'] : '',
            isset($row['message']) ? $row['message'] : '',
            isset($row['actor_name']) ? $row['actor_name'] : '',
            isset($row['entity_id']) ? $row['entity_id'] : '',
            isset($row['entity_type']) ? $row['entity_type'] : '',
            isset($row['module']) ? $row['module'] : '',
            isset($row['action']) ? $row['action'] : '',
            isset($row['request_id']) ? $row['request_id'] : '',
        );

        return mb_substr(mb_strtolower(implode(' ', array_filter($parts, 'strlen'))), 0, 4000);
    }

    /**
     * SQL вставки одной строки или пачки.
     *
     * Параметры именуются `:v0`, `:v1`, … (`:v0_0` для пачки): драйвер
     * подставляет их через strtr, поэтому ключи обязаны совпадать с
     * плейсхолдерами целиком, вместе с двоеточием.
     *
     * @param   array   $rows   строка либо список строк
     * @return  array   array(SQL, параметры)
     */
    protected function _insert(array $rows)
    {
        $many = isset($rows[0]) AND is_array($rows[0]);

        $columns = array_keys($many ? $rows[0] : $rows);
        $groups  = array();
        $params  = array();

        foreach ($many ? $rows : array($rows) as $r => $row)
        {
            $placeholders = array();

            foreach ($columns as $i => $column)
            {
                $key = ':v'.($many ? $r.'_'.$i : $i);

                $placeholders[] = in_array($column, array('changes', 'context'), TRUE)
                    ? 'CAST('.$key.' AS jsonb)'
                    : $key;

                $params[$key] = array_key_exists($column, $row) ? $row[$column] : NULL;
            }

            $groups[] = '('.implode(', ', $placeholders).')';
        }

        $sql = 'INSERT INTO '.self::TABLE.' ('.implode(', ', $columns).')'
             .' VALUES '.implode(', ', $groups)
             .' ON CONFLICT DO NOTHING'
             .' RETURNING id';

        return array($sql, $params);
    }

    // -------------------------------------------------------------------------
    // Указатель чтения событий шины
    // -------------------------------------------------------------------------

    /**
     * Какие события шины уже перенесены в журнал.
     *
     * Нужен переносу (`erp119:sync`), чтобы повторное чтение с начала —
     * например, после правки правил разбора — не задваивало записи.
     *
     * @param   array   $ids    номера событий шины
     * @return  array           array(id => TRUE)
     */
    public function imported_event_ids(array $ids)
    {
        $ids = array_filter(array_map('intval', $ids));

        if ( ! $ids)
        {
            return array();
        }

        try
        {
            $rows = DB::select('event_id')->from(self::TABLE)
                ->where('event_id', 'IN', array_values($ids))
                ->execute()
                ->as_array();
        }
        catch (Exception $e)
        {
            // Столбца ещё нет (не применена миграция 003) — считаем, что не перенесено
            return array();
        }

        $known = array();

        foreach ($rows as $row)
        {
            $known[(int) $row['event_id']] = TRUE;
        }

        return $known;
    }

    /**
     * До какого события шина уже разобрана журналом.
     *
     * @param   string  $name
     * @return  int
     */
    public function cursor($name = self::CURSOR_EVENTS)
    {
        try
        {
            $row = DB::select('last_event_id')->from(self::CURSORS)
                ->where('name', '=', $name)->execute()->current();
        }
        catch (Exception $e)
        {
            return 0;
        }

        return $row ? (int) $row['last_event_id'] : 0;
    }

    /**
     * Двинуть указатель (только после успешной записи в журнал).
     *
     * @param   int     $position
     * @param   string  $name
     * @return  void
     */
    public function set_cursor($position, $name = self::CURSOR_EVENTS)
    {
        $position = max(0, (int) $position);

        $updated = DB::update(self::CURSORS)
            ->set(array('last_event_id' => $position, 'updated_at' => date('c')))
            ->where('name', '=', $name)
            ->execute();

        if ( ! $updated)
        {
            DB::insert(self::CURSORS, array('name', 'last_event_id', 'updated_at'))
                ->values(array($name, $position, date('c')))
                ->execute();
        }
    }

    // -------------------------------------------------------------------------
    // Подписи для интерфейса
    // -------------------------------------------------------------------------

    /**
     * Человеческое название сущности: «erp228 / tech_card» → «Технологическая карта».
     *
     * Источник — справочник аудита `erp_119_audit_settings` (его ведёт модуль),
     * запасной вариант — название модуля из конфига и сам тип сущности.
     *
     * @param   string  $module
     * @param   string  $entity_type
     * @return  string
     */
    public function label_for($module, $entity_type = NULL)
    {
        static $labels = NULL;

        if ($labels === NULL)
        {
            $labels = array();

            try
            {
                foreach (DB::select('module', 'entity_type', 'label')->from(self::SETTINGS)->execute() as $row)
                {
                    $labels[$row['module'].'|'.$row['entity_type']] = $row['label'];
                }
            }
            catch (Exception $e)
            {
                $labels = array();
            }
        }

        if ($entity_type !== NULL AND isset($labels[$module.'|'.$entity_type]))
        {
            return $labels[$module.'|'.$entity_type];
        }

        $module_labels = $this->config('module_labels', array());

        if ($entity_type === NULL AND isset($module_labels[$module]))
        {
            return $module_labels[$module];
        }

        if (isset($module_labels[$module]))
        {
            return $module_labels[$module].' · '.$entity_type;
        }

        return (string) $entity_type;
    }

    // -------------------------------------------------------------------------
    // Файл-страховка
    // -------------------------------------------------------------------------

    /**
     * Строгий режим: сбой записи в журнал прерывает операцию, а не уходит в файл.
     *
     * @return  bool
     */
    public function strict()
    {
        $fallback = $this->config('fallback', array());

        return ! empty($fallback['throw']);
    }

    /**
     * Путь к файлу-страховке.
     *
     * @return  string
     */
    public function fallback_path()
    {
        $fallback = $this->config('fallback', array());

        return APPPATH.'logs/'.$fallback['file'];
    }

    /**
     * Положить строку (или пачку строк) в файл-страховку.
     *
     * @param   array           $row   строка таблицы либо список строк
     * @param   Exception|null  $e
     * @return  bool
     */
    protected function fallback(array $row, ?Exception $e = NULL)
    {
        $fallback = $this->config('fallback', array());

        $this->_last_error = ($e === NULL) ? $this->_last_error : $e->getMessage();

        if (empty($fallback['enabled']))
        {
            return FALSE;
        }

        $file = $this->fallback_path();

        if ( ! is_dir(dirname($file)))
        {
            @mkdir(dirname($file), 0775, TRUE);
        }

        // Ограничение размера: раздувшийся файл становится «импортированным»
        if (is_file($file) AND filesize($file) > $fallback['limit_bytes'])
        {
            @rename($file, $file.'.imported.'.date('YmdHis'));
        }

        $rows  = (isset($row[0]) AND is_array($row[0])) ? $row : array($row);
        $lines = '';

        foreach ($rows as $item)
        {
            $lines .= json_encode($item, JSON_UNESCAPED_UNICODE).PHP_EOL;
        }

        $ok = (bool) @file_put_contents($file, $lines, FILE_APPEND | LOCK_EX);

        if ( ! $this->_warned)
        {
            $this->_warned = TRUE;

            Log::instance()->add(Log::WARNING, 'erp119: запись в журнал не удалась (:error), данные в :file', array(
                ':error' => ($e === NULL) ? 'пакет не принят' : $e->getMessage(),
                ':file'  => $file,
            ));
        }

        return $ok;
    }

    /**
     * Вернуть записи из файла-страховки в журнал.
     *
     * Разобранный файл переименовывается в `*.imported.*` — повторный запуск
     * его уже не читает, а «задвоить» журнал невозможно.
     *
     * @return  array   array('files' => int, 'imported' => int, 'failed' => int)
     */
    public function replay()
    {
        $result = array('file' => $this->fallback_path(), 'files' => 0, 'imported' => 0, 'failed' => 0);

        foreach (glob($this->fallback_path().'*') as $file)
        {
            if ( ! is_file($file) OR strpos($file, '.imported.') !== FALSE)
            {
                continue;
            }

            $rows = array();

            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line)
            {
                $row = json_decode($line, TRUE);

                if (is_array($row) AND ! empty($row['entity_type']) AND isset($row['occurred_at']))
                {
                    $rows[] = $row;
                }
                else
                {
                    $result['failed']++;
                }
            }

            if ( ! $rows)
            {
                @rename($file, $file.'.imported.'.date('YmdHis'));

                continue;
            }

            $written = $this->write_rows($rows);

            if ($written === count($rows))
            {
                @rename($file, $file.'.imported.'.date('YmdHis'));

                $result['files']++;
                $result['imported'] += $written;
            }
            else
            {
                $result['failed'] += count($rows) - $written;
            }
        }

        return $result;
    }
}
