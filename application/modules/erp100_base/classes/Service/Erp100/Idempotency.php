<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Идемпотентность изменяющих запросов JSON API (ядро, erp100_base).
 *
 * Зачем. У каждого модуля есть точка входа для машин (`Controller/<модуль>/Api.php`),
 * и повтор запроса там означал повтор операции: обрыв связи, повтор LLM-агента
 * или двойной клик по кнопке создавали второй документ — вторую заявку, второй
 * заказ, вторую приёмку. Слой MCP от этого защищён (`erp_804_mcp_calls` с
 * уникальным `idempotency_key`), обычный JSON API — нет.
 *
 * Как это работает (и почему снаружи не нужно ничего менять).
 *
 *  1. Клиент присылает заголовок `Idempotency-Key: <строка>` (или параметр
 *     `idempotency_key`). Без ключа поведение прежнее: защита не включается —
 *     иначе сломались бы веб-формы.
 *  2. Первый запрос с ключом выполняется, а его ответ (код, тело, тип) и отпечаток
 *     тела запроса сохраняются в `erp_100_api_idempotency`.
 *  3. Повтор с тем же ключом и тем же телом получает **сохранённый ответ**:
 *     операция не выполняется второй раз. Заголовок `Idempotent-Replay: true`
 *     говорит клиенту, что это повтор.
 *  4. Тот же ключ с другим телом — это ошибка клиента, отвечаем 409 `conflict`:
 *     молча выполнить другое действие по чужому ключу нельзя.
 *  5. Параллельный запрос с тем же ключом (первый ещё выполняется) получает 409
 *     `in_progress`. Зависшую запись (обработчик упал вместе с процессом) можно
 *     переиспользовать через `STALE_SECONDS`.
 *
 * Записи короткоживущие (`expires_at`, по умолчанию 24 часа): это защита от
 * повтора, а не журнал — журнал ведёт erp119.
 *
 * @package    Erp100
 * @category   Services
 */
class Service_Erp100_Idempotency
{
    /** @var string Таблица ключей */
    const TABLE = 'erp_100_api_idempotency';

    /** @var int Максимальная длина ключа (как в колонке) */
    const MAX_KEY_LENGTH = 128;

    /** @var int Сколько часов хранить ответ */
    const TTL_HOURS = 24;

    /** @var int Через сколько секунд считать незавершённую запись зависшей */
    const STALE_SECONDS = 300;

    /** @var int Ограничение на сохраняемый ответ (байт): больше — не храним */
    const MAX_BODY = 262144;

    /** @var Service_Erp100_Idempotency|null */
    protected static $_instance;

    /**
     * @return Service_Erp100_Idempotency
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
     * Есть ли таблица ключей (модуль мог быть подключён без миграций).
     *
     * Проверка кэшируется на время запроса: без неё каждый изменяющий запрос
     * платил бы лишним обращением к схеме.
     *
     * @var bool|null
     */
    protected $_table_exists = NULL;

    /**
     * @return bool
     */
    public function available()
    {
        if ($this->_table_exists === NULL)
        {
            try
            {
                $this->_table_exists = (bool) DB::query(Database::SELECT,
                    "SELECT to_regclass('public.".self::TABLE."') IS NOT NULL AS ok")
                    ->execute()->get('ok');
            }
            catch (Exception $e)
            {
                // Нет соединения или таблицы — молча выключаем защиту: API обязан
                // работать, а не падать из-за необязательной оптимизации.
                $this->_table_exists = FALSE;
            }
        }

        return $this->_table_exists;
    }

    /**
     * Ключ идемпотентности из заголовка или параметра запроса.
     *
     * Заголовок — основной способ (`Idempotency-Key`), потому что он не входит в
     * тело документа и не мешает валидации. Параметр оставлен для клиентов,
     * которым заголовки недоступны (простые веб-формы, curl-скрипты).
     *
     * @param   string|null $header   значение заголовка Idempotency-Key
     * @param   mixed       $param    значение параметра idempotency_key
     * @return  string|null
     */
    public function key($header = NULL, $param = NULL)
    {
        $key = ($header !== NULL AND $header !== '') ? $header : $param;

        if ($key === NULL OR $key === '' OR is_array($key))
        {
            return NULL;
        }

        $key = trim((string) $key);

        if ($key === '')
        {
            return NULL;
        }

        if (strlen($key) > self::MAX_KEY_LENGTH)
        {
            $key = substr($key, 0, self::MAX_KEY_LENGTH);
        }

        return $key;
    }

