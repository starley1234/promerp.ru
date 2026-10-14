<?php

/**
 * Переменные окружения из файла `.env` — читаются до запуска фреймворка.
 *
 * Зачем отдельный файл, а не функция внутри bootstrap. Решение «какие у нас
 * настройки» принимается раньше всего остального: до `Kohana::init()`, до
 * подключения классов и до первого обращения к базе. Поэтому загрузчик — это
 * обычные функции в отдельном файле, который точка входа подключает напрямую:
 *
 *     require $root.'application/env.php';
 *     promerp_env_load($root);
 *
 * Почему приложение читает `.env` само, а не полагается на сервер. Пока
 * переменные приходили только из окружения процесса, развёртывание зависело от
 * того, как настроен веб-сервер:
 *
 *  * Apache + `mod_php` — работает `SetEnv` в `.htaccess` или `<VirtualHost>`;
 *  * Apache + `php-fpm` — `SetEnv` в `.htaccess` до PHP **не доходит** (это
 *    переменные запроса Apache, а не процесса FPM): нужен `ProxyFCGISetEnvIf`
 *    или `env[...]` в пуле;
 *  * nginx + `php-fpm` — нужны `fastcgi_param` или `env[...]` в пуле;
 *  * Docker/CI — переменные обычно есть, но `.htaccess` в образ не попадает.
 *
 * Итог: на новом сервере приложение падало на «A valid cookie salt is required»
 * не из-за ошибки в коде, а из-за способа доставки настроек. Файл `.env` убирает
 * эту зависимость: он одинаково работает в CLI, под mod_php, под php-fpm и под
 * nginx. Настройки сервера при этом остаются в силе — они приоритетнее файла
 * (см. ниже), поэтому боевой хост может задать секреты своим способом.
 *
 * Правила загрузки:
 *
 *  1. значения из окружения процесса (SetEnv, fastcgi_param, env[...], docker,
 *     переменные оболочки) **не перезаписываются** файлом — окружение главнее;
 *  2. пустое значение в окружении считается незаданным: `SetEnv COOKIE_SALT ""`
 *     не мешает взять соль из `.env`;
 *  3. строка `KEY=VALUE`, комментарии `#` и `;`, `export KEY=VALUE`, кавычки
 *     одинарные и двойные (в двойных раскрываются `\n`, `\t`, `\"`, `\\`),
 *     ` #` после значения обрезается как комментарий;
 *  4. отсутствие файла — не ошибка: значит, окружение настроено другими
 *     средствами (`dev:doctor` покажет, откуда пришли значения);
 *  5. если переменные выставила CLI-обёртка (`bin/env.sh`, она экспортирует их
 *     из того же файла), загрузчик понимает это по маркеру
 *     `PROMERP_ENV_FILE_VARS` и считает значения файловыми, а не серверными —
 *     иначе `dev:doctor` показывал бы «взято из окружения» для всего, что
 *     положила обёртка, и по отчёту нельзя было бы понять, чем настроен хост.
 *
 * @package    Promerp
 */

if ( ! function_exists('promerp_env_load'))
{
    /**
     * Разобрать файл `.env` в пары ключ => значение.
     *
     * @param   string  $path
     * @return  array
     */
    function promerp_env_parse($path)
    {
        if ( ! is_file($path) OR ! is_readable($path))
        {
            return array();
        }

        $values = array();
        $lines  = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === FALSE)
        {
            return array();
        }

        foreach ($lines as $line)
        {
            $line = trim($line);

            // Пустые строки и комментарии
            if ($line === '' OR $line[0] === '#' OR $line[0] === ';')
            {
                continue;
            }

            // export KEY=VALUE — так пишут в шеллах, читаем и так
            if (strpos($line, 'export ') === 0)
            {
                $line = trim(substr($line, 7));
            }

            if (strpos($line, '=') === FALSE)
            {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);

            $key   = trim($key);
            $value = trim($value);

            if ( ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key))
            {
                continue;
            }

            $values[$key] = promerp_env_value($value);
        }

        return $values;
    }

    /**
     * Значение из строки `.env`: снимаем кавычки, раскрываем escapes, режем комментарий.
     *
     * @param   string  $value
     * @return  string
     */
    function promerp_env_value($value)
    {
        if ($value === '')
        {
            return '';
        }

        $first = $value[0];

        // Значение в кавычках: комментарий внутри уже не комментарий
        if (($first === '"' OR $first === "'"))
        {
            $end = strrpos($value, $first);

            if ($end !== FALSE AND $end > 0)
            {
                $inner = substr($value, 1, $end - 1);

                return $first === '"' ? str_replace(array('\\n', '\\t', '\\"', '\\\\'), array("\n", "\t", '"', '\\'), $inner) : $inner;
            }
        }

        // Без кавычек: « #» — начало комментария (например, KEY=value # пояснение)
        $comment = strpos($value, ' #');

        if ($comment !== FALSE)
        {
            $value = substr($value, 0, $comment);
        }

        return trim($value);
    }

    /**
     * Загрузить `.env` в окружение процесса.
     *
     * @param   string  $root   корень проекта (каталог с application/, public_html/)
     * @param   string  $file   путь к файлу (по умолчанию $root/.env)
     * @return  array           отчёт: file, loaded, kept, missing_file
     */
    function promerp_env_load($root, $file = NULL)
    {
        $file = $file !== NULL ? $file : rtrim($root, '/\\').DIRECTORY_SEPARATOR.'.env';

        $report = array(
            'file'         => $file,
            'exists'       => is_file($file),
            'loaded'       => array(),
            'kept'         => array(),
            'exported'     => array(),
            'missing_file' => ! is_file($file),
        );

        // Маркер CLI-обёртки: имена, которые она экспортировала из этого же
        // файла. Для них «значение в окружении» вовсе не настройка сервера.
        $exported = array();
        $marker   = getenv('PROMERP_ENV_FILE_VARS');

        if ($marker !== FALSE AND trim($marker) !== '')
        {
            foreach (preg_split('/\s+/', trim($marker)) as $name)
            {
                $exported[$name] = TRUE;
            }
        }

        foreach (promerp_env_parse($file) as $key => $value)
        {
            $current     = getenv($key);
            $from_script = isset($exported[$key]);

            if ($from_script)
            {
                $report['exported'][] = $key;
            }

            // Окружение главнее файла: если там уже есть непустое значение —
            // это осознанная настройка сервера, и её не трогаем.
            if ( ! $from_script AND $current !== FALSE AND $current !== '')
            {
                $report['kept'][] = $key;

                continue;
            }

            putenv($key.'='.$value);
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;

            $report['loaded'][] = $key;
        }

        $GLOBALS['promerp_env_report'] = $report;

        return $report;
    }

    /**
     * Отчёт о последней загрузке `.env` (для `dev:doctor`).
     *
     * @return  array|null
     */
    function promerp_env_report()
    {
        return isset($GLOBALS['promerp_env_report']) ? $GLOBALS['promerp_env_report'] : NULL;
    }
}
