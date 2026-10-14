<?php defined('SYSPATH') or die('No direct script access.');

/**
 * JSON API модуля QR-маркировки (erp199_sqr) — контракт тонкого клиента.
 *
 * Приложение (Android, webview, curl) умеет только: считать QR, снять фото,
 * получить координаты и показать то, что вернул сервер. Поэтому интерфейс
 * намеренно узкий:
 *
 *   GET  /qr/api/resolve/<code>          — что это и что можно сделать;
 *   GET  /qr/api/targets?kind=…&q=…      — поиск цели для привязки;
 *   GET  /qr/api/scenarios               — каталог страниц терминала;
 *   GET  /qr/api/inventory               — открытая инвентаризация и прогресс;
 *   GET  /qr/api/object/<id>             — свободный объект;
 *   GET  /qr/api/objects?q=…             — список свободных объектов;
 *   GET  /qr/api/tokens?q=…              — реестр наклеек;
 *   GET  /qr/api/token/<code>            — карточка наклейки;
 *   GET  /qr/api/activity/<code>         — история сканов наклейки;
 *   GET  /qr/api/types                   — типы объектов;
 *
 *   POST /qr/api/bind                    — привязать наклейку к цели;
 *   POST /qr/api/register                — завести свободный объект с телефона;
 *   POST /qr/api/scan                    — выполнить сценарий (фото, статус, этап);
 *   POST /qr/api/photo                   — добавить фото к наклейке;
 *   POST /qr/api/save                    — правка свободного объекта;
 *   POST /qr/api/moderate                — решение модератора по черновику;
 *   POST /qr/api/inventory_open          — открыть инвентаризацию;
 *   POST /qr/api/inventory_close         — закрыть инвентаризацию;
 *   POST /qr/api/inventory_scan          — отметить наклейку в инвентаризации.
 *
 * Изменяющие действия принимают только POST (контракт `Controller_Base`), а
 * повтор запроса с тем же `Idempotency-Key` не выполняет действие дважды — это
 * важно в поле, где связь рвётся на середине отправки.
 *
 * @package    Erp199
 * @category   Controllers
 */
