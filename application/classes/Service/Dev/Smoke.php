<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * «Дымовой» прогон маршрутов внутри процесса (без HTTP-сервера).
 *
 * Задача dev:smoke запрашивает каждый зарегистрированный маршрут через
 * Request::factory() и сообщает только о том, что действительно сломано:
 *   * 500 и необработанные исключения (фатал в контроллере/модели/view);
 *   * пустые ответы 200;
 *   * запросы, упавшие в БД.
 * 302/403/404 — ожидаемые ответы (нужна авторизация, страницы не существует),
 * поэтому в отчёт об ошибках они не попадают.
 *
 * Стоимость: один прогон ~40 маршрутов — секунды, без внешнего сервера.
 *
 * @package Dev
 */
class Service_Dev_Smoke {

    /** @var array  коды, которые считаются нормальным ответом */
    protected $_expected = [200, 301, 302, 303, 400, 401, 403, 404, 405, 409, 422];

    /**
     * Маршруты проекта: имя, URI, ожидаемый код (NULL — любой допустимый).
     *
     * @return array
     */
    public function urls()
    {
        $urls = [
            ['url' => '/',            'name' => 'главная'],
            ['url' => '/auth/login',  'name' => 'вход'],
            ['url' => '/auth/register', 'name' => 'регистрация'],
            // Публичные страницы: их читают до входа, и падение здесь замечает
            // только посетитель — в общем списке маршрутов их не было.
            ['url' => '/about',       'name' => 'о сервисе'],
            ['url' => '/terms',       'name' => 'условия использования'],
            ['url' => '/privacy',     'name' => 'политика конфиденциальности'],
        ];

        foreach (Route::all() as $name => $route)
        {
            if (in_array($name, ['default', 'universal_param', 'static_pages'], TRUE))
            {
                continue;
            }

            $defaults = $route->defaults();

            // Подставляем значения для обязательных параметров маршрута
            $params = [];

            foreach ($defaults as $key => $value)
            {
                if ( ! is_scalar($value) OR $value === '')
                {
                    continue;
                }

                $params[$key] = $value;
            }

            // Числовые плейсхолдеры (<id>) и прочие обязательные параметры
            foreach (['id', 'item_id', 'warehouse_id'] as $key)
            {
                $params[$key] = 1;
            }

            try
            {
                $uri = $route->uri($params);
            }
            catch (Exception $e)
            {
                continue;
            }

            if ($uri === '' OR $uri === '/')
            {
                continue;
            }

            $urls[] = ['url' => '/'.ltrim($uri, '/'), 'name' => $name];
        }

        return $urls;
    }

    /**
     * Действия JSON API, которые только читают: по ним дымовой прогон ходит
     * реально, и это ловит 500 в API до того, как их увидит агент или клиент.
     *
     * Список — `application/config/api.php`. Он же управляет контрактом методов
     * в `Controller_Base` (изменяющее действие по GET получает 405), поэтому
     * копий списка нет: разъехавшиеся копии означали бы либо несуществующий
     * охват прогона, либо 405 на живом чтении.
     *
     * Имена точные (`in_array`, а не префикс): иначе `request` затянул бы в
     * прогон `request_create`, а `order` — `order_act`, и «дымовой» запрос
     * создавал бы настоящие документы.
     *
     * @return  array
     */
    protected function _read_only_actions()
    {
        return Controller_Base::api_read_only_actions();
    }

