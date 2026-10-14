<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Генерация каркаса нового модуля.
 *
 *   bin/console module:scaffold --id=402 --slug=orders --name="Заказы"
 *   bin/console module:scaffold --id=402 --slug=orders --name="Заказы" --force
 *
 * @package Minion
 */
class Task_Module_Scaffold extends Minion_Task {

    protected $_options = [
        'id'    => NULL,
        'slug'  => NULL,
        'name'  => NULL,
        'force' => FALSE,
    ];

    protected function _execute(?array $options = NULL)
    {
        $id   = Arr::get($options, 'id');
        $slug = Arr::get($options, 'slug');
        $name = Arr::get($options, 'name', $slug);

        if ( ! $id OR ! $slug)
        {
            echo 'Использование: bin/console module:scaffold --id=402 --slug=orders --name="Заказы"'.PHP_EOL;

            return;
        }

        $force = array_key_exists('force', (array) $options)
            AND ! in_array(strtolower((string) $options['force']), ['0', 'false', 'no'], TRUE);

        try
        {
            $result = Service_Erp218_Scaffolder::instance()->generate([
                'id'    => $id,
                'slug'  => $slug,
                'name'  => $name,
                'force' => $force,
            ]);
        }
        catch (Exception $e)
        {
            echo '  ✘ '.$e->getMessage().PHP_EOL;
            exit(1);
        }

        echo 'Создан модуль '.$result['module'].PHP_EOL.PHP_EOL;

        foreach ($result['created'] as $file)
        {
            echo '  + '.$file.PHP_EOL;
        }

        echo PHP_EOL.'Дальше:'.PHP_EOL;
        echo '  1. подключите модуль в application/bootstrap.php:'.PHP_EOL;
        echo "       '".$result['module']."' => APPPATH.'modules/".$result['module']."',".PHP_EOL;
        echo '  2. накатите миграции: bin/console db:migrate --module='.$result['module'].PHP_EOL;
        echo '  3. проверьте синтаксис: bin/console dev:lint'.PHP_EOL;
        echo '  4. опишите модуль в README.md (назначение, таблицы, события, права)'.PHP_EOL;
    }
}
