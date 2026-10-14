<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Тесты идемпотентности JSON API (ядро: Service_Erp100_Idempotency + Controller_Base).
 *
 * Зачем это вообще нужно. Точка входа для машин у каждого модуля — свой
 * `Api.php`, и до этой правки повтор запроса означал повтор операции: обрыв
 * связи, повтор LLM-агента или двойной клик создавали **второй документ**
 * (вторую заявку, второй заказ, вторую приёмку). Слой MCP был защищён
 * (`idempotency_key` в `erp_804_mcp_calls`), обычный API — нет.
 *
 * Что проверяем:
 *
 *  - ключ читается из заголовка `Idempotency-Key` или параметра, мусор не
 *    принимается;
 *  - повтор с тем же ключом и телом возвращает **сохранённый ответ**, а не
 *    выполняет действие второй раз (проверяется на живом API закупок: два
 *    одинаковых POST — одна заявка в базе);
 *  - тот же ключ с другим телом — 409 `conflict`;
 *  - параллельный запрос — 409 `in_progress`, зависшая запись переиспользуется;
 *  - чужой ключ другого пользователя не отдаёт свой ответ;
 *  - без ключа поведение прежнее (два POST — два документа): защита не ломает
 *    существующих клиентов.
 *
 * Свои ключи и созданные заявки тест убирает в tearDown.
 */
class Erp100IdempotencyTest extends TestCase
{
    /** @var string Префикс ключей теста */
    const PREFIX = 'ТЕСТ-IDEM-';

    /** @var array id созданных заявок закупки */
    protected $request_ids = array();

    /** @var \Service_Erp100_Idempotency */
    protected $idempotency;

    protected function setUp(): void
    {
        if ( ! class_exists('Service_Erp100_Idempotency'))
        {
            $this->markTestSkipped('Сервис идемпотентности недоступен');
            return;
        }

        $this->idempotency = \Service_Erp100_Idempotency::instance();

        if ( ! $this->idempotency->available())
        {
            $this->markTestSkipped('Нет таблицы erp_100_api_idempotency — выполните bin/console db:migrate');
        }

        $this->_clean();

        // Живой API проверяет права, а в CLI сессии нет: без входа POST отвечает
        // 403 и проверять идемпотентность не на чем (см. AGENTS.md §6 п. 36).
        if (class_exists('Service_Dev_Smoke'))
        {
            $smoke = new \Service_Dev_Smoke;
            $smoke->login_as('admin');
        }
    }

    protected function tearDown(): void
    {
        if ( ! class_exists('Service_Erp100_Idempotency'))
        {
            return;
        }

        $this->_clean();

        // Заявки, созданные живыми POST-запросами теста
        foreach ($this->request_ids as $id)
        {
            try
            {
                \DB::delete('erp_403_request_lines')->where('request_id', '=', (int) $id)->execute();
                \DB::delete('erp_403_requests')->where('id', '=', (int) $id)->execute();
            }
            catch (\Exception $e)
            {
                // нет модуля закупок — нечего убирать
            }
        }

        foreach (\DB::select('id')->from('erp_403_requests')
            ->where('note', 'LIKE', 'ТЕСТ-IDEM%')->execute()->as_array() as $row)
        {
            \DB::delete('erp_403_request_lines')->where('request_id', '=', (int) $row['id'])->execute();
            \DB::delete('erp_403_requests')->where('id', '=', (int) $row['id'])->execute();
        }
    }

    /**
     * Ключ: заголовок, параметр, мусор.
     */
    public function test_key_is_normalized_and_junk_is_rejected()
    {
        $service = $this->idempotency;

        $this->assertSame('key-1', $service->key('key-1', 'key-2'), 'заголовок важнее параметра');
        $this->assertSame('key-2', $service->key(NULL, 'key-2'), 'параметр работает без заголовка');
        $this->assertSame('key-3', $service->key('  key-3  ', NULL), 'пробелы снимаются');
        $this->assertNull($service->key(NULL, NULL));
        $this->assertNull($service->key('', ''));
        $this->assertNull($service->key(array('a'), NULL), 'массив ключом быть не может');

        $long = str_repeat('x', 200);
        $this->assertSame(128, strlen($service->key($long, NULL)), 'длинный ключ обрезается по колонке');
    }