    /**
     * Адреса публичных действий API-контроллеров (`Controller_*_Api`).
     *
     * Почему отдельно от `action_urls()`. Маршруты API построены как
     * `/procurement/api/<action>`, поэтому рефлексия по имени контроллера
     * (`/erp403_api/stats`) давала 404 и не проверяла ничего: 500 в API-действии
     * не ловил ни один прогон. Здесь адрес собирается из самого маршрута —
     * подставляется конкретное имя действия.
     *
     * Ходим только по действиям из `$_read_only_actions`: изменяющие действия
     * вызывать «на всякий случай» нельзя (это второй документ в базе).
     *
     * @return  array
     */
    public function api_urls()
    {
        $urls = array();

        foreach (Route::all() as $name => $route)
        {
            $defaults = $route->defaults();

            if (empty($defaults['controller']))
            {
                continue;
            }

            // Маршрут задаёт пару directory/controller («Erp403» + «Api»), из
            // которой имя класса складывается как Controller_Erp403_Api
            $controller = 'Controller_';

            if ( ! empty($defaults['directory']))
            {
                $controller .= trim($defaults['directory'], '_').'_';
            }

            $controller .= ltrim($defaults['controller'], '_');

            if (substr($controller, -4) !== '_Api' OR ! class_exists($controller))
            {
                continue;
            }

            try
            {
                $reflection = new ReflectionClass($controller);
            }
            catch (Throwable $e)
            {
                continue;
            }

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
            {
                $method_name = $method->getName();

                if (strpos($method_name, 'action_') !== 0 OR $method->getNumberOfRequiredParameters() > 0)
                {
                    continue;
                }

                $action = substr($method_name, strlen('action_'));

                if ( ! in_array($action, $this->_read_only_actions(), TRUE))
                {
                    continue;
                }

                $uri = $this->_route_uri($route, array(
                    'action'       => $action,
                    'id'           => 1,
                    'item_id'      => 1,
                    'warehouse_id' => 1,
                ));

                if ($uri === NULL OR $uri === '' OR $uri === '/')
                {
                    continue;
                }

                // Параметры, которые действия читают из строки запроса
                $urls[] = array(
                    'url'  => '/'.ltrim($uri, '/').'?id=1&item_id=1&warehouse_id=1&limit=5',
                    'name' => $controller.'::'.$method_name,
                );
            }
        }

        // Один адрес проверяем один раз: маршрут мог быть объявлен дважды
        $unique = array();

        foreach ($urls as $entry)
        {
            $unique[$entry['url']] = $entry;
        }

        return array_values($unique);
    }

    /**
     * Собрать адрес маршрута, доставляя недостающие обязательные параметры.
     *
     * `Route::uri()` падает с «Required route parameter not passed», если у
     * маршрута есть обязательный плейсхолдер, которого нет в параметрах (у
     * модульных маршрутов это `<action>`, а бывает `<slug>`, `<type>` и т.п.).
     * Вместо списка известных плейсхолдеров читаем имя из текста ошибки и
     * подставляем единицу — так метод переживёт новые маршруты.
     *
     * @param   Route   $route
     * @param   array   $params
     * @return  string|null
     */
    protected function _route_uri($route, array $params)
    {
        for ($attempt = 0; $attempt < 5; $attempt++)
        {
            try
            {
                return (string) $route->uri($params);
            }
            catch (Exception $e)
            {
                if ( ! preg_match('/Required route parameter not passed: ([A-Za-z0-9_]+)/', $e->getMessage(), $found))
                {
                    return NULL;
                }

                if (array_key_exists($found[1], $params))
                {
                    // Повторно просит тот же параметр — маршрут нам не по зубам
                    return NULL;
                }

                $params[$found[1]] = 1;
            }
        }

        return NULL;
    }

