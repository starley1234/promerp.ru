<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Режим интерфейса: простой или профессиональный.
 *
 * Один продукт, один набор данных и сервисов, два способа показать их человеку
 * (см. docs/SIMPLE_ADVANCED_MODES_AND_AGENT_TESTING.md):
 *
 *  - простой (по умолчанию) — те же данные «крупными клетками»: списки плитками,
 *    фильтры и второстепенные поля свёрнуты, меню без служебных разделов;
 *  - профессиональный — прежний плотный вид: таблицы, все поля, все разделы.
 *
 * Правило проекта: представление модуля НЕ спрашивает режим. Оно отдаёт данные
 * компонентам (`table`, `kpi`, `filters`, `more`), а те сами выбирают разметку.
 * Звать `Ui_Mode::` можно только из компонентов, шаблона и классов — это
 * проверяет `bin/frontend-audit.py`. Для мелочей без PHP есть CSS-классы
 * `pro-only` и `simple-only` (assets/css/simple.css).
 *
 * Режим — удобство, а не защита: скрытое в простом режиме открывается по
 * прямому адресу, права по-прежнему решает ACL.
 *
 * Где хранится. Выбор человека лежит в cookie `ui_mode` («режим.id_пользователя»;
 * чужой id делает cookie недействительной, и тогда на общем компьютере второй
 * человек не получает режим первого) и, для вошедшего пользователя, в
 * `users_settings.settings_json` — оттуда режим приезжает на другое устройство.
 * В демо-режиме база не пишется: демо-пользователи общие. В консоли и тестах
 * человека за браузером нет, поэтому там остаётся прежний (профессиональный)
 * вид, пока тест не выберет режим через `force()`.
 *
 * @package    PromERP
 * @category   UI
 */
class Ui_Mode
{
    /** Простой режим (по умолчанию для всех). */
    const SIMPLE = 'simple';

    /** Профессиональный режим. */
    const PRO = 'pro';

    /** Режим, который видит человек, ещё ничего не выбравший. */
    const DEFAULT_MODE = 'simple';

    /** Имя cookie и ключа настройки пользователя. */
    const COOKIE = 'ui_mode';

    /** Сколько помнить выбор в браузере: год, в секундах. */
    const LIFETIME = 31536000;

    /**
     * @var string|NULL  Режим текущего запроса (считается один раз).
     */
    protected static $_mode;

    /**
     * Режимы: код → название для переключателя.
     *
     * @return  array
     */
    public static function modes()
    {
        return array(
            self::SIMPLE => 'Простой',
            self::PRO    => 'Профессиональный',
        );
    }

    /**
     * Подсказки к режимам: что человек получит, нажав кнопку.
     *
     * @return  array
     */
    public static function hints()
    {
        return array(
            self::SIMPLE => 'Крупные карточки и только главное',
            self::PRO    => 'Таблицы, все поля и все разделы',
        );
    }

    /**
     * Известен ли такой режим.
     *
     * @param   mixed   $mode
     * @return  boolean
     */
    public static function valid($mode)
    {
        return is_string($mode) AND array_key_exists($mode, self::modes());
    }

    /**
     * Режим текущего запроса.
     *
     * @return  string  simple|pro
     */
    public static function current()
    {
        if (self::$_mode === NULL)
        {
            self::$_mode = self::resolve();
        }

        return self::$_mode;
    }

    /**
     * @return  boolean
     */
    public static function simple()
    {
        return self::current() === self::SIMPLE;
    }

    /**
     * @return  boolean
     */
    public static function pro()
    {
        return self::current() === self::PRO;
    }

    /**
     * Выбрать режим для текущего запроса, не сохраняя его (тесты).
     * NULL — забыть выбор и считать заново.
     *
     * @param   string|NULL  $mode
     * @return  void
     */
    public static function force($mode)
    {
        self::$_mode = self::valid($mode) ? $mode : NULL;
    }

    /**
     * Сохранить выбор человека: в браузере и, если он вошёл, в его настройках.
     *
     * @param   string  $mode
     * @return  string
     * @throws  InvalidArgumentException
     */
    public static function set($mode)
    {
        if ( ! self::valid($mode))
        {
            throw new InvalidArgumentException('Неизвестный режим интерфейса');
        }

        $user_id     = self::user_id();
        self::$_mode = $mode;

        self::remember($mode, $user_id);

        if ($user_id > 0)
        {
            try
            {
                Service_Erp100_UserSettings::instance()->set($user_id, self::COOKIE, $mode);
            }
            catch (Exception $e)
            {
                // Настройка — удобство: в браузере выбор уже сохранён.
                Kohana::$log->add(Log::WARNING, 'Ui_Mode: режим не записан в настройки — :error', array(
                    ':error' => $e->getMessage(),
                ));
            }
        }

        return $mode;
    }

    /**
     * Определить режим: cookie этого человека → его настройки → по умолчанию.
     *
     * @return  string
     */
    protected static function resolve()
    {
        if (PHP_SAPI === 'cli')
        {
            return self::PRO;
        }

        $user_id = self::user_id();
        $cookie  = (string) Cookie::get(self::COOKIE);

        if (preg_match('/^(simple|pro)\.(\d+)$/', $cookie, $match) AND (int) $match[2] === $user_id)
        {
            return $match[1];
        }

        $mode = self::DEFAULT_MODE;

        if ($user_id > 0)
        {
            try
            {
                $stored = Service_Erp100_UserSettings::instance()->get($user_id, self::COOKIE);

                if (self::valid($stored))
                {
                    $mode = $stored;
                }
            }
            catch (Exception $e)
            {
                Kohana::$log->add(Log::WARNING, 'Ui_Mode: настройки не прочитаны — :error', array(
                    ':error' => $e->getMessage(),
                ));
            }
        }

        // Запоминаем и умолчание: иначе настройки читались бы при каждом запросе.
        self::remember($mode, $user_id);

        return $mode;
    }

    /**
     * Записать выбор в cookie браузера.
     *
     * @param   string   $mode
     * @param   integer  $user_id
     * @return  void
     */
    protected static function remember($mode, $user_id)
    {
        if (PHP_SAPI !== 'cli' AND ! headers_sent())
        {
            Cookie::set(self::COOKIE, $mode.'.'.(int) $user_id, self::LIFETIME);
        }
    }

    /**
     * Кто смотрит: id вошедшего пользователя либо 0 (гость и демо-режим).
     *
     * @return  integer
     */
    protected static function user_id()
    {
        try
        {
            if (class_exists('Service_Erp811_Demo') AND Service_Erp811_Demo::instance()->active())
            {
                return 0;
            }

            $auth = Auth::instance();

            return $auth->logged_in('login') ? (int) $auth->get_user()->id : 0;
        }
        catch (Exception $e)
        {
            return 0;
        }
    }
}
