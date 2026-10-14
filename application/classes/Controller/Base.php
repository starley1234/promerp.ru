<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Controller_Base — Базовый контроллер ERP-системы.
 * 
 * ПРИНЦИП: "Просто, Единообразно, Хорошо"
 * 
 * 1. ДОСТУП И РАЗРЕШЕНИЯ (ACL):
 *    - $this->can('ability_name', $model): Проверка разрешения (например, 'invoice.edit').
 *    - Глобальные переменные в View: $current_user, $current_user_roles.
 *    - В контроллерах: $this->_current_user (объект Model_User).
 *     
 * ПАМЯТКА ПО ПРОВЕРКЕ ПРАВ В VIEWS (ШАБЛОНАХ):
 * 
 * 1. ACL + Владение: <?php if ($this->can('client.edit', $client)): ?> ... <?php endif; ?>
 * 2. Роли (массив):  <?php if (in_array('admin', $current_user_roles)): ?> ... <?php endif; ?>
 * 3. Роли (метод):   <?php if ($this->has_any_role(['admin', 'manager'])): ?> ... <?php endif; ?>
 * 4. Владелец:       <?php if ($this->is_owner($item)): ?> ... <?php endif; ?>
 * 5. Авторизация:    <?php if ($current_user): ?> Привет, <?=$current_user->username?> <?php endif; ?>
 *
 *  
 * 2. ДОСТУПНЫЕ ДАННЫЕ ПРОФИЛЯ (через $current_user = $this->_current_user):
 *    - Основное: $current_user->email, $current_user->username
 *    - Профиль: $current_user->profile->full_name, position, first_name, last_name, phone_number, city и т.д.
 *    - Настройки: $current_user->setting->settings_json (автоматически декодируется в массив)
 *    - $user = ORM::factory('User', 2); $fullName = $user->profile->full_name; position;
 *    - $profiles = Model_User::get_all_names(); // @return array ['user_id', 'full_name', 'position'] Массив данных профилей, индексированный по user_id
 * 
 * 3. СВЯЗИ ЧЕРЕЗ СОБЫТИЯ (Event Dispatcher):
 *    - Модули общаются через шину: Event::fire('erp[ID].object.action', $data).
 *    - Это позволяет модулям Audit, Notify и Storage работать автоматически.
 *    - Пример подписки в контроллерах или init-файлах (в одну строку): `Event::listen('erp219.item.created', function($payload) {`
 * 
 * 4. ХРАНИЛИЩЕ (Storage):
 *    - Загрузка файлов: $file_id = Storage::upload($_FILES['doc'], 'erp[ID]_module_name').
 *    - Получение ссылки: Storage::url($file_id).
 * 
 * 5. УВЕДОМЛЕНИЯ (Notify):
 *    - Notify::send($user, 'template_code', ['var' => 'val']): Отправка почты/TG/push.
 *  
 * 6. УПРАВЛЕНИЕ ШАБЛОНОМ ИЗ КОНТРОЛЛЕРА:
 *    - $this->set_title('Заголовок') - установить title.
 *    - $this->set_description('Описание') - установить description.
 *    - $this->template->content = View::factory('path/to/view') - основной контент.
 *    - $this->add_style('css/path.css'), $this->add_script('js/path.js') - подключение ресурсов.
 * 
 * 7. ИИ (LLM):
 *
 * // Самый простой вариант (модель по умолчанию)
 * $text = Llm::ask("Напиши текст для рассылки"); echo $text;
 *
 * // Мгновенная смена модели через ваш синтаксис
 * $answer = Llm::model('openrouter::google/gemini-2.5-flash-lite')->ask("Проанализируй код"); echo $answer;
 *
 * // Прямой вопрос с системным промптом (без массивов)
 * $pro = Llm::system("Ты юрист")->ask("Проверь договор на риски");
 *
 * // Передаем путь, URL или base64 и вопрос
 * $desc = Llm::model('openrouter::google/gemini-2.5-flash-lite')->vision('path/to/invoice.jpg', 'Сумма счета?');
 *
 * // Анализ нескольких картинок
 * $diff = Llm::vision(['img1.png', 'img2.png'], 'В чем разница между товарами?');
 *
 * // LLM сама понимает, что нужен JSON, благодаря методу
 * $data = Llm::model('local::qwen3-coder-30b-a3b-instruct')->extract($text, ['amount', 'vendor', 'date']);
 * echo $data['amount']; // Работаем сразу с массивом
 *
 * // Вызов именованного шаблона с передачей переменных (анализ данных)
 * $review = Llm::prompt('invoice_check', ['invoice' => $data])->ask(); echo $review;
 *
 * // Базовый метод: Всегда возвращает полный ответ API (массив)
 * $response = Llm::chat($messages);
 * echo "Ответ LLM: " . $response['choices'][0]['message']['content'] . PHP_EOL;
 *  
 * Llm::get_embedding("Это текст для превращения в вектор");
 * 
 * 8. МЕНЮ:
 *    - $this->set_title('Заголовок'): Установить заголовок страницы.
 *    - $this->set_menu([...]): Формирование навигации. 
 *      ВАЖНО: Доступ к пункту меню определяется через ключ 'acl'.
 *
 * 9. Открытие доступа в конкретных контроллерах