    /**
     * Дополнительные адреса из рефлексии по контроллерам: для каждого
     * Controller_<Модуль>_<Под> перебираются методы action_* из «безопасного»
     * списка чтения (index, list, view, form, tree, report, …) и превращаются
     * в URL вида /erp218_widget/llm_status.
     *
     * Действия, меняющие данные (save, delete, upload, process, …), не вызываются:
     * их нельзя запускать «на всякий случай». URL, не совпавший с маршрутом,
     * отсекается по коду 404 — это не ошибка.
     *
     * @return  array
     */
    public function action_urls()
    {
        $safe_prefixes = [
            'index', 'list', 'view', 'detail', 'form', 'show', 'dashboard', 'report',
            'tree', 'timeline', 'calendar', 'kanban', 'search', 'status', 'widget',
            'about', 'help', 'docs', 'stats', 'settings', 'export_dialog', 'print',
        ];

        $urls = [];

        foreach (Kohana::modules() as $module => $path)
        {
            $dir = $path.'classes/Controller/';

            if ( ! is_dir($dir))
            {
                continue;
            }

            // Префикс модуля в URL: erp218_module_manager → erp218
            $prefix = strtolower(strtok($module, '_'));

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file)
            {
                if ( ! $file->isFile() OR substr($file->getFilename(), -4) !== '.php')
                {
                    continue;
                }

                $relative = substr($file->getPathname(), strlen($dir), -4);
                $relative = str_replace(DIRECTORY_SEPARATOR, '_', $relative);

                if (stripos($relative, 'Controller_') === 0)
                {
                    $relative = substr($relative, strlen('Controller_'));
                }

                // Отбрасываем упоминание модуля в имени класса: Erp218_Widget → Widget
                $parts = explode('_', $relative);

                if (isset($parts[0]) AND strtolower($parts[0]) === $prefix)
                {
                    array_shift($parts);
                }

                if ( ! $parts)
                {
                    continue;
                }

                $class = 'Controller_'.$relative;

                if ( ! class_exists($class))
                {
                    continue;
                }

                try
                {
                    $reflection = new ReflectionClass($class);
                }
                catch (Throwable $e)
                {
                    continue;
                }

                if ($reflection->isAbstract())
                {
                    continue;
                }

                $controller_url = '/'.$prefix.'_'.strtolower(implode('_', $parts));

                foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
                {
                    $name = $method->getName();

                    if (strpos($name, 'action_') !== 0 OR $method->getNumberOfRequiredParameters() > 0)
                    {
                        continue;
                    }

                    $action = substr($name, strlen('action_'));

                    $safe = FALSE;

                    foreach ($safe_prefixes as $allowed)
                    {
                        if (strpos($action, $allowed) === 0)
                        {
                            $safe = TRUE;
                            break;
                        }
                    }

                    if ( ! $safe)
                    {
                        continue;
                    }

                    $urls[] = [
                        'url'  => $controller_url.'/'.$action,
                        'name' => $class.'::'.$name,
                    ];
                }
            }
        }

        // Один и тот же маршрут не проверяем дважды
        $unique = [];

        foreach ($urls as $entry)
        {
            $unique[$entry['url']] = $entry;
        }

