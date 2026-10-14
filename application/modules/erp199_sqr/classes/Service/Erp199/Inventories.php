<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Инвентаризация по QR-наклейкам (erp199_sqr).
 *
 * Сессия инвентаризации — это «список ожидаемого» (активные наклейки в области
 * проверки) плюс факты сканирования. Терминал отмечает объекты, админка видит
 * прогресс и расхождения: найдено, не найдено, повреждено, чужое (наклейка не
 * из области этой сессии).
 *
 * Данные ожидаемого не копируются в отдельную таблицу: они отбираются из
 * реестра наклеек по области (scope) на момент запроса — иначе список
 * «застынет» и не увидит поставленное на учёт после открытия сессии.
 *
 * @package    Erp199
 * @category   Services
 */
class Service_Erp199_Inventories
{
    /** @var Service_Erp199_Inventories|null */
    protected static $_instance;

    /**
     * @return  Service_Erp199_Inventories
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
     * Открыть сессию инвентаризации.
     *
     * @param   array   $data  label, scope (kinds[], type_codes[], batch, note), comment
     * @param   integer $actor
     * @return  array
     */
    public function open(array $data, $actor = NULL)
    {
        $label = trim((string) Arr::get($data, 'label', ''));

        if ($label === '')
        {
            throw new Kohana_Exception('Укажите название инвентаризации');
        }

        $scope = Arr::get($data, 'scope', array());

        if (is_string($scope))
        {
            $decoded = json_decode($scope, TRUE);
            $scope   = is_array($decoded) ? $decoded : array();
        }

        if ( ! is_array($scope))
        {
            $scope = array();
        }

        $result = DB::insert('erp_199_inventories', array('code', 'label', 'scope', 'status', 'comment', 'started_by', 'started_at'))
            ->values(array(
                $this->next_code(),
                mb_substr($label, 0, 160),
                json_encode($scope, JSON_UNESCAPED_UNICODE),
                'open',
                Arr::get($data, 'comment') ?: NULL,
                $actor ?: NULL,
                DB::expr('CURRENT_TIMESTAMP'),
            ))
            ->execute();

        $id = is_array($result) ? (int) reset($result) : (int) $result;

        Service_Erp199_Tokens::instance()->log(NULL, 'inventory', $actor, 'open', array(
            'inventory_id' => $id, 'label' => $label, 'scope' => $scope,
        ));

        return $this->get($id);
    }

    /**
     * Закрыть сессию и зафиксировать итоги.
     *
     * @param   mixed   $ref
     * @param   integer $actor
     * @param   string  $comment
     * @return  array
     */
    public function close($ref, $actor = NULL, $comment = NULL)
    {
        $inventory = $this->get($ref);

        if ($inventory['status'] === 'closed')
        {
            return $inventory;
        }

        $progress = $this->progress($inventory['id']);

        DB::update('erp_199_inventories')
            ->set(array(
                'status'    => 'closed',
                'closed_by' => $actor ?: NULL,
                'closed_at' => DB::expr('CURRENT_TIMESTAMP'),
                'stats'     => json_encode($progress, JSON_UNESCAPED_UNICODE),
                'comment'   => $comment ?: $inventory['comment'],
            ))
            ->where('id', '=', (int) $inventory['id'])
            ->execute();

        $result = $this->get($inventory['id']);

        Service_Erp199_Tokens::instance()->log(NULL, 'inventory', $actor, 'close', array(
            'inventory_id' => (int) $inventory['id'], 'stats' => $progress,
        ), $comment);

        try
        {
            Event::fire('erp199.inventory.closed', array(
                'inventory_id' => (int) $inventory['id'],
                'uuid'         => (string) $inventory['uuid'],
                'code'         => (string) $inventory['code'],
                'stats'        => $progress,
                'actor_id'     => $actor ?: NULL,
            ));
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp199: событие закрытия инвентаризации недоступно — :error', array(':error' => $e->getMessage()));
        }

        return $result;
    }

    /**
     * Список сессий: сначала открытые, затем закрытые по дате.
     *
     * @param   integer $limit
     * @return  array
     */
    public function all($limit = 50)
    {
        $rows = DB::select()->from('erp_199_inventories')
            ->order_by(DB::expr("CASE WHEN status = 'open' THEN 0 ELSE 1 END"), 'ASC')
            ->order_by('id', 'DESC')
            ->limit(max(1, min(200, (int) $limit)))
            ->execute()
            ->as_array();

        foreach ($rows as &$row)
        {
            $row['scope'] = Service_Erp199_Cast::json($row['scope']);
            $row['stats'] = Service_Erp199_Cast::json($row['stats']);
        }
        unset($row);

        return $rows;
    }