#### Вариант А: Открыть весь контроллер (например, статьи)
В файле `/application/modules/517_articles/classes/Controller/Lib517/Articles.php`:

```php
class Controller_Lib517_Articles extends Controller_Lib517_Main {  
    protected $auth_required = FALSE; // Весь контроллер доступен всем
}
```

#### Вариант Б: Открыть только конкретные методы (рекомендуется)
Если вы хотите, чтобы список статей и просмотр были открыты, а создание/редактирование по-прежнему требовало пароль:

```php
class Controller_Lib517_Articles extends Controller_Lib517_Main {
    
    protected $auth_required = TRUE; // Защита включена
    protected $public_actions = ['index', 'view', 'category', 'all', 'sitemap']; // Но эти методы открыты

    public function before() {
        parent::before();
        // ...
    }
}
```
 
 
 */

abstract class Controller_Base extends Controller_Erp100_Base {

    /**
     * @var bool Требуется ли авторизация для доступа к контроллеру по умолчанию
     */
    protected $auth_required = TRUE;

    /**
     * @var array Список action, доступных без авторизации
     */
    protected $public_actions = [];

    /**
     * Выполняется перед вызовом action.
     */
    public function before()
    {
        parent::before();

        // Проверяем, нужно ли ограничивать доступ
        if ($this->auth_required) {
            $action = $this->request->action();
            
            // Если экшен не в белом списке и пользователь не авторизован
            if (!in_array($action, $this->public_actions) && !$this->_current_user) {
                // Перенаправляем на логин или выдаем 403
                throw new HTTP_Exception_403('Access Denied. Please login.');
            }
        }
        
        /**
         * ПРИМЕР ВЫЗОВА МЕНЮ С ПРОВЕРКОЙ РАЗРЕШЕНИЙ:
         * 
         * 'acl' => 'all' (или пустая строка) — пункт видят все авторизованные.
         * 'acl' => 'task.view' — увидят те, кому разрешено 'task.view'.
         * 
         * $this->set_menu([
         *     [
         *         'title'      => 'Задачи',
         *         'href'       => '/erp100_tasks',
         *         'icon'       => 'fas fa-tasks',
         *         'acl' => 'task.view',
         *         'children'   => []
         *     ],
         *     [
         *         'title'      => 'Настройки системы',
         *         'href'       => '/erp100_settings',
         *         'icon'       => 'fas fa-cog',
         *         'acl' => 'settings.manage',
         *         'children'   => []
         *     ]
         * ]);
         */
    }

    /**
     * Параметр строки запроса со значением по умолчанию.
     *
     * ВНИМАНИЕ: у `Request::query($key, $value)` второй аргумент — это
     * ЗНАЧЕНИЕ ДЛЯ ЗАПИСИ, а не значение по умолчанию: вызов
     * `$this->request->query('limit', 50)` возвращает объект Request, и
     * `(int)` от него падает с «Object of class Request could not be
     * converted to int». Поэтому значения по умолчанию — только здесь.
     *
     * @param   string  $key
     * @param   mixed   $default
     * @return  mixed
     */
    protected function _query($key, $default = NULL)
    {
        $value = $this->request->query($key);

        return ($value === NULL OR $value === '') ? $default : $value;
    }

    /**
     * Успешный ответ JSON для API: {"success": true, "data": ...}.
     *
     * Имена отличаются от модульных `_json()`/`_json_error()` намеренно: в
     * модулях исторически свои помощники с несовместимыми сигнатурами, и
     * общий метод с тем же именем сломал бы их (PHP требует совместимости
     * объявлений). Новые контроллеры API используют эти методы, старые
     * переезжают сюда по мере правок модулей.
     *
     * @param   mixed   $data
     * @param   int     $code
     * @return  void
     */
    protected function _json_ok($data, $code = 200)
    {
        $this->auto_render = FALSE;
        $this->response
            ->status($code)
            ->headers('Content-Type', 'application/json; charset=utf-8')
            ->body(json_encode(array('success' => TRUE, 'data' => $data), JSON_UNESCAPED_UNICODE));
    }

