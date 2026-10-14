<?php
/**
 * Простой раннер тестов без PHPUnit.
 *
 *   php tests/run.php                  # все тесты
 *   php tests/run.php --filter=Smoke   # только подходящие по имени класса/теста
 *
 * Находит классы *Test в tests/Unit и выполняет методы test*.
 * Если установлен PHPUnit — используйте его:  make test
 */

require __DIR__.'/bootstrap.php';

if ( ! class_exists('PHPUnit\\Framework\\TestCase'))
{
    require __DIR__.'/stubs.php';
}

// Фильтр по имени теста/класса: php tests/run.php --filter=Smoke
$filter = NULL;

foreach ($argv as $arg)
{
    if (strpos($arg, '--filter=') === 0)
    {
        $filter = substr($arg, 9);
    }
}

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/Unit'));
foreach ($it as $file)
{
    if ($file->isFile() AND substr($file->getFilename(), -8) === 'Test.php')
    {
        $files[] = $file->getPathname();
    }
}
sort($files);

/**
 * Ожидаемое исключение теста (если тест его объявил).
 *
 * @param   object  $test
 * @return  array
 */
function expectation_of($test)
{
    if (method_exists($test, 'expectedException'))
    {
        return $test->expectedException();
    }

    return ['class' => NULL, 'message' => NULL, 'pattern' => NULL];
}

$passed = 0;
$failed = 0;
$skipped = 0;
$matched = 0;
$failures = [];

foreach ($files as $file)
{
    require_once $file;

    $class = 'Tests\\Unit\\'.str_replace(['/', '.php'], ['\\', ''], substr($file, strlen(__DIR__.'/Unit/')));

    if ( ! class_exists($class))
    {
        // Пробуем без неймспейса
        $class = basename($file, '.php');
    }

    if ( ! class_exists($class))
    {
        echo "  ! не найден класс для $file".PHP_EOL;
        continue;
    }

    $reflection = new ReflectionClass($class);

    // Сначала отбираем методы: заголовок файла печатаем только тогда, когда
    // под фильтр попал хотя бы один тест — иначе отчёт выглядит как «0 тестов».
    $methods = [];

    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
    {
        if (strpos($method->getName(), 'test') !== 0)
        {
            continue;
        }

        if ($filter !== NULL AND stripos($method->getName(), $filter) === FALSE
            AND stripos($class, $filter) === FALSE)
        {
            continue;
        }

        $methods[] = $method;
    }

    if ( ! $methods)
    {
        continue;
    }

    echo PHP_EOL.basename($file).PHP_EOL;

    foreach ($methods as $method)
    {
        $matched++;

        $test = $reflection->newInstance();

        // Ожидания исключения сбрасываются для каждого теста заново
        $expects = ['class' => NULL, 'message' => NULL, 'pattern' => NULL];

        // Уборка после теста: зовётся и на успехе, и на падении. Как в PHPUnit:
        // иначе упавший тест оставляет свои данные в базе, и следующий прогон
        // спотыкается о них (уникальные номера, остатки на «тестовом» складе).
        $cleanup = function () use ($reflection, $test) {
            try
            {
                $reflection->getMethod('tearDown')->invoke($test);
            }
            catch (Throwable $teardown)
            {
                // Уборка не должна подменять исходную ошибку теста
            }
        };

        try
        {
            $reflection->getMethod('setUp')->invoke($test);
            $method->invoke($test);

            // Тело теста могло объявить ожидаемое исключение — читаем после вызова
            $expects = expectation_of($test);

            if ($expects['class'] !== NULL)
            {
                throw new AssertionFailedError(
                    'Ожидалось исключение '.$expects['class'].', но тест завершился без ошибок'
                );
            }

            $reflection->getMethod('tearDown')->invoke($test);

            echo '    [ok]   '.$method->getName().PHP_EOL;
            $passed++;
        }
        catch (Throwable $e)
        {
            $expects = expectation_of($test);

            if (strpos(get_class($e), 'Skipped') !== FALSE)
            {
                echo '    [skip] '.$method->getName().': '.$e->getMessage().PHP_EOL;
                $skipped++;
                continue;
            }

            if ($expects['class'] !== NULL)
            {
                if ( ! is_a($e, $expects['class']))
                {
                    echo '    [fail] '.$method->getName().PHP_EOL;
                    echo '           Ожидалось '.$expects['class'].', получено '.get_class($e).': '.$e->getMessage().PHP_EOL;
                    $cleanup();
                    $failed++;
                    $failures[] = $class.'::'.$method->getName();
                    continue;
                }

                if ($expects['message'] !== NULL AND strpos($e->getMessage(), $expects['message']) === FALSE)
                {
                    echo '    [fail] '.$method->getName().PHP_EOL;
                    echo '           Сообщение "'.$e->getMessage().'" не содержит "'.$expects['message'].'"'.PHP_EOL;
                    $cleanup();
                    $failed++;
                    $failures[] = $class.'::'.$method->getName();
                    continue;
                }

                if ($expects['pattern'] !== NULL AND ! preg_match($expects['pattern'], $e->getMessage()))
                {
                    echo '    [fail] '.$method->getName().PHP_EOL;
                    echo '           Сообщение "'.$e->getMessage().'" не соответствует '.$expects['pattern'].PHP_EOL;
                    $cleanup();
                    $failed++;
                    $failures[] = $class.'::'.$method->getName();
                    continue;
                }

                $cleanup();

                echo '    [ok]   '.$method->getName().PHP_EOL;
                $passed++;
                continue;
            }

            echo '    [fail] '.$method->getName().PHP_EOL;
            echo '           '.$e->getMessage().PHP_EOL;
            $cleanup();
            $failed++;
            $failures[] = $class.'::'.$method->getName();
        }
    }
}

echo PHP_EOL;
echo sprintf('Итого: пройдено %d, провалено %d, пропущено %d'.PHP_EOL, $passed, $failed, $skipped);

// Фильтр, который ни с чем не совпал, — это опечатка или устаревшее имя.
// Молчаливый «0 тестов» выглядел как успешная проверка и прятал ошибку.
if ($filter !== NULL AND $matched === 0)
{
    echo 'Фильтр --filter='.$filter.' не совпал ни с одним тестом (имя класса или метода).'.PHP_EOL;
    exit(2);
}

if ($failures)
{
    echo 'Упавшие тесты:'.PHP_EOL.'  - '.implode(PHP_EOL.'  - ', $failures).PHP_EOL;
}

exit($failed > 0 ? 1 : 0);