class Controller_Erp199_Api extends Controller_Erp199_Main
{
    public function before()
    {
        parent::before();

        $this->auto_render = FALSE;
        $this->response->headers('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Корень API: что этот адрес умеет.
     *
     * Отвечает без вызова методов модуля: используется для быстрой проверки,
     * что маршрут жив, и как справка для разработчика приложения.
     *
     * @return  void
     */
    public function action_index()
    {
        $this->_json_ok(array(
            'module'  => 'erp199_sqr',
            'title'   => 'QR-маркировка: тонкий клиент',
            'version' => 1,
            'read'    => array('resolve', 'targets', 'scenarios', 'inventory', 'object', 'objects', 'tokens', 'token', 'activity', 'types'),
            'write'   => array('bind', 'register', 'scan', 'photo', 'save', 'moderate', 'inventory_open', 'inventory_close', 'inventory_scan'),
            'auth'    => 'Сессия системы или ключ устройства (erp804_mcp) в заголовке Authorization: Bearer …',
        ));
    }

    // =========================================================================
    //  Чтение
    // =========================================================================

    /**
     * Что показать по отсканированному коду.
     *
     * @return  void
     */
    public function action_resolve()
    {
        $this->guard_api('erp199.scan');

        $code = (string) $this->request->param('id', $this->_query('code', ''));

        if ($code === '')
        {
            $this->_json_fail('Не передан код наклейки', 'validation', 400);

            return;
        }

        $this->_json_ok($this->scan_service()->resolve($code, $this->actor(), $this->meta()));
    }

    /**
     * Поиск цели для привязки наклейки.
     *
     * @return  void
     */
    public function action_targets()
    {
        $this->guard_api('erp199.tokens.view');

        $kind = (string) $this->_query('kind', 'registry');

        $this->_json_ok(array(
            'kinds'   => Service_Erp199_Targets::instance()->kinds(),
            'results' => Service_Erp199_Targets::instance()->search($kind, (string) $this->_query('q', ''), (int) $this->_query('limit', 10)),
        ));
    }

    /**
     * Каталог сценариев терминала.
     *
     * @return  void
     */
    public function action_scenarios()
    {
        $this->guard_api('erp199.scan');

        $scenarios = array();

        foreach (Service_Erp199_Scenarios::instance()->all() as $scenario)
        {
            $scenarios[] = $this->scan_service()->scenario_for_client($scenario);
        }

        $this->_json_ok(array('scenarios' => $scenarios));
    }

    /**
     * Открытая инвентаризация и её прогресс.
     *
     * @return  void
     */
    public function action_inventory()
    {
        $this->guard_api('erp199.inventories.view');

        $inventory = Service_Erp199_Inventories::instance()->open_session();

        if ( ! $inventory)
        {
            $this->_json_ok(array('inventory' => NULL, 'progress' => NULL, 'expected' => array()));

            return;
        }

        $this->_json_ok(array(
            'inventory' => array(
                'id'    => (int) $inventory['id'],
                'uuid'  => (string) $inventory['uuid'],
                'code'  => (string) $inventory['code'],
                'label' => (string) $inventory['label'],
                'scope' => $inventory['scope'],
            ),
            'progress' => Service_Erp199_Inventories::instance()->progress($inventory['id']),
            'expected' => Service_Erp199_Inventories::instance()->expected($inventory['id'], (int) $this->_query('limit', 200), $this->_query('q')),
        ));
    }

    /**
     * Свободный объект модуля.
     *
     * @return  void
     */
    public function action_object()
    {
        $this->guard_api('erp199.objects.view');

        try
        {
            $this->_json_ok(array('object' => Service_Erp199_Objects::instance()->get($this->request->param('id'))));
        }
        catch (Exception $e)
        {
            $this->_json_fail($e->getMessage(), 'not_found', 404);
        }
    }

    /**
     * Список свободных объектов.
     *
     * @return  void
     */
    public function action_objects()
    {
        $this->guard_api('erp199.objects.view');

        $result = Service_Erp199_Objects::instance()->search(array(
            'q'      => $this->_query('q', ''),
            'status' => $this->_query('status'),
            'type'   => $this->_query('type'),
        ), (int) $this->_query('limit', 50), (int) $this->_query('offset', 0));

        $this->_json_ok($result);
    }

    /**
     * Реестр наклеек.
     *
     * @return  void
     */
    public function action_tokens()
    {
        $this->guard_api('erp199.tokens.view');

        $result = Service_Erp199_Tokens::instance()->search(array(
            'q'      => $this->_query('q', ''),
            'status' => $this->_query('status'),
            'kind'   => $this->_query('kind'),
            'batch'  => $this->_query('batch'),
            'bound'  => $this->_query('bound'),
        ), (int) $this->_query('limit', 50), (int) $this->_query('offset', 0));

        $this->_json_ok($result + array('stats' => Service_Erp199_Tokens::instance()->stats()));
    }

    /**
     * Карточка наклейки.
     *
     * @return  void
     */
    public function action_token()
    {
        $this->guard_api('erp199.tokens.view');

        try
        {
            $token = Service_Erp199_Tokens::instance()->get($this->request->param('id'));

            $this->_json_ok(array(
                'token'    => $token,
                'status_label' => Service_Erp199_Tokens::instance()->status_label($token['status']),
                'target'   => $token['target_kind']
                    ? Service_Erp199_Targets::instance()->card($token['target_kind'], $token['target_id'])
                    : NULL,
                'photos'   => Service_Erp199_Media::instance()->search(array('token_id' => $token['id'])),
                'activity' => $this->scan_service()->activity($token['id'], (int) $this->_query('limit', 20)),
            ));
        }
        catch (Exception $e)
        {
            $this->_json_fail($e->getMessage(), 'not_found', 404);
        }
    }

    /**
     * История действий по наклейке.
     *
     * @return  void
     */
    public function action_activity()
    {
        $this->guard_api('erp199.activity.view');

        try
        {
            $token = Service_Erp199_Tokens::instance()->get($this->request->param('id'));

            $this->_json_ok(array('activity' => $this->scan_service()->activity($token['id'], (int) $this->_query('limit', 50))));
        }
        catch (Exception $e)
        {
            $this->_json_fail($e->getMessage(), 'not_found', 404);
        }
    }

    /**
     * Типы объектов и ожидаемые атрибуты (для отрисовки форм).
     *
     * @return  void
     */
    public function action_types()
    {
        $this->guard_api('erp199.types.view');

        $this->_json_ok(array('types' => Service_Erp199_Types::instance()->all()));
    }

    // =========================================================================
    //  Изменения (только POST — контракт Controller_Base)
    // =========================================================================

    /**
     * Привязать наклейку к существующей цели.
     *
     * @return  void
     */
    public function action_bind()
    {
        $this->guard_api('erp199.tokens.bind', TRUE);

        $payload = $this->payload();

        try
        {
            $result = $this->scan_service()->bind(
                (string) Arr::get($payload, 'token'),
                (string) Arr::get($payload, 'kind', 'registry'),
                Arr::get($payload, 'ref', Arr::get($payload, 'target_id')),
                $this->actor(),
                Arr::get($payload, 'comment'),
                $this->meta($payload)
            );

            $this->_json_ok($result);
        }
        catch (Exception $e)
        {
            $this->_json_fail($e->getMessage(), 'validation', 400);
        }
    }

    /**
     * Завести свободный объект с телефона и привязать наклейку.
     *
     * @return  void
     */
    public function action_register()
    {
        $this->guard_api('erp199.objects.create', TRUE);

        $payload = $this->payload();

        try
        {
            $result = $this->scan_service()->register_local(
                (string) Arr::get($payload, 'token'),
                $payload,
                array('image' => $this->uploaded('image')),
                $this->actor(),
                $this->meta($payload)
            );

            $this->_json_ok($result);
        }
        catch (Exception $e)
        {
            $this->_json_fail($e->getMessage(), 'validation', 400);
        }
    }

    /**
     * Выполнить сценарий над наклейкой.
     *
     * @return  void
     */
    public function action_scan()
    {
        $this->guard_api('erp199.scan', TRUE);

        $payload = $this->payload();

        try
        {
            $result = $this->scan_service()->execute(
                (string) Arr::get($payload, 'token'),
                (string) Arr::get($payload, 'scenario'),
                $payload,
                array('image' => $this->uploaded('image')),
                $this->actor(),
                $this->meta($payload)
            );

            $this->_json_ok($result);
        }
        catch (Exception $e)
        {
            $this->_json_fail($e->getMessage(), 'validation', 400);
        }
    }

    /**
     * Добавить фото к наклейке (без сценария).
     *
     * @return  void
     */
    public function action_photo()
    {
        $this->guard_api('erp199.scan', TRUE);

        $payload = $this->payload();

        try
        {
            $token = Service_Erp199_Tokens::instance()->get(Arr::get($payload, 'token'));

            $files = array('image' => $this->uploaded('image'));
            $media = ! empty($files['image'])
                ? Service_Erp199_Media::instance()->save_upload($files['image'], array(
                    'token_id' => $token['id'], 'object_id' => $token['object_id'],
                    'purpose' => Arr::get($payload, 'purpose', 'object'),
                    'uploaded_by' => $this->actor(),
                    'geo_lat' => Arr::get($payload, 'geo_lat', Arr::get($payload, 'lat')),
                    'geo_lon' => Arr::get($payload, 'geo_lon', Arr::get($payload, 'lon')),
                ))
                : Service_Erp199_Media::instance()->save_base64(Arr::get($payload, 'image'), array(
                    'token_id' => $token['id'], 'object_id' => $token['object_id'],
                    'purpose' => Arr::get($payload, 'purpose', 'object'),
                    'uploaded_by' => $this->actor(),
                    'geo_lat' => Arr::get($payload, 'geo_lat', Arr::get($payload, 'lat')),
                    'geo_lon' => Arr::get($payload, 'geo_lon', Arr::get($payload, 'lon')),
                ));

            Service_Erp199_Tokens::instance()->log($token, 'media', $this->actor(), 'ok', array(
                'media_id' => (int) $media['id'], 'purpose' => $media['purpose'],
            ), Arr::get($payload, 'comment'), $this->meta($payload));

            $this->_json_ok(array('media' => $media));
        }
        catch (Exception $e)
        {
            $this->_json_fail($e->getMessage(), 'validation', 400);
        }
    }

    /**
     * Правка свободного объекта (в том числе с модерации).
     *
     * @return  void
     */
    public function action_save()
    {
        $this->guard_api('erp199.objects.edit', TRUE);

        $payload = $this->payload();
        $ref     = Arr::get($payload, 'id', $this->request->param('id'));

        try
        {
            $this->_json_ok(array('object' => Service_Erp199_Objects::instance()->update($ref, $payload, $this->actor())));
        }
        catch (Exception $e)
        {
            $this->_json_fail($e->getMessage(), 'validation', 400);
        }
    }

    /**
     * Решение модератора по черновику объекта.
     *
     * @return  void
     */
    public function action_moderate()
    {
        $this->guard_api('erp199.objects.moderate', TRUE);

        $payload = $this->payload();

        try
        {
            $object = Service_Erp199_Objects::instance()->moderate(
                Arr::get($payload, 'id', $this->request->param('id')),
                (string) Arr::get($payload, 'status'),
                $this->actor(),
                Arr::get($payload, 'comment')
            );

            $this->_json_ok(array('object' => $object));
        }
        catch (Exception $e)
        {
            $this->_json_fail($e->getMessage(), 'validation', 400);
        }
    }

    /**
     * Открыть инвентаризацию.
     *
     * @return  void
     */
    public function action_inventory_open()
    {
        $this->guard_api('erp199.inventories.manage', TRUE);

        $payload = $this->payload();

        try
        {
            $inventory = Service_Erp199_Inventories::instance()->open($payload, $this->actor());

            $this->_json_ok(array('inventory' => $inventory, 'progress' => Service_Erp199_Inventories::instance()->progress($inventory['id'])));
        }
        catch (Exception $e)
        {
            $this->_json_fail($e->getMessage(), 'validation', 400);
        }
    }

    /**
     * Закрыть инвентаризацию.
     *
     * @return  void
     */
    public function action_inventory_close()
    {
        $this->guard_api('erp199.inventories.manage', TRUE);

        $payload = $this->payload();

        try
        {
            $inventory = Service_Erp199_Inventories::instance()->close(
                Arr::get($payload, 'id', $this->request->param('id')),
                $this->actor(),
                Arr::get($payload, 'comment')
            );

            $this->_json_ok(array('inventory' => $inventory, 'progress' => Service_Erp199_Inventories::instance()->progress($inventory['id'])));
        }
        catch (Exception $e)
        {
            $this->_json_fail($e->getMessage(), 'validation', 400);
        }
    }

    /**
     * Отметить наклейку в инвентаризации (короткий путь для терминала).
     *
     * @return  void
     */
    public function action_inventory_scan()
    {
        $this->guard_api('erp199.scan', TRUE);

        $payload = $this->payload();

        try
        {
            $inventory = Service_Erp199_Inventories::instance()->open_session();

            if ( ! $inventory)
            {
                throw new Kohana_Exception('Открытой инвентаризации нет');
            }

            $token = Service_Erp199_Tokens::instance()->get(Arr::get($payload, 'token'));

            $this->_json_ok(array('progress' => Service_Erp199_Inventories::instance()->mark(
                $inventory['id'],
                $token,
                (string) Arr::get($payload, 'status', 'found'),
                $this->actor(),
                array(
                    'comment' => Arr::get($payload, 'comment'),
                    'geo_lat' => Arr::get($payload, 'geo_lat', Arr::get($payload, 'lat')),
                    'geo_lon' => Arr::get($payload, 'geo_lon', Arr::get($payload, 'lon')),
                )
            )));
        }
        catch (Exception $e)
        {
            $this->_json_fail($e->getMessage(), 'validation', 400);
        }
    }
}