    /**
     * Сессия по id или uuid.
     *
     * @param   mixed  $ref
     * @return  array
     */
    public function get($ref)
    {
        $query = DB::select()->from('erp_199_inventories');

        if (Erp::is_uuid((string) $ref))
        {
            $query->where('uuid', '=', (string) $ref);
        }
        else
        {
            $query->where('id', '=', (int) $ref);
        }

        $row = $query->execute()->current();

        if ( ! $row)
        {
            throw new Kohana_Exception('Инвентаризация :ref не найдена', array(':ref' => (string) $ref));
        }

        $row['scope'] = Service_Erp199_Cast::json($row['scope']);
        $row['stats'] = Service_Erp199_Cast::json($row['stats']);

        return $row;
    }

    /**
     * Первая открытая сессия (терминал работает с ней, пока её не закрыли).
     *
     * @return  array|null
     */
    public function open_session()
    {
        $row = DB::select()->from('erp_199_inventories')
            ->where('status', '=', 'open')
            ->order_by('id', 'DESC')
            ->limit(1)
            ->execute()
            ->current();

        return $row ? $this->get($row['id']) : NULL;
    }

    /**
     * Список записей сессии (что уже отмечено).
     *
     * @param   mixed   $ref
     * @param   integer $limit
     * @return  array
     */
    public function items($ref, $limit = 500)
    {
        $inventory = is_array($ref) ? $ref : $this->get($ref);

        return DB::select()->from('erp_199_inventory_items')
            ->where('inventory_id', '=', (int) $inventory['id'])
            ->order_by('scanned_at', 'DESC')
            ->limit(max(1, min(2000, (int) $limit)))
            ->execute()
            ->as_array();
    }

    /**
     * Отметить наклейку в сессии.
     *
     * @param   mixed   $ref      сессия
     * @param   array   $token    наклейка
     * @param   string  $status   found|missing|damaged|foreign
     * @param   integer $actor
     * @param   array   $meta     media_id, comment, geo_lat, geo_lon
     * @return  array
     */
    public function mark($ref, array $token, $status = 'found', $actor = NULL, array $meta = array())
    {
        $inventory = is_array($ref) ? $ref : $this->get($ref);

        if ($inventory['status'] !== 'open')
        {
            throw new Kohana_Exception('Инвентаризация :code уже закрыта', array(':code' => $inventory['code']));
        }

        if ( ! in_array($status, array('found', 'missing', 'damaged', 'foreign'), TRUE))
        {
            throw new Kohana_Exception('Недопустимая отметка инвентаризации «:status»', array(':status' => (string) $status));
        }

        $existing = DB::select('id')->from('erp_199_inventory_items')
            ->where('inventory_id', '=', (int) $inventory['id'])
            ->and_where('token_id', '=', (int) $token['id'])
            ->execute()
            ->current();

        $values = array(
            'inventory_id' => (int) $inventory['id'],
            'token_id'     => (int) $token['id'],
            'token_code'   => (string) $token['code'],
            'object_id'    => $token['object_id'] ?: (int) $token['target_id'] ?: NULL,
            'status'       => $status,
            'scanned_by'   => $actor ?: NULL,
            'scanned_at'   => DB::expr('CURRENT_TIMESTAMP'),
            'media_id'     => Arr::get($meta, 'media_id'),
            'comment'      => Arr::get($meta, 'comment'),
            'geo_lat'      => Arr::get($meta, 'geo_lat'),
            'geo_lon'      => Arr::get($meta, 'geo_lon'),
        );

        if ($existing)
        {
            unset($values['inventory_id']);
            DB::update('erp_199_inventory_items')->set($values)->where('id', '=', (int) $existing['id'])->execute();
        }
        else
        {
            DB::insert('erp_199_inventory_items', array_keys($values))->values(array_values($values))->execute();
        }

        Service_Erp199_Tokens::instance()->log($token, 'inventory', $actor, $status, array(
            'inventory_id' => (int) $inventory['id'],
            'code'         => (string) $inventory['code'],
        ), Arr::get($meta, 'comment'), $meta);

        return $this->progress($inventory['id']);
    }

