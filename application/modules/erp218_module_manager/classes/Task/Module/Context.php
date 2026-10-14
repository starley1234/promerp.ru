<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контекст одного модуля — то, что нужно агенту перед правкой, без чтения
 * всего репозитория.
 *
 *   bin/console module:context --module=erp401_wms
 *   bin/console module:context --module=401            # по ID
 *   bin/console module:context --module=erp401 --json  # для машинной обработки
 *   bin/console module:context --list                  # какие модули есть
 *
 * Карточка отвечает на вопросы: где контракт модуля, кто владелец таблиц,
 * какие роуты/права/события/MCP/тесты, от кого зависит и кто зависит от него,
 * какие файлы читать и какие проверки запускать.
 *
 * @package Minion
 */
class Task_Module_Context extends Minion_Task {

    protected $_options = [
        'module'   => NULL,
        'json'     => FALSE,
        'list'     => FALSE,
        'no-files' => FALSE,
    ];

    protected function _execute(?array $options = NULL)
    {
        $options = (array) $options;

        $json = $this->_flag($options, 'json');
        $list = $this->_flag($options, 'list');

        if ($list OR ! $this->_value($options, 'module'))
        {
            $this->_modules($json);

            return;
        }

        $module = (string) $this->_value($options, 'module');

        try
        {
            $card = Service_Erp218_Context::instance()->build($module);
        }
        catch (Kohana_Exception $e)
        {
            echo $e->getMessage().PHP_EOL;
            exit(1);
        }

        if ($json)
        {
            echo json_encode($card, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;

            return;
        }

        echo Service_Erp218_Context::instance()->render($card, ! $this->_flag($options, 'no-files'));
    }

    /**
     * Список модулей и короткая подсказка, если --module не указан.
     *
     * @param   boolean $json
     * @return  void
     */
    protected function _modules($json)
    {
        $rows = Service_Erp218_Inventory::instance()->modules();

        if ($json)
        {
            echo json_encode(array_map(function ($row) {
                return [
                    'module'  => $row['name'],
                    'id'      => $row['id'],
                    'enabled' => (bool) $row['enabled'],
                    'path'    => str_replace(dirname(APPPATH).'/', '', $row['path']),
                    'lines'   => $row['lines'],
                ];
            }, $rows), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;

            return;
        }

        echo 'Контекст модуля: bin/console module:context --module=<имя|ID>'.PHP_EOL.PHP_EOL;

        foreach ($rows as $row)
        {
            printf("  %-28s %-6s %s\n",
                $row['name'],
                $row['id'] === NULL ? '—' : $row['id'],
                $row['core'] ? 'ядро' : ($row['enabled'] ? 'включён' : 'выключен'));
        }
    }
}