    /**
     * Ответ JSON с ошибкой: {"success": false, "error": {...}}.
     *
     * Причины (reason) стабильны и машинно-читаемы: unauthorized, forbidden,
     * not_found, validation, conflict, method_not_allowed, error — клиент
     * разбирает их, а не текст сообщения.
     *
     * @param   string  $message
     * @param   string  $reason
     * @param   int     $code
     * @return  void
     */
    protected function _json_fail($message, $reason = 'error', $code = 400)
    {
        $this->auto_render = FALSE;
        $this->response
            ->status($code)
            ->headers('Content-Type', 'application/json; charset=utf-8')
            ->body(json_encode(array(
                'success' => FALSE,
                'error'   => array('message' => $message, 'reason' => $reason),
            ), JSON_UNESCAPED_UNICODE));
    }

    /**
     * Отказ в доступе + остановка выполнения (для API-контроллеров).
     *
     * Konine не прерывает обработку запроса после before(): фреймворк всё
     * равно вызовет действие (Controller::execute читает имя действия ПОСЛЕ
     * before()). Поэтому вместе с ответом подменяем имя действия на служебное
     * `denied` — действие с пустым пользователем не выполнится и не перепишет
     * ответ 401/403 на 200.
     *
     * @param   string  $message
     * @param   string  $reason
     * @param   int     $code
     * @return  void
     */
    protected function _json_halt($message, $reason = 'forbidden', $code = 403)
    {
        $this->_json_fail($message, $reason, $code);

        $this->request->action('denied');
    }

