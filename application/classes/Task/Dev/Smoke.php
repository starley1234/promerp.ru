<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Дымовой прогон всех маршрутов внутри процесса (без HTTP-сервера).
 *
 *   bin/console dev:smoke              # все маршруты, отчёт по проблемным
 *   bin/console dev:smoke --all        # показать и успешные ответы
 *   bin/console dev:smoke --url=/erp218
 *   bin/console dev:smoke --json       # машиночитаемо
 *
 * Ловит ошибки, которые не видны при проверке синтаксиса: падения в контроллерах,
 * запросах и представлениях. 302/403/404 считаются нормальными ответами
 * (нужна авторизация или страницы нет), ошибкой — только 500 и исключения.
 *
 * @package Minion
 */
class Task_Dev_Smoke extends Minion_Task {

    protected $_options = [
        'url'      => NULL,
        'user'     => NULL,
        'no-auth'  => FALSE,
        'all'      => FALSE,
        'json'     => FALSE,
        'routes-only' => FALSE,
        'module'      => NULL,
    ];

    protected function _execute(?array $options = NULL)
    {
        $options = (array) $options;

        $smoke = new Service_Dev_Smoke;
        $urls  = $smoke->urls();

        // Дополнительно — действия контроллеров и публичные действия JSON API,
        // которые только читают данные
        if ( ! $this->_flag($options, 'routes-only'))
        {
            $urls = array_merge($urls, $smoke->action_urls(), $smoke->api_urls());
        }

        // Без авторизации большинство страниц отвечают 403 и ничего не проверяют,
        // поэтому по умолчанию входим первым администратором.
        $as_user = NULL;

        if ( ! $this->_flag($options, 'no-auth'))
        {
            $as_user = $smoke->login_as($this->_value($options, 'user'));
        }

        $only = $this->_value($options, 'url');

        if ($only)
        {
            $urls = [['url' => $only, 'name' => 'вручную']];
        }

        $show_all = $this->_flag($options, 'all');
        $json     = $this->_flag($options, 'json');
        $module   = $this->_value($options, 'module');

        // Ограничение прогона одним модулем: make check-module module=erp401_wms
        if ($module)
        {
            $id  = preg_match('/(\d{3})/', $module, $found) ? $found[1] : NULL;
            $urls = array_values(array_filter($urls, function ($entry) use ($module, $id) {
                if (stripos($entry['name'], $module) !== FALSE)
                {
                    return TRUE;
                }

                return ($id !== NULL AND stripos($entry['name'], 'erp'.$id) !== FALSE);
            }));

            if ( ! $urls)
            {
                echo 'Для модуля '.$module.' не найдено ни одного адреса для прогона.'.PHP_EOL;
                exit(1);
            }
        }

        if ($json)
        {
            $result = $smoke->run($urls);

            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;

            exit($result['stats']['bad'] ? 1 : 0);
        }

        echo 'PromERP · дымовой прогон: '.count($urls).' маршрутов'.PHP_EOL;
        echo 'Пользователь: '.($as_user ?: 'анонимный доступ (нет администратора в БД)').PHP_EOL.PHP_EOL;

        $result = $smoke->run($urls, function ($row) use ($show_all) {
            if ($row['error'] === NULL AND ! $show_all)
            {
                return;
            }

            printf('  %-6s %-42s %s%s',
                $row['error'] === NULL ? 'ok' : 'FAIL',
                $row['url'],
                $row['error'] === NULL
                    ? $row['status'].( ! empty($row['empty']) ? ' (пустой ответ)' : '').' ('.$row['time'].' c)'
                    : $row['error'],
                PHP_EOL);
        });

        echo PHP_EOL.sprintf('Итого: %d маршрутов, проблем %d, время %.3f с%s',
            $result['stats']['total'], $result['stats']['bad'], $result['stats']['time'], PHP_EOL);

        if ($result['stats']['bad'])
        {
            echo PHP_EOL.'Подробности по каждой проблеме — в application/logs/.'.PHP_EOL;
            exit(1);
        }
    }
}
