<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Готовность развёртывания: секреты и их источник.
 *
 * Секреты приложения (соль cookie и ключ хеширования паролей) приходили только
 * из окружения процесса. На новом сервере это давало ошибку, которую сложно
 * связать с причиной: `A valid cookie salt is required. Please set
 * Cookie::$salt in your bootstrap.php` — сообщение ядра Kohana, которое:
 *
 *  * не говорит, что делать (`.env`? SetEnv? php-fpm?);
 *  * не говорит, где искать (какой файл конфигурации читается);
 *  * появляется раньше любых страниц, поэтому «сайт не работает целиком».
 *
 * Здесь живёт одна проверка и одно объяснение, общие для трёх мест: bootstrap
 * (падение с понятным текстом), `dev:doctor` (отчёт о развёртывании) и тесты.
 *
 * @package    Promerp
 * @category   Deployment
 */
class Deploy
{
    /**
     * Секреты, без которых система либо падает, либо становится небезопасной.
     *
     * @return  array
     */
    public static function secrets()
    {
        return array(
            'COOKIE_SALT' => array(
                'title'  => 'соль cookie',
                'min'    => 16,
                'why'    => 'подпись cookie и сессионных данных',
                'config' => 'application/config/cookie.php',
            ),
            'AUTH_HASH_KEY' => array(
                'title'  => 'ключ хеширования паролей',
                'min'    => 16,
                'why'    => 'проверка паролей пользователей',
                'config' => 'application/config/auth.php',
            ),
        );
    }

    /**
     * Как задать секреты — один текст для ошибки и для подсказок.
     *
     * @return  string
     */
    public static function secrets_hint()
    {
        return implode(PHP_EOL, array(
            'Секреты задаются одним из способов (любой подойдёт):',
            '',
            '  1) файл .env в корне проекта — приложение читает его само:',
            '         bash bin/setup.sh          # создаст .env и сгенерирует секреты',
            '',
            '  2) окружение процесса (приоритетнее файла):',
            '         nginx + php-fpm:  fastcgi_param COOKIE_SALT "...";',
            '         пул php-fpm:      env[COOKIE_SALT] = "..."',
            '         Apache + mod_php: SetEnv COOKIE_SALT "..."',
            '         Docker/CI:        environment: COOKIE_SALT: "..."',
            '',
            'Почему `SetEnv` в .htaccess ненадёжен: это переменная запроса Apache, под php-fpm',
            '(clear_env) она до скрипта не доходит, а сам .htaccess в git не хранится.',
            'Настройки держите в .env либо в конфиге сервера.',
            '',
            'Проверить, что видит приложение: bin/console dev:doctor',
            'Подробнее: docs/DEPLOY.md',
        ));
    }

    /**
     * Соль для cookie. Пустое значение — это не «почти настроено», а падение
     * ядра, поэтому проверяем сами и объясняем, что делать.
     *
     * @param   mixed   $salt
     * @return  string
     * @throws  Kohana_Exception
     */
    public static function require_cookie_salt($salt)
    {
        $salt = is_string($salt) ? trim($salt) : '';

        if (strlen($salt) >= 16)
        {
            return $salt;
        }

        $where = self::env_source_text();

        throw new Kohana_Exception(
            'Не задана соль cookie (COOKIE_SALT): подписывать cookie нечем, поэтому приложение остановлено.'.PHP_EOL
            .'Значение читается из application/config/cookie.php → getenv(\'COOKIE_SALT\').'.PHP_EOL
            .$where.PHP_EOL
            .self::secrets_hint()
        );
    }

    /**
     * Что нашлось в окружении и в .env — короткая справка для сообщения об ошибке.
     *
     * @return  string
     */
    public static function env_source_text()
    {
        $report = function_exists('promerp_env_report') ? promerp_env_report() : NULL;

        if ($report === NULL)
        {
            $line = 'Файл .env в этом запросе не читался (загрузчик application/env.php не подключён).';
        }
        elseif (empty($report['exists']))
        {
            $line = 'Файла '.$report['file'].' нет.';
        }
        else
        {
            $line = 'Файл '.$report['file'].' прочитан: ключей из файла — '.count((array) $report['loaded'])
                .', значений окружения оставлено — '.count((array) $report['kept']).'.';
        }

        // Список секретов — главное, что нужно из этого сообщения.
        $secrets = array();

        foreach (self::secrets() as $name => $secret)
        {
            $value   = getenv($name);
            $secrets[] = $name.': '.((($value === FALSE) OR trim((string) $value) === '') ? 'не задан' : 'задан ('.strlen(trim((string) $value)).' символов)');
        }

        return $line.' Секреты: '.implode(', ', $secrets).'.';
    }

    /**
     * Проверка секретов для `dev:doctor`.
     *
     * @return  array   ['ok' => [ключ => сообщение], 'problems' => [строка]]
     */
    public static function check_secrets()
    {
        $ok       = array();
        $problems = array();

        foreach (self::secrets() as $name => $secret)
        {
            $value = getenv($name);

            if ($value === FALSE OR strlen(trim($value)) < $secret['min'])
            {
                $problems[] = 'секрет '.$name.' ('.$secret['title'].') не задан или короче '.$secret['min'].' символов — '
                    .'нужен для '.$secret['why'].', читается в '.$secret['config'];
                $ok[$name]  = FALSE;

                continue;
            }

            $ok[$name] = TRUE;
        }

        return array('ok' => $ok, 'problems' => $problems);
    }

    /**
     * Откуда пришли настройки: файл .env или окружение процесса. Нужно, чтобы
     * на боевом хосте было видно, чем именно он настроен.
     *
     * @return  array
     */
    public static function env_source()
    {
        $report = function_exists('promerp_env_report') ? promerp_env_report() : NULL;

        // Скобки обязательны: у `AND` приоритет ниже, чем у тернарного оператора,
        // и без них выражение возвращает TRUE вместо массива (проверено doctor).
        return array(
            'file'   => ($report !== NULL && ! empty($report['exists'])) ? $report['file'] : NULL,
            'loaded' => ($report !== NULL && ! empty($report['loaded'])) ? (array) $report['loaded'] : array(),
            'kept'   => ($report !== NULL && ! empty($report['kept'])) ? (array) $report['kept'] : array(),
        );
    }
}
