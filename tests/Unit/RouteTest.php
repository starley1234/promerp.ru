<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Маршрутизация: базовые роуты и роуты модулей, зарегистрированные в init.php.
 */
class RouteTest extends TestCase
{
    public function test_default_route_exists()
    {
        $this->assertNotNull(\Route::get('default'));
    }

    public function test_module_routes_are_registered()
    {
        $routes = \Route::all();

        $this->assertGreaterThan(3, count($routes), 'Должны быть зарегистрированы роуты модулей');

        foreach (['erp208_events', 'erp217', 'erp218', 'erp228', 'erp227'] as $prefix)
        {
            $found = FALSE;

            foreach (array_keys($routes) as $name)
            {
                if (strpos($name, $prefix) === 0)
                {
                    $found = TRUE;
                    break;
                }
            }

            $this->assertTrue($found, 'Не найдены роуты модуля '.$prefix);
        }
    }

    public function test_modules_with_init_register_routes()
    {
        $violations = [];

        foreach (\Kohana::modules() as $name => $path)
        {
            if ( ! is_file($path.'init.php'))
            {
                continue;
            }

            $contents = file_get_contents($path.'init.php');

            if (strpos($contents, 'Route::set') === FALSE)
            {
                $violations[] = $name;
            }
        }

        $this->assertSame([], $violations, 'init.php модуля должен регистрировать роуты');
    }

    /**
     * Все роуты должны вести на существующие контроллеры и действия.
     *
     * Проверка построена на рефлексии: защищённые свойства роута (_uri, _regex)
     * читаются напрямую, из них собираются варианты URI (значения параметров
     * берутся из defaults, из regex-альтернатив или подставляется «1» для id).
     * Для каждого варианта роут сопоставляется сам с собой, вычисляется класс
     * контроллера и проверяется наличие public-метода action_<action>.
     * Роут считается исправным, если хотя бы один вариант приводит к
     * существующему действию.
     *
     * Так ловятся опечатки в именах контроллеров и действий, а также ошибки вида
     * 'counterparties/contact_<action>' → action_save: параметр роута с именем
     * action перекрывает имя метода.
     */
    public function test_every_route_points_to_existing_controller_and_action()
    {
        $violations = array();
        $checked    = 0;

        foreach (\Route::all() as $name => $route)
        {
            $samples = $this->sample_uris($route);

            if ( ! $samples)
            {
                // Роут динамический (значения параметров неизвестны) — пропускаем
                continue;
            }

            $ok         = FALSE;
            $first_fail = NULL;

            foreach ($samples as $sample)
            {
                try
                {
                    $params = $route->matches(\Request::factory($sample));
                }
                catch (\Exception $e)
                {
                    $first_fail = $first_fail ?: $name.' ('.$sample.') → исключение: '.$e->getMessage();
                    continue;
                }

                if ($params === FALSE)
                {
                    // Вариант не подошёл под собственный роут
                    continue;
                }

                $checked++;

                $target = array(
                    'directory'  => isset($params['directory']) ? $params['directory'] : '',
                    'controller' => isset($params['controller']) ? $params['controller'] : '',
                    'action'     => isset($params['action']) ? $params['action'] : 'index',
                );

                $class = $this->controller_class($target);

                if ( ! class_exists($class))
                {
                    $first_fail = $first_fail ?: $name.' ('.$sample.') → нет класса '.$class;
                    continue;
                }

                $reflection = new \ReflectionClass($class);
                $method     = 'action_'.$target['action'];

                if ( ! $reflection->hasMethod($method))
                {
                    $first_fail = $first_fail ?: $name.' ('.$sample.') → '.$class.' не имеет '.$method;
                    continue;
                }

                if ( ! $reflection->getMethod($method)->isPublic())
                {
                    $first_fail = $first_fail ?: $name.' ('.$sample.') → '.$class.'::'.$method.' не public';
                    continue;
                }

                $ok = TRUE;
                break;
            }

            if ( ! $ok AND $first_fail !== NULL)
            {
                $violations[] = $first_fail;
            }
        }

        $this->assertGreaterThan(15, $checked, 'Слишком мало проверенных роутов — возможно, роуты не зарегистрированы');
        $this->assertSame(array(), $violations, 'Роуты ведут на несуществующие действия:'."\n".implode("\n", $violations));
    }

    /**
     * Маршруты модуля контрагентов должны указывать на ожидаемые действия.
     *
     * Регрессия: роут 'counterparties/contact_<action>' вызывал action_save
     * (создание контрагента) вместо action_contact_save.
     */
    public function test_erp301_counterparty_routes_are_wired_to_expected_actions()
    {
        $expected = array(
            'counterparties'                    => array('Index', 'index'),
            'counterparties/list'               => array('Counterparty', 'index'),
            'counterparties/view/1'             => array('Counterparty', 'view'),
            'counterparties/edit'               => array('Counterparty', 'edit'),
            'counterparties/edit/1'             => array('Counterparty', 'edit'),
            'counterparties/save'               => array('Counterparty', 'save'),
            'counterparties/delete'             => array('Counterparty', 'delete'),
            'counterparties/contact_save'       => array('Counterparty', 'contact_save'),
            'counterparties/contact_delete'     => array('Counterparty', 'contact_delete'),
            'counterparties/requisite_save'     => array('Counterparty', 'requisite_save'),
            'counterparties/contracts'          => array('Contract', 'index'),
            'counterparties/contracts/edit/1'   => array('Contract', 'edit'),
            'counterparties/contract_save'      => array('Contract', 'save'),
            'counterparties/contract_terminate' => array('Contract', 'terminate'),
            'counterparties/api/search'         => array('Api', 'search'),
            'counterparties/api/card/1'         => array('Api', 'card'),
        );

        $violations = array();

        foreach ($expected as $uri => $target)
        {
            list($controller, $action) = $target;

            $match = $this->dispatch($uri);

            if ($match === NULL)
            {
                $violations[] = $uri.' → не сопоставлен ни одним роутом';
                continue;
            }

            if ($match['controller'] !== $controller OR $match['action'] !== $action)
            {
                $violations[] = sprintf('%s → %s::%s (ожидалось %s::%s)',
                    $uri, $match['controller'], $match['action'], $controller, $action);
            }
        }

        $this->assertSame(array(), $violations, 'Маршруты контрагентов настроены неверно:'."\n".implode("\n", $violations));
    }