    /**
     * Прогресс: ожидалось, отмечено, расхождения.
     *
     * @param   mixed  $ref
     * @return  array
     */
    public function progress($ref)
    {
        $inventory = is_array($ref) ? $ref : $this->get($ref);

        $expected_total = $this->expected_count($inventory['scope']);

        $counts = DB::select(
                array(DB::expr('COUNT(*)'), 'marked'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'found')"), 'found'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'missing')"), 'missing'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'damaged')"), 'damaged'),
                array(DB::expr("COUNT(*) FILTER (WHERE status = 'foreign')"), 'foreign')
            )
            ->from('erp_199_inventory_items')
            ->where('inventory_id', '=', (int) $inventory['id'])
            ->execute()
            ->current();

        $marked = (int) $counts['marked'];

        return array(
            'inventory_id' => (int) $inventory['id'],
            'code'         => (string) $inventory['code'],
            'label'        => (string) $inventory['label'],
            'status'       => (string) $inventory['status'],
            'expected'     => $expected_total,
            'marked'       => $marked,
            'found'        => (int) $counts['found'],
            'damaged'      => (int) $counts['damaged'],
            'missing'      => max(0, $expected_total - $marked),
            'foreign'      => (int) $counts['foreign'],
            'percent'      => $expected_total > 0 ? round(min(100, $marked / $expected_total * 100), 1) : ($marked ? 100 : 0),
        );
    }

    /**
     * Сколько всего наклеек в области проверки.
     *
     * @param   array  $scope
     * @return  integer
     */
    protected function expected_count(array $scope)
    {
        $sql    = 'SELECT COUNT(*) AS total FROM erp_199_tokens t WHERE t.status = \'active\'';
        $params = $this->scope_params($scope, $sql);

        $row = DB::query(Database::SELECT, $sql)->parameters($params)->execute()->current();

        return $row ? (int) $row['total'] : 0;
    }

    /**
     * Список ожидаемого по области сессии + отметки сканирования.
     *
     * @param   mixed   $ref
     * @param   integer $limit
     * @param   string  $query  поиск по коду/подписи
     * @return  array
     */
    public function expected($ref, $limit = 200, $query = NULL)
    {
        $inventory = is_array($ref) ? $ref : $this->get($ref);
        $limit     = max(1, min(500, (int) $limit));

        $sql    = 'SELECT t.* FROM erp_199_tokens t WHERE t.status = \'active\'';
        $params = $this->scope_params($inventory['scope'], $sql);

        if ($query !== NULL AND trim((string) $query) !== '')
        {
            $sql .= ' AND (t.code ILIKE :q OR t.target_label ILIKE :q OR t.label_note ILIKE :q)';
            $params[':q'] = '%'.trim((string) $query).'%';
        }

        $sql .= ' ORDER BY t.target_label NULLS LAST, t.code LIMIT :limit';
        $params[':limit'] = (int) $limit;

        $rows = DB::query(Database::SELECT, $sql)->parameters($params)->execute()->as_array();

        $marked = array();

        foreach ($this->items($inventory['id'], 2000) as $item)
        {
            $marked[(int) $item['token_id']] = $item;
        }

        foreach ($rows as &$row)
        {
            $row['scan'] = isset($marked[(int) $row['id']]) ? $marked[(int) $row['id']] : NULL;
        }
        unset($row);

        return $rows;
    }

    /**
     * Дописать в SQL и параметры область проверки (виды целей, партия печати).
     *
     * @param   array   $scope
     * @param   string  $sql    изменяется по ссылке
     * @return  array   параметры запроса
     */
    protected function scope_params(array $scope, &$sql)
    {
        $params = array();

        if ( ! empty($scope['kinds']) AND is_array($scope['kinds']))
        {
            $sql .= ' AND t.target_kind = ANY(:kinds)';
            $params[':kinds'] = '{'.implode(',', array_map('strval', $scope['kinds'])).'}';
        }

        if ( ! empty($scope['batch']))
        {
            $sql .= ' AND t.batch = :batch';
            $params[':batch'] = (string) $scope['batch'];
        }

        return $params;
    }

    /**
     * Код новой сессии: INV-ГГММДД-NNN.
     *
     * @return  string
     */
    protected function next_code()
    {
        $prefix = 'INV-'.date('ymd').'-';

        $row = DB::select(array(DB::expr('COUNT(*)'), 'total'))
            ->from('erp_199_inventories')
            ->where('code', 'LIKE', $prefix.'%')
            ->execute()
            ->current();

        $sequence = (int) $row['total'] + 1;

        do
        {
            $code = $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $exists = DB::select('id')->from('erp_199_inventories')->where('code', '=', $code)->execute()->current();
            $sequence++;
        }
        while ($exists);

        return $code;
    }
}
