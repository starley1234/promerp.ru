<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Статика интерфейса: пути, версии и проверка сборки.
 *
 * Раньше шаблон тянул Tailwind, Font Awesome и jQuery с чужих доменов
 * (`cdn.tailwindcss.com`, `cdnjs.cloudflare.com`, `code.jquery.com`). Это
 * значило: нет интернета — нет интерфейса (классы Tailwind генерировал сам CDN
 * в браузере), версия библиотеки менялась без нашего коммита, а строгую
 * политику безопасности (CSP) включить было нельзя.
 *
 * Теперь файлы лежат в `public_html/assets` и собираются командой
 * `bin/build-assets` (`make assets`), а этот класс:
 *
 *  * отдаёт адрес файла с меткой версии (кэш браузера не покажет старый CSS
 *    после пересборки);
 *  * знает, что именно должно быть собрано (`manifest()`), и умеет сказать,
 *    чего не хватает (`missing()`) — этим пользуются проверка
 *    `bin/console dev:audit --check=assets` и тест `tests/Unit/AssetsTest.php`.
 *
 * Если файла нет, адрес всё равно возвращается: страница откроется (и это
 * заметно сразу), а ошибку поймает аудит. Молча подсовывать CDN нельзя — тогда
 * офлайн-стенд снова окажется без стилей, и никто не узнает почему.
 *
 * @package    PromERP
 * @category   Assets
 */
class Assets
{
    /**
     * Что должно быть собрано: ключ → файл относительно public_html.
     *
     * `required` — без этого файла интерфейс нечитаем (ошибка аудита),
     * иначе — предупреждение.
     */
    protected static $_manifest = array(
        'css' => array(
            'file'     => 'assets/css/app.css',
            'required' => TRUE,
            'title'    => 'Tailwind CSS и свои стили (сборка bin/build-assets)',
        ),
        'simple' => array(
            'file'     => 'assets/css/simple.css',
            'required' => FALSE,
            'title'    => 'Оформление простого режима интерфейса (Ui_Mode), без сборки',
        ),
        'fontawesome' => array(
            'file'     => 'assets/vendor/fontawesome/css/all.min.css',
            'required' => TRUE,
            'title'    => 'Font Awesome 6 (локальная копия)',
        ),
        'jquery' => array(
            'file'     => 'assets/vendor/jquery-3.7.1.min.js',
            'required' => TRUE,
            'title'    => 'jQuery 3.7 (локальная копия)',
        ),
        // Свои скрипты шаблона: с меткой версии, иначе при долгом кэше статики
        // (nginx: 30 дней) браузер продолжит работать со старым app.js после выкладки.
        'theme_init' => array(
            'file'     => 'assets/js/theme-init.js',
            'required' => TRUE,
            'title'    => 'Тема оформления до отрисовки страницы (без мерцания)',
        ),
        'template_js' => array(
            'file'     => 'assets/js/template.js',
            'required' => TRUE,
            'title'    => 'Скрипт шаблона страницы',
        ),
        'app_js' => array(
            'file'     => 'assets/js/app.js',
            'required' => TRUE,
            'title'    => 'Поведение интерфейса: меню, поиск, уведомления',
        ),
        'smart_reference_js' => array(
            'file'     => 'assets/js/smart-reference.js',
            'required' => FALSE,
            'title'    => 'Умный выбор справочных значений',
        ),
    );

    /**
     * Каталоги, которые должны существовать, но не имеют версии файла.
     */
    protected static $_directories = array(
        'webfonts' => array(
            'path'     => 'assets/vendor/fontawesome/webfonts',
            'required' => TRUE,
            'title'    => 'Шрифты Font Awesome (woff2)',
        ),
    );

    /**
     * Кэш версий на время запроса: sha1 файла читается один раз.
     *
     * Пути внутри манифеста читаются напрямую, а не через `Arr::get('a.b')`:
     * в этой сборке фреймворка точки обрабатывает только `Arr::path()`, и
     * `Arr::get()` вернул бы пустую строку — сборка «пропала» бы целиком.
     */
    protected static $_versions = array();