        return array_values($unique);
    }

    /**
     * Прогоняет маршруты и возвращает отчёт.
     *
     * @param   array    $urls   список ['url' => …, 'name' => …]
     * @param   callable $on_row необязательный наблюдатель: function(array $row)
     * @return  array    ['rows' => […], 'stats' => ['total','ok','bad','time']]
     */
    public function run(array $urls, $on_row = NULL)
    {
        $rows = [];
        $ok   = 0;
        $bad  = 0;

        // В CLI нет переменных веб-сервера, а Kohana (URL::base, Request::$client_ip,
        // ссылки в представлениях) их ожидает — подставляем значения по умолчанию.
        $restore = $this->_emulate_http_server();

        $started = microtime(TRUE);

        foreach ($urls as $entry)
        {
            $url  = $entry['url'];
            $name = isset($entry['name']) ? $entry['name'] : '';

            $row = [
                'url'      => $url,
                'name'     => $name,
                'status'   => 0,
                'error'    => NULL,
                'bytes'    => 0,
                'time'     => 0.0,
            ];

            $before = microtime(TRUE);

            try
            {
                // Своя сессия/запрос на каждый маршрут — эмулируем независимый визит
                $request  = Request::factory($url)->method(Request::GET);
                $response = $request->execute();

                $row['status'] = $response->status();
                $row['bytes']  = strlen($response->body());

                if ( ! in_array($row['status'], $this->_expected, TRUE))
                {
                    $row['error'] = 'HTTP '.$row['status'].': '.$this->_short($response->body());
                }
                else
                {
                    // Пустой ответ нормален для action-роутов (например, запись cookie)
                    $row['empty'] = ($row['bytes'] === 0);
                }
            }
            catch (HTTP_Exception $e)
            {
                // 403/404/500 из контроллеров: 5xx — это ошибка
                $row['status'] = $e->getCode();

                if ($e->getCode() >= 500)
                {
                    $row['error'] = get_class($e).': '.$e->getMessage();
                }
            }
            catch (Throwable $e)
            {
                $row['status'] = 500;
                $row['error']  = get_class($e).': '.$e->getMessage();
            }

            $row['time'] = round(microtime(TRUE) - $before, 3);

            if ($row['error'] === NULL)
            {
                $ok++;
            }
            else
            {
                $bad++;
            }

            $rows[] = $row;

            if ($on_row !== NULL)
            {
                call_user_func($on_row, $row);
            }
        }

        $this->_restore_server($restore);

        return [
            'rows'  => $rows,
            'stats' => [
                'total' => count($rows),
                'ok'    => $ok,
                'bad'   => $bad,
                'time'  => round(microtime(TRUE) - $started, 3),
            ],
        ];
    }

    /**
     * Вход под пользователем (для прогона страниц, требующих авторизации).
     *
     * @param   string|integer|null  $who  e-mail, логин или ID; NULL — первый администратор
     * @return  string|null          описание пользователя или NULL, если войти не удалось
     */
    public function login_as($who = NULL)
    {
        try
        {
            $user = ORM::factory('User');

            if ($who === NULL)
            {
                // По умолчанию — администратор: он видит больше всего страниц
                $user_id = DB::select(['ru.user_id', 'user_id'])
                    ->from(['roles_users', 'ru'])
                    ->join(['roles', 'r'], 'INNER')
                    ->on('r.id', '=', 'ru.role_id')
                    ->where('r.name', '=', 'admin')
                    ->order_by('ru.user_id', 'ASC')
                    ->limit(1)
                    ->execute()
                    ->get('user_id');

                if ($user_id === NULL)
                {
                    return NULL;
                }

                $user = ORM::factory('User', (int) $user_id);
            }
            elseif (is_numeric($who))
            {
                $user = ORM::factory('User', (int) $who);
            }
            else
            {
                $user = ORM::factory('User')
                    ->where('email', '=', $who)
                    ->or_where('username', '=', $who)
                    ->find();
            }

            if ( ! $user->loaded())
            {
                return NULL;
            }

            $auth = Auth::instance();

            // В CLI сессия сама не стартует. Обычно её поднимает Auth::instance()
            // (первый вызов → Session::instance → session_start), но в тестах
            // bootstrap гасит сессию через logout(TRUE) — и тогда force_login()
            // падает на session_regenerate_id() без активной сессии. Дымовой
            // прогон после этого проверяет страницы анонимно, а «403» выглядит
            // нормальным ответом: реальные дефекты страниц не видны.
            if (session_status() !== PHP_SESSION_ACTIVE AND ! headers_sent())
            {
                @session_start();
            }

            $auth->force_login($user);

            return $user->username.' <'.$user->email.'> (роли: '.implode(', ', $user->roles->find_all()->as_array(NULL, 'name')).')';
        }
        catch (Throwable $e)
        {
            return NULL;
        }
    }

    /**
     * Подставляет переменные окружения веб-сервера, если их нет.
     *
     * @return  array  список имён, которые были добавлены (их нужно убрать после)
     */
    protected function _emulate_http_server()
    {
        $defaults = [
            'SERVER_NAME'     => 'localhost',
            'SERVER_PORT'     => '80',
            'HTTP_HOST'       => 'localhost',
            'REMOTE_ADDR'     => '127.0.0.1',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_METHOD'  => 'GET',
            'REQUEST_URI'     => '/',
            'SCRIPT_NAME'     => '/index.php',
            'HTTP_USER_AGENT' => 'PromERP dev:smoke',
            'HTTPS'           => '',
        ];

        $added = [];

        foreach ($defaults as $key => $value)
        {
            if ( ! array_key_exists($key, $_SERVER))
            {
                $_SERVER[$key] = $value;
                $added[] = $key;
            }
        }

        return $added;
    }

    /**
     * @param   array  $keys
     * @return  void
     */
    protected function _restore_server(array $keys)
    {
        foreach ($keys as $key)
        {
            unset($_SERVER[$key]);
        }
    }

    /**
     * Короткое человекочитаемое сообщение об ошибке из HTML-ответа.
     *
     * @param   string  $body
     * @return  string
     */
    protected function _short($body)
    {
        // Из страницы ошибки Kohana убираем CSS/JS — оставляем сам текст
        $text = preg_replace('#<(style|script)\b[^>]*>.*?</\1>#is', ' ', $body);
        $text = strip_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);

        return mb_substr($text, 0, 200);
    }
}