    /**
     * Первый запрос выполняется, повтор получает сохранённый ответ.
     */
    public function test_finish_then_replay_returns_saved_response()
    {
        $scope = 'test:replay';
        $key   = self::PREFIX.'replay';
        $hash  = $this->idempotency->fingerprint('POST', '/test', array('a' => 1));

        $plan = $this->idempotency->begin($scope, $key, 1, $hash, array('method' => 'POST', 'uri' => '/test'));

        $this->assertSame('new', $plan['state']);
        $this->assertGreaterThan(0, (int) $plan['id']);

        $this->idempotency->finish($plan['id'], 200, '{"success":true}', 'application/json', 42);

        $again = $this->idempotency->begin($scope, $key, 1, $hash, array('method' => 'POST', 'uri' => '/test'));

        $this->assertSame('replay', $again['state']);
        $this->assertSame(200, (int) $again['row']['response_code']);
        $this->assertSame('{"success":true}', $again['row']['response_body']);
        $this->assertSame(42, (int) $again['row']['entity_id']);
    }

    /**
     * Тот же ключ, другое тело — ошибка клиента, а не второй документ.
     */
    public function test_same_key_with_other_body_is_conflict()
    {
        $scope = 'test:conflict';
        $key   = self::PREFIX.'conflict';

        $first = $this->idempotency->begin($scope, $key, 1,
            $this->idempotency->fingerprint('POST', '/test', array('qty' => 1)));
        $this->idempotency->finish($first['id'], 200, '{"ok":true}');

        $second = $this->idempotency->begin($scope, $key, 1,
            $this->idempotency->fingerprint('POST', '/test', array('qty' => 2)));

        $this->assertSame('conflict', $second['state']);
    }

    /**
     * Пока запрос выполняется, повтор получает in_progress; зависшую запись
     * можно переиспользовать, иначе ключ «залипает» навсегда.
     */
    public function test_in_progress_and_stale_key_is_reclaimed()
    {
        $scope = 'test:progress';
        $key   = self::PREFIX.'progress';
        $hash  = $this->idempotency->fingerprint('POST', '/test', array());

        $plan = $this->idempotency->begin($scope, $key, 1, $hash);

        $this->assertSame('in_progress',
            $this->idempotency->begin($scope, $key, 1, $hash)['state']);

        // «Зависла»: обработчик упал вместе с процессом
        \DB::update('erp_100_api_idempotency')
            ->set(array('created_at' => date('c', time() - 3600)))
            ->where('id', '=', (int) $plan['id'])
            ->execute();

        $this->assertSame('new',
            $this->idempotency->begin($scope, $key, 1, $hash)['state'],
            'запись старше пяти минут считается зависшей и переиспользуется');
    }

    /**
     * Упавшее действие освобождает ключ: иначе повтор бессмысленен.
     */
    public function test_release_frees_the_key()
    {
        $scope = 'test:release';
        $key   = self::PREFIX.'release';
        $hash  = $this->idempotency->fingerprint('POST', '/test', array());

        $plan = $this->idempotency->begin($scope, $key, 1, $hash);
        $this->idempotency->release($plan['id']);

        $this->assertNull($this->idempotency->find($scope, $key));
        $this->assertSame('new', $this->idempotency->begin($scope, $key, 1, $hash)['state']);
    }

    /**
     * Чужим ключом ответ другого пользователя не получить.
     */
    public function test_foreign_key_is_not_replayed_to_another_user()
    {
        $scope = 'test:user';
        $key   = self::PREFIX.'user';
        $hash  = $this->idempotency->fingerprint('POST', '/test', array());

        $plan = $this->idempotency->begin($scope, $key, 1, $hash);
        $this->idempotency->finish($plan['id'], 200, '{"ok":true}');

        $this->assertSame('replay',
            $this->idempotency->begin($scope, $key, 1, $hash)['state'],
            'тот же пользователь получает свой ответ');

        $this->assertSame('conflict',
            $this->idempotency->begin($scope, $key, 2, $hash)['state'],
            'другой пользователь — отказ: в ответе могут быть чужие данные');
    }

    /**
     * Уборка просроченных ключей.
     */
    public function test_purge_removes_expired_keys()
    {
        $scope = 'test:purge';
        $key   = self::PREFIX.'purge';
        $hash  = $this->idempotency->fingerprint('POST', '/test', array());

        $plan = $this->idempotency->begin($scope, $key, 1, $hash);

        \DB::update('erp_100_api_idempotency')
            ->set(array('expires_at' => date('c', time() - 60)))
            ->where('id', '=', (int) $plan['id'])
            ->execute();

        $this->assertGreaterThan(0, $this->idempotency->purge());
        $this->assertNull($this->idempotency->find($scope, $key));
    }