    /**
     * Варианты URI для роута (до 6 комбинаций значений параметров).
     *
     * @param   \Route  $route
     * @return  array
     */
    protected function sample_uris(\Route $route)
    {
        $pattern = $this->route_property($route, '_uri');

        if ( ! is_string($pattern) OR $pattern === '')
        {
            return array();
        }

        if ( ! preg_match_all('/<([a-zA-Z0-9_]+)>/', $pattern, $matches))
        {
            return array();
        }

        $defaults = $route->defaults();
        $regex    = (array) $this->route_property($route, '_regex');

        $candidates = array();

        foreach ($matches[1] as $param)
        {
            $values = array();

            if (array_key_exists($param, $defaults) AND $defaults[$param] !== NULL)
            {
                $values[] = (string) $defaults[$param];
            }

            if (isset($regex[$param]))
            {
                foreach ($this->regex_alternatives($regex[$param]) as $alt)
                {
                    $values[] = $alt;
                }
            }

            if ( ! $values AND ($param === 'id' OR substr($param, -3) === '_id'))
            {
                $values[] = '1';
            }

            if ( ! $values)
            {
                // Значение параметра неизвестно — вариант URI собрать нельзя
                return array();
            }

            $candidates[$param] = array_slice(array_values(array_unique($values)), 0, 3);
        }

        // Декартово произведение с ограничением в 6 вариантов
        $samples = array(array());
        $limit   = 6;

        foreach ($candidates as $param => $values)
        {
            $next = array();

            foreach ($samples as $sample)
            {
                foreach ($values as $value)
                {
                    if (count($next) >= $limit)
                    {
                        break 2;
                    }

                    $sample[$param] = $value;
                    $next[]         = $sample;
                }
            }

            $samples = $next;
        }

        $uris = array();

        foreach ($samples as $params)
        {
            try
            {
                $uris[] = $route->uri($params);
            }
            catch (\Exception $e)
            {
                // вариант собрать нельзя — пропускаем
            }
        }

        return array_values(array_unique($uris));
    }

    /**
     * Литеральные альтернативы параметра роута: '(index|list)' → ['index', 'list'].
     *
     * @param   string  $regex
     * @return  array
     */
    protected function regex_alternatives($regex)
    {
        $regex = (string) $regex;

        if ( ! preg_match_all('/[a-zA-Z0-9_]{2,}/', $regex, $matches))
        {
            return array();
        }

        // Служебные слова регулярных выражений нам не нужны
        return array_values(array_diff($matches[0], array('d', 'w', 's')));
    }

    /**
     * Чтение защищённого свойства роута через рефлексию (только чтение).
     *
     * @param   \Route  $route
     * @param   string  $name
     * @return  mixed
     */
    protected function route_property(\Route $route, $name)
    {
        try
        {
            $property = new \ReflectionProperty(get_class($route), $name);
            $property->setAccessible(TRUE);

            return $property->getValue($route);
        }
        catch (\Exception $e)
        {
            return NULL;
        }
    }

    /**
     * Прогон URI по роутам в порядке регистрации (как Request::execute()).
     *
     * @param   string  $uri
     * @return  array|NULL  параметры роута
     */
    protected function dispatch($uri)
    {
        $request = \Request::factory($uri);

        foreach (\Route::all() as $route)
        {
            $params = $route->matches($request);

            if ($params !== FALSE)
            {
                return array(
                    'directory'  => isset($params['directory']) ? $params['directory'] : '',
                    'controller' => isset($params['controller']) ? $params['controller'] : '',
                    'action'     => isset($params['action']) ? $params['action'] : 'index',
                );
            }
        }

        return NULL;
    }

    /**
     * Имя класса контроллера по параметрам роута.
     *
     * @param   array   $params
     * @return  string
     */
    protected function controller_class(array $params)
    {
        $directory = trim((string) $params['directory'], '/');

        return 'Controller_'.($directory !== '' ? str_replace('/', '_', $directory).'_' : '').$params['controller'];
    }

    public function test_url_base_does_not_throw_for_localhost()
    {
        // URL::base() выбрасывает "Untrusted host", если хост не в url.trusted_hosts
        $trusted = (array) \Kohana::$config->load('url')->get('trusted_hosts');

        $this->assertNotEmpty($trusted, 'Заполните url.trusted_hosts (см. application/config.dist/url.php)');
        $this->assertTrue(
            \URL::is_trusted_host('127.0.0.1', $trusted) OR \URL::is_trusted_host('127.0.0.1:8080', $trusted),
            'Локальный хост должен быть доверенным'
        );
    }
}