    /**
     * Адрес файла статики с меткой версии.
     *
     * @param   string  $key  ключ манифеста (css, jquery, fontawesome)
     * @return  string
     */
    public static function url($key)
    {
        if ( ! isset(self::$_manifest[$key]))
        {
            throw new Kohana_Exception('Статика «:key» не объявлена в Assets::$_manifest', array(':key' => $key));
        }

        $relative = self::$_manifest[$key]['file'];
        $path     = DOCROOT.$relative;
        $url      = URL::site($relative);

        if ( ! is_file($path))
        {
            return $url;
        }

        if ( ! isset(self::$_versions[$key]))
        {
            // Время и размер файла, а не sha1 его содержимого: метка нужна на каждой
            // странице, а читать и хешировать 110 КБ CSS при каждом запросе незачем.
            self::$_versions[$key] = substr(sha1(filemtime($path).':'.filesize($path)), 0, 8);
        }

        return $url.'?v='.self::$_versions[$key];
    }

    /**
     * Полный путь к собранному файлу.
     *
     * @param   string  $key  ключ манифеста
     * @return  string
     */
    public static function path($key)
    {
        if ( ! isset(self::$_manifest[$key]['file']))
        {
            return '';
        }

        return DOCROOT.self::$_manifest[$key]['file'];
    }

    /**
     * Файл собран и не пуст.
     *
     * @param   string  $key  ключ манифеста
     * @return  bool
     */
    public static function exists($key)
    {
        $path = self::path($key);

        return ($path !== '' AND is_file($path) AND filesize($path) > 0);
    }

    /**
     * Чего не хватает в собранной статике.
     *
     * @return  array   список: ключ, путь, критичность, подпись
     */
    public static function missing()
    {
        $missing = array();

        foreach (self::$_manifest as $key => $asset)
        {
            if (self::exists($key))
            {
                continue;
            }

            $missing[] = array(
                'key'      => $key,
                'path'     => 'public_html/'.$asset['file'],
                'required' => (bool) $asset['required'],
                'title'    => $asset['title'],
            );
        }

        foreach (self::$_directories as $key => $directory)
        {
            $path = DOCROOT.$directory['path'];

            if (is_dir($path) AND count(glob($path.'/*.woff2')) > 0)
            {
                continue;
            }

            $missing[] = array(
                'key'      => $key,
                'path'     => 'public_html/'.$directory['path'],
                'required' => (bool) $directory['required'],
                'title'    => $directory['title'],
            );
        }

        return $missing;
    }

    /**
     * Что собрано: ключ → путь, размер, версия. Нужно проверкам и отчётам.
     *
     * @return  array
     */
    public static function manifest()
    {
        $manifest = array();

        foreach (self::$_manifest as $key => $asset)
        {
            $path = self::path($key);

            $manifest[$key] = array(
                'file'     => 'public_html/'.$asset['file'],
                'exists'   => self::exists($key),
                'size'     => is_file($path) ? (int) filesize($path) : 0,
                'version'  => is_file($path) ? substr(sha1_file($path), 0, 8) : NULL,
                'required' => (bool) $asset['required'],
                'title'    => $asset['title'],
            );
        }

        foreach (self::$_directories as $key => $directory)
        {
            $path = DOCROOT.$directory['path'];

            $files = is_dir($path) ? glob($path.'/*') : array();
            $size  = 0;

            foreach ($files as $file)
            {
                $size += (int) filesize($file);
            }

            $manifest[$key] = array(
                'file'     => 'public_html/'.$directory['path'],
                'exists'   => (bool) $files,
                'size'     => $size,
                'version'  => NULL,
                'required' => (bool) $directory['required'],
                'title'    => $directory['title'],
                'files'    => count($files),
            );
        }

        return $manifest;
    }

    /**
     * Общий вес собранной статики: сколько страница тянет без сжатия и
     * сколько — со сжатием (br/gzip примерно втрое меньше).
     *
     * @return  array
     */
    public static function weight()
    {
        $total = 0;
        $files = 0;

        foreach (self::$_manifest as $key => $asset)
        {
            $path = self::path($key);

            if ( ! is_file($path))
            {
                continue;
            }

            $total += (int) filesize($path);
            $files++;
        }

        $webfonts = isset(self::$_directories['webfonts']['path']) ? self::$_directories['webfonts']['path'] : '';

        if ($webfonts !== '' AND is_dir(DOCROOT.$webfonts))
        {
            foreach (glob(DOCROOT.$webfonts.'/*.woff2') as $font)
            {
                $total += (int) filesize($font);
                $files++;
            }
        }

        return array(
            'files' => $files,
            'bytes' => $total,
        );
    }
}