    /**
     * Служебное действие: сюда приводят проверки доступа в before().
     *
     * Ответ уже сформирован (401/403 JSON). Если же действие вызвано напрямую
     * адресом `/controller/denied`, тела ещё нет — отвечаем честной 403.
     *
     * @return  void
     */
    public function action_denied()
    {
        if ($this->response->body() === '')
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }
    }

    /**
     * Контракт методов JSON API: изменяющие действия вызываются POST-ом.
     *
     * Список действий чтения — `application/config/api.php`. Действие, которого
     * в списке нет, считается изменяющим: GET на него — это 405, а не попытка
     * выполнить его «как получится». Так закрыт класс дефектов, который нашёл
     * дымовой прогон: `GET /mcp/api/key` доходил до выдачи ключа и падал с
     * `Undefined array key "scopes"` (500) вместо понятного отказа
     * (`docs/IMPROVEMENTS.md` B16).
     *
     * Внутренние вызовы (`Request::factory()` из кода, MCP-мост, тесты) не
     * затронуты: там метода как такового нет, и ломать их нельзя.
     *
     * @return  string|null  текст ошибки или NULL, если запрос допустим
     */
    protected function _api_method_error()
    {
        return self::api_method_error(
            get_class($this),
            (string) $this->request->action(),
            (string) $this->request->method(),
            (bool) $this->request->is_initial()
        );
    }

    /**
     * Решение о методе — чистая функция, чтобы её можно было проверить тестом
     * (у внутреннего запроса метода как такового нет, а поведение нужно).
     *
     * @param   string  $controller класс контроллера
     * @param   string  $action     имя действия без `action_`
     * @param   string  $method     метод HTTP
     * @param   bool    $initial    запрос пришёл извне (а не Request::factory из кода)
     * @return  string|null         текст ошибки или NULL
     */
    public static function api_method_error($controller, $action, $method, $initial = TRUE)
    {
        if ( ! preg_match('/_Api$/', (string) $controller) OR ! $initial)
        {
            return NULL;
        }

        if ($action === 'denied' OR in_array($action, self::api_read_only_actions(), TRUE))
        {
            return NULL;
        }

        if (in_array(strtoupper((string) $method), array(Request::POST, Request::PUT, Request::DELETE), TRUE))
        {
            return NULL;
        }

        return 'Действие «'.$action.'» изменяет данные — вызывайте его POST-ом '
            .'(GET оставлен для чтения: список действий чтения — application/config/api.php)';
    }

    /**
     * Действия API, которые ничего не меняют (единственный источник правды).
     *
     * Список читают дымовой прогон (`Service_Dev_Smoke::api_urls()`) и аудит
     * (`dev:audit --check=api_read_only`), поэтому он лежит в конфиге, а не в
     * этих методах: разъехавшиеся копии списка — это либо несуществующий
     * охват прогона, либо 405 на живом чтении.
     *
     * @return  array
     */
    public static function api_read_only_actions()
    {
        static $actions;

        if ($actions === NULL)
        {
            $config  = Kohana::$config->load('api');
            $config  = ($config instanceof Kohana_Config_Group) ? $config->as_array() : (array) $config;

            $actions = isset($config['read_only_actions']) ? (array) $config['read_only_actions'] : array();
        }

        return $actions;
    }

    /**
     * Защита изменяющих запросов JSON API от повтора (идемпотентность).
     *
     * Включается сама для API-контроллеров (`Controller_*_Api`) и только тогда,
     * когда клиент прислал ключ — заголовок `Idempotency-Key` или параметр
     * `idempotency_key`. Без ключа поведение прежнее, поэтому ни веб-формы, ни
     * дымовой прогон этого не замечают.
     *
     * Зачем в базовом классе: повтор запроса — это второй документ (заявка,
     * заказ, приёмка). Механизм один для всех модулей, и модулю не нужно
     * ничего менять: достаточно быть API-контроллером. Слой MCP защищён своим
     * `idempotency_key` (`erp_804_mcp_calls`), обычный JSON API теперь тоже.
     *
     * @var bool
     */
    protected $_idempotency_enabled = TRUE;

    /**
     * @var array|null Что решили про текущий запрос: state, id, row
     */
    protected $_idempotency_plan;

    /**
     * Выполнение запроса с защитой от повтора.
     *
     * Повтор с тем же ключом и телом получает сохранённый ответ (операция не
     * выполняется второй раз), тот же ключ с другим телом — 409 `conflict`,
     * параллельный запрос — 409 `in_progress`.
     *
     * @return  Response
     */
    public function execute()
    {
        // Сначала метод запроса: изменяющее действие API по GET — это либо
        // ошибка клиента, либо ссылка, которую открыл краулер. Отвечаем 405 до
        // любой работы с данными (и до ключа идемпотентности).
        $method_error = $this->_api_method_error();

        if ($method_error !== NULL)
        {
            $this->_json_fail($method_error, 'method_not_allowed', 405);

            return $this->response;
        }

        $this->_idempotency_plan = $this->_idempotency_begin();

        if ($this->_idempotency_plan === NULL)
        {
            return parent::execute();
        }

        $state = $this->_idempotency_plan['state'];

        if ($state === 'replay')
        {
            return $this->_idempotency_replay($this->_idempotency_plan['row']);
        }

        if ($state === 'conflict' OR $state === 'in_progress')
        {
            $this->_json_fail(
                $state === 'conflict'
                    ? 'Этот Idempotency-Key уже использован с другим запросом — повторите с новым ключом'
                    : 'Запрос с этим Idempotency-Key уже выполняется',
                $state,
                409
            );

            return $this->response;
        }

        try
        {
            $response = parent::execute();
        }
        catch (Exception $e)
        {
            // Действие не выполнено — ключ освобождаем, иначе повтор этого же
            // запроса упрётся в «уже выполняется» на 5 минут.
            $this->_idempotency_release();
            throw $e;
        }

        $this->_idempotency_finish($response);

        return $response;
    }

    /**
     * Ключ идемпотентности текущего запроса, если защита применима.
     *
     * Защита работает только для изменяющих методов: GET и HEAD ничего не
     * создают, и хранить их ответы незачем.
     *
     * @return  array|null  id ключа, scope, key, hash
     */
    protected function _idempotency_begin()
    {
        if ( ! $this->_idempotency_enabled
            OR ! preg_match('/_Api$/', get_class($this))
            OR ! class_exists('Service_Erp100_Idempotency'))
        {
            return NULL;
        }

        $method = $this->request->method();

        if ($method !== Request::POST AND $method !== Request::PUT AND $method !== Request::DELETE)
        {
            return NULL;
        }

        $service = Service_Erp100_Idempotency::instance();

        $key = $service->key(
            $this->request->headers('Idempotency-Key'),
            $this->_idempotency_key_param()
        );

        if ($key === NULL OR ! $service->available())
        {
            return NULL;
        }

        // Тело берём у Request, а не из $_POST: у внутренних вызовов и у PUT с
        // телом глобальный массив пуст, и «другой запрос» выглядел бы тем же.
        $params = $this->request->post();

        if ( ! is_array($params) OR ! $params)
        {
            $body = $this->request->body();

            if (is_string($body) AND $body !== '')
            {
                parse_str($body, $params);
            }
        }

        $scope = $this->_idempotency_scope();
        $hash  = $service->fingerprint($method, $this->request->uri(), is_array($params) ? $params : array());

        $plan = $service->begin($scope, $key, $this->_idempotency_user_id(), $hash, array(
            'method'  => $method,
            'uri'     => $this->request->uri(),
            'request' => is_array($params) ? $params : array(),
        ));

        $plan['scope'] = $scope;
        $plan['key']   = $key;

        return $plan;
    }

    /**
     * Ключ из параметра запроса (клиенты без заголовков: формы, curl-скрипты).
     *
     * @return  mixed
     */
    protected function _idempotency_key_param()
    {
        $key = $this->request->post('idempotency_key');

        if ($key === NULL OR $key === '')
        {
            $key = $this->request->query('idempotency_key');
        }

        return $key;
    }

    /**
     * Границы ключа: запросы разных действий (и модулей) не перекрываются.
     *
     * @return  string  erpNNN:контроллер:действие
     */
    protected function _idempotency_scope()
    {
        $class = strtolower(get_class($this));

        // controller_erp403_api → erp403:api
        $name = preg_replace('/^controller_/', '', $class);

        return $name.':'.$this->request->action();
    }

    /**
     * @return  int|null
     */
    protected function _idempotency_user_id()
    {
        if (isset($this->_current_user) AND $this->_current_user)
        {
            return (int) $this->_current_user->id;
        }

        if (class_exists('Auth') AND Auth::instance()->logged_in())
        {
            $user = Auth::instance()->get_user();

            return $user ? (int) $user->id : NULL;
        }

        return NULL;
    }

    /**
     * Повтор: отдаём сохранённый ответ, действие не выполняем.
     *
     * @param   array $row
     * @return  Response
     */
    protected function _idempotency_replay($row)
    {
        if ( ! is_array($row) OR empty($row['response_code']))
        {
            // Ключ занят, но ответа нет (например, ответ был слишком большим) —
            // честнее сказать об этом, чем молча выполнить операцию снова.
            $this->_json_fail(
                'Этот Idempotency-Key уже выполнялся, но ответ не сохранён — проверьте результат по журналу и повторите с новым ключом',
                'conflict',
                409
            );

            return $this->response;
        }

        // before() не выполнялся: контроллер не успел снять авто-рендер.
        $this->auto_render = FALSE;

        $this->response
            ->status((int) $row['response_code'])
            ->headers('Content-Type', $row['response_type'] ? $row['response_type'] : 'application/json; charset=utf-8')
            ->headers('Idempotent-Replay', 'true')
            ->body((string) $row['response_body']);

        return $this->response;
    }

    /**
     * Запомнить ответ для повторов.
     *
     * @param   Response $response
     * @return  void
     */
    protected function _idempotency_finish($response)
    {
        if ( ! $this->_idempotency_plan OR empty($this->_idempotency_plan['id']) OR ! $response)
        {
            return;
        }

        Service_Erp100_Idempotency::instance()->finish(
            $this->_idempotency_plan['id'],
            $response->status(),
            $response->body(),
            $response->headers('Content-Type'),
            $this->_idempotency_entity_id($response)
        );
    }

    /**
     * Освободить ключ (действие упало).
     *
     * @return  void
     */
    protected function _idempotency_release()
    {
        if ($this->_idempotency_plan AND ! empty($this->_idempotency_plan['id']))
        {
            Service_Erp100_Idempotency::instance()->release($this->_idempotency_plan['id']);
        }
    }

    /**
     * Что за сущность создал запрос: `id` в успешном ответе.
     *
     * Хранится для разбора инцидентов: видно, какой документ соответствует ключу.
     *
     * @param   Response $response
     * @return  int|null
     */
    protected function _idempotency_entity_id($response)
    {
        $body = json_decode((string) $response->body(), TRUE);

        if ( ! is_array($body) OR empty($body['success']) OR ! isset($body['data']))
        {
            return NULL;
        }

        $data = $body['data'];

        if (is_array($data) AND isset($data['id']) AND is_scalar($data['id']))
        {
            return (int) $data['id'];
        }

        if (is_array($data) AND isset($data['request']) AND is_array($data['request']) AND isset($data['request']['id']))
        {
            return (int) $data['request']['id'];
        }

        return NULL;
    }

    /**
     * Быстрый ответ JSON для контроллеров (AJAX/API)
     */
    public function _json_response($data, $code = 200)
    {
        
        $this->auto_render = FALSE;
        $this->response
            ->status($code)
            ->headers('Content-Type', 'application/json; charset=utf-8')
            ->body(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

}