    /**
     * Отпечаток запроса: одинаковый ключ с разным телом — конфликт.
     *
     * Берём метод, путь и тело (которое уже отсортировано контроллером),
     * без служебных параметров вроде CSRF.
     *
     * @param   string $method
     * @param   string $uri
     * @param   array  $params
     * @return  string sha1
     */
    public function fingerprint($method, $uri, array $params = array())
    {
        unset($params['idempotency_key'], $params['csrf_token'], $params['_token']);

        ksort($params);

        return sha1(strtoupper((string) $method).' '.$uri.' '.json_encode($params, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Начать операцию: занять ключ или вернуть то, что уже известно.
     *
     * Возвращает:
     *  - `new`         — ключ занят этим запросом, выполняйте действие;
     *  - `replay`      — операция уже выполнена, отдайте `response_body`;
     *  - `conflict`    — ключ уже использован с другим телом (или другим пользователем);
     *  - `in_progress` — по этому ключу уже идёт запрос.
     *
     * @param   string      $scope     границы ключа: `erpNNN:контроллер:действие`
     * @param   string      $key
     * @param   int|null    $user_id
     * @param   string      $hash      отпечаток запроса
     * @param   array       $meta      method, uri, request (тело для разбора)
     * @return  array       state, row, id
     */
    public function begin($scope, $key, $user_id, $hash, array $meta = array())
    {
        if ( ! $this->available())
        {
            return array('state' => 'new', 'id' => NULL, 'row' => NULL);
        }

        $existing = $this->_find($scope, $key);

        if ($existing)
        {
            if ($existing['state'] === 'done')
            {
                if ($existing['request_hash'] !== $hash)
                {
                    return array('state' => 'conflict', 'id' => (int) $existing['id'], 'row' => $existing);
                }

                if ($existing['user_id'] !== NULL AND $user_id !== NULL
                    AND (int) $existing['user_id'] !== (int) $user_id)
                {
                    // Ключ угадал или переиспользовал другой пользователь: чужой
                    // ответ не отдаём (в нём могут быть данные другого заказчика).
                    return array('state' => 'conflict', 'id' => (int) $existing['id'], 'row' => $existing);
                }

                return array('state' => 'replay', 'id' => (int) $existing['id'], 'row' => $existing);
            }

            // Запись в работе. Свежая — значит, запрос действительно идёт сейчас;
            // зависшую (упал процесс) забираем себе, иначе ключ «залипнет» навсегда.
            $age = time() - strtotime((string) $existing['created_at']);

            if ($age < self::STALE_SECONDS)
            {
                return array('state' => 'in_progress', 'id' => (int) $existing['id'], 'row' => $existing);
            }

            DB::update(self::TABLE)
                ->set(array(
                    'state'        => 'in_progress',
                    'request_hash' => $hash,
                    'request_json' => isset($meta['request']) ? json_encode($meta['request'], JSON_UNESCAPED_UNICODE) : NULL,
                    'created_at'   => date('c'),
                ))
                ->where('id', '=', (int) $existing['id'])
                ->execute();

            return array('state' => 'new', 'id' => (int) $existing['id'], 'row' => $existing);
        }

        try
        {
            $result = DB::insert(self::TABLE, array(
                    'scope', 'idem_key', 'user_id', 'method', 'uri',
                    'request_hash', 'request_json', 'state', 'created_at', 'expires_at',
                ))
                ->values(array(
                    (string) $scope,
                    (string) $key,
                    $user_id !== NULL ? (int) $user_id : NULL,
                    isset($meta['method']) ? strtoupper(substr((string) $meta['method'], 0, 8)) : 'POST',
                    isset($meta['uri']) ? (string) $meta['uri'] : NULL,
                    (string) $hash,
                    isset($meta['request']) ? json_encode($meta['request'], JSON_UNESCAPED_UNICODE) : NULL,
                    'in_progress',
                    date('c'),
                    date('c', time() + self::TTL_HOURS * 3600),
                ))
                ->execute();

            return array('state' => 'new', 'id' => is_array($result) ? (int) reset($result) : (int) $result, 'row' => NULL);
        }
        catch (Database_Exception $e)
        {
            // Между проверкой и вставкой ключ занял параллельный запрос —
            // уникальный индекс сработал ровно так, как задумано.
            $existing = $this->_find($scope, $key);

            return array(
                'state' => ($existing AND $existing['state'] === 'done') ? 'replay' : 'in_progress',
                'id'    => $existing ? (int) $existing['id'] : NULL,
                'row'   => $existing,
            );
        }
    }

    /**
     * Завершить операцию: сохранить ответ для повторов.
     *
     * @param   int|null    $id
     * @param   int         $code    код ответа HTTP
     * @param   string      $body    тело ответа
     * @param   string|null $type    Content-Type
     * @param   int|null    $entity_id созданная сущность (для разбора повторов)
     * @return  void
     */
    public function finish($id, $code, $body, $type = NULL, $entity_id = NULL)
    {
        if ( ! $id OR ! $this->available())
        {
            return;
        }

        // Огромные ответы не храним: защита от повтора не должна превращаться в
        // хранилище выгрузок. Повтор такого ключа выполнит операцию заново...
        // поэтому помечаем состояние done и отдаём клиенту тот же код.
        $store = (strlen((string) $body) <= self::MAX_BODY) ? (string) $body : NULL;

        DB::update(self::TABLE)
            ->set(array(
                'state'         => 'done',
                'response_code' => (int) $code,
                'response_body' => $store,
                'response_type' => $type !== NULL ? (string) $type : NULL,
                'entity_id'     => $entity_id !== NULL ? (int) $entity_id : NULL,
                'finished_at'   => date('c'),
                'expires_at'    => date('c', time() + self::TTL_HOURS * 3600),
            ))
            ->where('id', '=', (int) $id)
            ->execute();
    }

    /**
     * Освободить ключ: действие упало, повтор должен иметь право на выполнение.
     *
     * @param   int|null $id
     * @return  void
     */
    public function release($id)
    {
        if ( ! $id OR ! $this->available())
        {
            return;
        }

        DB::delete(self::TABLE)->where('id', '=', (int) $id)->execute();
    }

    /**
     * Убрать просроченные ключи.
     *
     * @param   int $hours 0 — удалить только просроченные
     * @return  int сколько удалено
     */
    public function purge($hours = 0)
    {
        if ( ! $this->available())
        {
            return 0;
        }

        $query = DB::delete(self::TABLE);

        if ($hours > 0)
        {
            // Ручная чистка «старше N часов»: записи в работе не трогаем — по
            // ним прямо сейчас может идти запрос (их разберёт истечение срока).
            $query
                ->where('created_at', '<', date('c', time() - $hours * 3600))
                ->where('state', '!=', 'in_progress');
        }
        else
        {
            // Штатная уборка: всё, у чего вышел срок хранения ответа.
            $query->where('expires_at', '<', date('c'));
        }

        return (int) $query->execute();
    }

    /**
     * Запись по ключу (для тестов и разбора инцидентов).
     *
     * @param   string $scope
     * @param   string $key
     * @return  array|null
     */
    public function find($scope, $key)
    {
        return $this->available() ? $this->_find($scope, $key) : NULL;
    }

    /**
     * @param   string $scope
     * @param   string $key
     * @return  array|null
     */
    protected function _find($scope, $key)
    {
        $row = DB::select()
            ->from(self::TABLE)
            ->where('scope', '=', (string) $scope)
            ->where('idem_key', '=', (string) $key)
            ->limit(1)
            ->execute()
            ->current();

        return $row ? $row : NULL;
    }
}