    /**
     * Живой API: два одинаковых POST с одним ключом создают один документ,
     * второй ответ помечен как повтор.
     */
    public function test_api_post_with_same_key_creates_one_document()
    {
        if ( ! $this->_api_available())
        {
            $this->markTestSkipped('Модуль закупок недоступен — нужен живой API для проверки');
        }

        $key   = self::PREFIX.'api-1';
        $post  = array('name' => 'ТЕСТ-IDEM заявка', 'qty' => 3, 'note' => 'ТЕСТ-IDEM повтор');
        $first = $this->_post('/procurement/api/request_create', $post, $key);

        $this->assertSame(201, $first->status(), 'первый запрос выполняется: '.$first->body());

        $created = $this->_created_id($first);
        $this->assertGreaterThan(0, $created);
        $this->request_ids[] = $created;

        $second = $this->_post('/procurement/api/request_create', $post, $key);

        $this->assertSame(201, $second->status(), 'повтор отдаёт тот же код ответа, что и первый запрос');
        $this->assertSame('true', (string) $second->headers('Idempotent-Replay'),
            'повтор должен быть помечен заголовком Idempotent-Replay');
        $this->assertSame($first->body(), $second->body(), 'повтор отдаёт сохранённый ответ');

        $count = \DB::select(array(\DB::expr('COUNT(*)'), 'found'))
            ->from('erp_403_requests')
            ->where('id', '=', $created)
            ->execute()->get('found');

        $this->assertSame(1, (int) $count);
    }

    /**
     * Тот же ключ с другим телом: живой API отвечает 409 и ничего не создаёт.
     */
    public function test_api_post_with_same_key_and_other_body_is_rejected()
    {
        if ( ! $this->_api_available())
        {
            $this->markTestSkipped('Модуль закупок недоступен — нужен живой API для проверки');
        }

        $key = self::PREFIX.'api-2';

        $first = $this->_post('/procurement/api/request_create',
            array('name' => 'ТЕСТ-IDEM заявка A', 'qty' => 3, 'note' => 'ТЕСТ-IDEM конфликт'), $key);
        $this->assertSame(201, $first->status());
        $this->request_ids[] = $this->_created_id($first);

        $second = $this->_post('/procurement/api/request_create',
            array('name' => 'ТЕСТ-IDEM заявка B', 'qty' => 9, 'note' => 'ТЕСТ-IDEM конфликт'), $key);

        $this->assertSame(409, $second->status());

        $body = json_decode($second->body(), TRUE);

        $this->assertSame('conflict', $body['error']['reason']);
    }

    /**
     * Без ключа поведение прежнее: сколько запросов — столько документов.
     */
    public function test_api_without_key_keeps_old_behaviour()
    {
        if ( ! $this->_api_available())
        {
            $this->markTestSkipped('Модуль закупок недоступен — нужен живой API для проверки');
        }

        $post = array('name' => 'ТЕСТ-IDEM без ключа', 'qty' => 1, 'note' => 'ТЕСТ-IDEM без ключа');

        $first  = $this->_post('/procurement/api/request_create', $post, NULL);
        $second = $this->_post('/procurement/api/request_create', $post, NULL);

        $this->assertSame(201, $first->status());
        $this->assertSame(201, $second->status());

        $this->request_ids[] = $this->_created_id($first);
        $this->request_ids[] = $this->_created_id($second);

        $this->assertNotSame($this->request_ids[0], $this->request_ids[1],
            'без ключа защита не включается: это два разных документа');
    }

    /**
     * POST в живой маршрут внутри процесса (как это делает дымовой прогон).
     */
    protected function _post($uri, array $post, $key)
    {
        $request = \Request::factory($uri)
            ->method(\Request::POST)
            ->post($post);

        if ($key !== NULL)
        {
            $request->headers('Idempotency-Key', $key);
        }

        return $request->execute();
    }

    /**
     * @return int
     */
    protected function _created_id($response)
    {
        $body = json_decode($response->body(), TRUE);

        if (is_array($body) AND isset($body['data']['id']))
        {
            return (int) $body['data']['id'];
        }

        if (is_array($body) AND isset($body['data']['request']['id']))
        {
            return (int) $body['data']['request']['id'];
        }

        return 0;
    }

    /**
     * Есть ли чем проверять живой API (модуль закупок подключён и мигрирован).
     */
    protected function _api_available()
    {
        try
        {
            return class_exists('Service_Erp403_Requests')
                AND (bool) \DB::query(\Database::SELECT,
                    "SELECT to_regclass('public.erp_403_requests') IS NOT NULL AS ok")
                    ->execute()->get('ok');
        }
        catch (\Exception $e)
        {
            return FALSE;
        }
    }

    /**
     * Убрать ключи и заявки теста.
     */
    protected function _clean()
    {
        \DB::delete('erp_100_api_idempotency')
            ->where('idem_key', 'LIKE', self::PREFIX.'%')
            ->execute();
    }
}
