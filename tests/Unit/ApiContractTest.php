<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Контракт JSON API: методы запросов и список действий чтения.
 *
 * У изменяющих действий API метод — POST (PUT/DELETE по смыслу), у чтения —
 * GET. По имени действия это не угадывается (`request` читает заявку,
 * `request_create` её создаёт), поэтому список действий чтения объявлен явно в
 * `application/config/api.php` и используется сразу тремя местами:
 * `Controller_Base` (405 на изменяющее действие по GET), дымовой прогон
 * (ходит только по чтению) и аудит (`dev:audit --check=api_read_only`).
 *
 * Тест охраняет именно список и решение о методе — то, что раньше приводило к
 * 500: `GET /mcp/api/key` доходил до выдачи ключа и падал с «Undefined array
 * key "scopes"` вместо понятного отказа (см. `docs/IMPROVEMENTS.md` B16).
 */
class ApiContractTest extends TestCase
{
    /**
     * Устаревшая запись в списке: действия с таким именем нет ни в одном
     * `Controller_*_Api`. Такая запись тихо расширяет «разрешённое чтение» и
     * ослабляет прогон.
     */
    public function test_read_only_list_has_no_stale_entries()
    {
        $declared = $this->_api_actions();
        $stale    = array();

        foreach (\Controller_Base::api_read_only_actions() as $action)
        {
            if ( ! isset($declared[$action]))
            {
                $stale[] = $action;
            }
        }

        $this->assertSame(array(), $stale,
            'В application/config/api.php есть записи без действия в коде: '.implode(', ', $stale));
    }

    /**
     * Список вообще заполнен и покрывает все модули, у которых есть API.
     */
    public function test_read_only_list_is_filled()
    {
        $this->assertGreaterThan(50, count(\Controller_Base::api_read_only_actions()));

        $this->assertGreaterThan(100, count((new \Service_Dev_Smoke)->api_urls()),
            'Дымовой прогон должен обходить действия чтения API');
    }

    /**
     * Решение о методе: GET на изменение — отказ, POST на изменение — норма,
     * GET на чтение — норма, внутренний запрос — не наше дело.
     */
    public function test_method_decision_table()
    {
        $read    = $this->_first_action(\Controller_Base::api_read_only_actions());
        $mutate  = $this->_first_mutating_action();

        $this->assertNotNull($read, 'в списке чтения нет ни одного существующего действия');
        $this->assertNotNull($mutate, 'не нашлось изменяющего действия API для проверки');

        // чтение по GET — пропускаем
        $this->assertNull(\Controller_Base::api_method_error(
            $read['class'], $read['action'], \Request::GET, TRUE));

        // изменение по GET — отказ с внятным текстом
        $error = \Controller_Base::api_method_error(
            $mutate['class'], $mutate['action'], \Request::GET, TRUE);

        $this->assertNotNull($error, 'изменяющее действие по GET обязано получить отказ');
        $this->assertStringContainsString('POST', $error);

        // изменение по POST/PUT/DELETE — разрешено
        foreach (array(\Request::POST, \Request::PUT, \Request::DELETE) as $method)
        {
            $this->assertNull(\Controller_Base::api_method_error(
                $mutate['class'], $mutate['action'], $method, TRUE),
                'метод '.$method.' для изменяющего действия допустим');
        }

        // внутренний вызов (Request::factory из кода) не ломаем
        $this->assertNull(\Controller_Base::api_method_error(
            $mutate['class'], $mutate['action'], \Request::GET, FALSE));

        // не-API контроллер под контракт не попадает
        $this->assertNull(\Controller_Base::api_method_error(
            'Controller_Index', $mutate['action'], \Request::GET, TRUE));

        // служебное действие denied — ответ уже сформирован
        $this->assertNull(\Controller_Base::api_method_error(
            $mutate['class'], 'denied', \Request::GET, TRUE));
    }

    /**
     * Ни одно действие из списка чтения не читает POST-данные: иначе оно
     * изменяющее, и GET на него — ошибка классификации.
     */
    public function test_read_only_actions_do_not_read_post_data()
    {
        $declared = $this->_api_actions();
        $guilty   = array();

        foreach (\Controller_Base::api_read_only_actions() as $action)
        {
            if ( ! isset($declared[$action]))
            {
                continue;
            }

            $body = $declared[$action]['body'];

            if (strpos($body, 'request->post(') !== FALSE OR strpos($body, '$_POST') !== FALSE)
            {
                $guilty[] = $action;
            }
        }

        $this->assertSame(array(), $guilty,
            'Объявлены чтением, но читают POST-данные: '.implode(', ', $guilty));
    }

    /**
     * Все действия API, читающие POST-данные, либо наследуют `Controller_Base`
     * (там контракт методов централизован), либо проверяют метод сами.
     */
    public function test_mutating_actions_are_guarded()
    {
        $read_only = \Controller_Base::api_read_only_actions();
        $unguarded = array();

        foreach ($this->_api_actions() as $action => $info)
        {
            if (in_array($action, $read_only, TRUE))
            {
                continue;
            }

            $body = $info['body'];

            if (strpos($body, 'request->post(') === FALSE AND strpos($body, '$_POST') === FALSE)
            {
                continue;
            }

            if (strpos($body, 'Request::POST') !== FALSE OR strpos($body, '_require_post') !== FALSE)
            {
                continue;
            }

            if (is_subclass_of($info['class'], 'Controller_Base'))
            {
                continue;
            }

            $unguarded[] = $info['class'].'::'.$action;
        }

        $this->assertSame(array(), $unguarded,
            "Действия, которые принимают POST, но не проверяют метод и не наследуют Controller_Base:\n"
            .implode("\n", $unguarded));
    }

    /**
     * Действия всех API-контроллеров: имя действия (без `action_`) → класс и тело.
     *
     * @return array
     */
    protected function _api_actions()
    {
        $actions = array();

        foreach (\Kohana::modules() as $path)
        {
            $dir = $path.'classes/Controller/';

            if ( ! is_dir($dir))
            {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file)
            {
                if ( ! $file->isFile() OR substr($file->getFilename(), -4) !== '.php')
                {
                    continue;
                }

                $source = @file_get_contents($file->getPathname());

                if ($source === FALSE OR ! preg_match('/class\s+(\w+)/', $source, $class_found))
                {
                    continue;
                }

                $class = $class_found[1];

                if (substr($class, -4) !== '_Api' OR ! class_exists($class))
                {
                    continue;
                }

                $reflection = new \ReflectionClass($class);

                if ($reflection->isAbstract())
                {
                    continue;
                }

                foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method)
                {
                    $name = $method->getName();

                    if (strpos($name, 'action_') !== 0 OR $method->getNumberOfRequiredParameters() > 0)
                    {
                        continue;
                    }

                    $lines = file($method->getFileName());

                    $actions[substr($name, strlen('action_'))] = array(
                        'class' => $class,
                        'body'  => implode('', array_slice($lines, $method->getStartLine() - 1,
                            $method->getEndLine() - $method->getStartLine() + 1)),
                    );
                }
            }
        }

        return $actions;
    }

    /**
     * Первое действие из списка, которое реально существует.
     *
     * @param   array $actions
     * @return  array|null
     */
    protected function _first_action(array $actions)
    {
        $declared = $this->_api_actions();

        foreach ($actions as $action)
        {
            if (isset($declared[$action]))
            {
                return array('action' => $action, 'class' => $declared[$action]['class']);
            }
        }

        return NULL;
    }

    /**
     * Первое изменяющее действие API, читающее POST-данные.
     *
     * @return array|null
     */
    protected function _first_mutating_action()
    {
        $read_only = \Controller_Base::api_read_only_actions();

        foreach ($this->_api_actions() as $action => $info)
        {
            if (in_array($action, $read_only, TRUE))
            {
                continue;
            }

            if (strpos($info['body'], 'request->post(') !== FALSE OR strpos($info['body'], '$_POST') !== FALSE)
            {
                return array('action' => $action, 'class' => $info['class']);
            }
        }

        return NULL;
    }
}
