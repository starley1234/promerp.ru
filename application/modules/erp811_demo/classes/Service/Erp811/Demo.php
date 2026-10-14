<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Демонстрационный стенд (erp811_demo): состояние, переключение базы, гостевой вход.
 *
 * Стенд нужен, чтобы показать работающую систему там, где своего производства
 * ещё нет: у внедрения длинный путь (найти данные, договориться о пилоте,
 * подождать реальных заказов), а показать ценность надо сейчас. Поэтому демо —
 * это **отдельная база** `promerp_demo`, наполненная сценарием
 * (`Service_Erp811_Scenario` → `Service_Erp811_Seeder`), и режим гостя, в
 * котором приложение целиком переключается на неё.
 *
 * Три решения, которые определяют всю конструкцию:
 *
 * 1. **Отдельная база, а не пометка строк.** Изоляция должна быть физической:
 *    пока гостевой запрос ходит другим подключением, никакая ошибка в правах не
 *    даст ему дотянуться до боевых данных, а «сбросить демо» можно целиком.
 * 2. **Cookie, а не сессия.** Решение о базе принимается в bootstrap, до старта
 *    сессии: иначе первый же запрос успел бы уйти в рабочую базу. Cookie для
 *    этого достаточно — она ставится только после проверки, что демо собрано.
 * 3. **Только чтение.** Демо-режим запрещает любой не-GET запрос до входа в
 *    контроллер: стенд показывает систему, а не принимает правки.
 *
 * @package    Erp811
 * @category   Services
 */
class Service_Erp811_Demo
{
    /** @var Service_Erp811_Demo|null */
    protected static $_instance;

    /** @var bool Включён ли демо-режим в этом процессе */
    protected $_active = FALSE;

    /** @var bool|null Подключается ли демо-база вообще (без проверки знака сборки) */
    protected $_connectable;

    /** @var bool|null Собран ли стенд (подключение + знак сборки) */
    protected $_available;

    /** @var array|null Роли осмотра, уже проверенные по демо-базе */
    protected $_roleplay;

    /** @var array|null Конфигурация основной базы до переключения */
    protected $_original_config;

    /**
     * @return  Service_Erp811_Demo
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    protected function __construct() {}

    // =========================================================================
    //  Настройки
    // =========================================================================

    /**
     * @param   string  $key
     * @param   mixed   $default
     * @return  mixed
     */
    public function config($key, $default = NULL)
    {
        $config = Kohana::$config->load('demo');

        if ($config === NULL)
        {
            return $default;
        }

        $value = $config->get($key, $default);

        return ($value === NULL) ? $default : $value;
    }

    /** @return array Доступные профили объёма синтетики. */
    public function build_profiles()
    {
        return (array) $this->config('build_profiles', array());
    }

    /**
     * Применить профиль на время текущего процесса сборки.
     *
     * @param string|null $name
     * @return array имя, label, history_days, orders
     */
    public function apply_build_profile($name = NULL)
    {
        $profiles = $this->build_profiles();
        $name = $name !== NULL ? (string) $name : (string) $this->config('build_profile', 'quick');

        if ( ! isset($profiles[$name]))
        {
            $name = isset($profiles['quick']) ? 'quick' : key($profiles);
        }

        $profile = isset($profiles[$name]) ? (array) $profiles[$name] : array(
            'label' => 'Пользовательский',
            'history_days' => (int) $this->config('history_days', 30),
            'orders' => (int) $this->config('orders', 8),
        );
        $profile['name'] = $name ?: 'custom';
        $profile['history_days'] = max(30, (int) $profile['history_days']);
        $profile['orders'] = max(8, (int) $profile['orders']);

        $config = Kohana::$config->load('demo');
        $config->set('active_build_profile', $profile['name']);
        $config->set('history_days', $profile['history_days']);
        $config->set('orders', $profile['orders']);

        return $profile;
    }

    /**
     * Включён ли модуль демонстрации.
     *
     * @return  bool
     */
    public function enabled()
    {
        return (bool) $this->config('enabled', TRUE);
    }

    /** @return string `database` (legacy default) or `schema` (same physical DB). */
    public function storage_mode()
    {
        $mode = strtolower((string) $this->config('storage', 'database'));

        return $mode === 'schema' ? 'schema' : 'database';
    }

    /** @return bool */
    public function uses_shared_database()
    {
        if ($this->storage_mode() !== 'schema')
        {
            return FALSE;
        }

        if ( ! (bool) $this->config('shared_database_allowed', FALSE))
        {
            throw new Kohana_Exception(
                'DEMO_STORAGE=schema запрещён в этом окружении. Разрешите shared_database_allowed осознанно или используйте отдельную БД.'
            );
        }

        return TRUE;
    }

    /** @return string Isolated schema used in shared-database mode. */
    public function schema_name()
    {
        $schema = (string) $this->config('schema', 'promerp_demo');

        if ( ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $schema))
        {
            throw new Kohana_Exception('Недопустимое имя схемы демо-стенда: :schema', array(':schema' => $schema));
        }

        return $schema;
    }

    /**
     * Имя физической базы стенда. В schema-режиме это основная база.
     *
     * @return string
     */
    public function database_name()
    {
        if ($this->uses_shared_database())
        {
            return (string) Kohana::$config->load('database.default.connection.database');
        }

        return (string) $this->config('database', 'promerp_demo');
    }

    /** @return string Человекочитаемая цель сборки без паролей. */
    public function storage_label()
    {
        return $this->uses_shared_database()
            ? $this->database_name().' / schema '.$this->schema_name()
            : $this->database_name().' / отдельная база';
    }

    /**
     * Настройки подключения: отдельная база или изолированная схема основной БД.
     *
     * @return  array
     */
    public function database_config()
    {
        $config = (array) Kohana::$config->load('database.default');

        $config['connection']['database'] = $this->database_name();
        $config['schema'] = $this->uses_shared_database() ? $this->schema_name() : 'public';
        // Extension types/operators (ltree, vector) are installed in public.
        // Demo tables are resolved first and are created in the first schema.
        $config['search_path'] = $this->uses_shared_database()
            ? array($this->schema_name(), 'public')
            : array('public');

        return $config;
    }

    // =========================================================================
    //  Режим
    // =========================================================================

    /**
     * Включён ли демо-режим в текущем запросе.
     *
     * @return  bool
     */
    public function active()
    {
        if ($this->_active)
        {
            return TRUE;
        }

        if (PHP_SAPI === 'cli')
        {
            return FALSE;
        }

        $cookie = (string) $this->config('cookie', 'promerp_demo');

        return (isset($_COOKIE[$cookie]) AND (string) $_COOKIE[$cookie] !== '');
    }

    /**
     * Открыть прямое подключение к демо-базе (PDO).
     *
     * Отдельное подключение, а не группа фреймворка: проверки стенда должны
     * работать и до переключения приложения, и после него.
     *
     * @return  PDO|null
     */
    protected function pdo()
    {
        static $pdo = NULL;
        static $failed = FALSE;

        if ($pdo !== NULL OR $failed)
        {
            return $pdo;
        }

        $config = $this->database_config();

        try
        {
            $pdo = new PDO(
                sprintf('pgsql:host=%s;port=%s;dbname=%s',
                    $config['connection']['hostname'],
                    $config['connection']['port'],
                    $config['connection']['database']),
                $config['connection']['username'],
                $config['connection']['password'],
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3)
            );
            $search_path = array_map(function ($schema) {
                return '"'.str_replace('"', '""', $schema).'"';
            }, $config['search_path']);
            $pdo->exec('SET search_path TO '.implode(', ', $search_path));
        }
        catch (Exception $e)
        {
            $pdo    = NULL;
            $failed = TRUE;
        }

        return $pdo;
    }

    /**
     * Есть ли демо-база и можно ли к ней подключиться (схема ещё может быть пустой).
     *
     * @param   bool    $force
     * @return  bool
     */
    public function can_connect($force = FALSE)
    {
        if ($this->_connectable !== NULL AND ! $force)
        {
            return $this->_connectable;
        }

        $this->_connectable = ($this->pdo() !== NULL);

        return $this->_connectable;
    }

    /**
     * Собран ли стенд: база есть и в ней лежит знак сборки.
     *
     * Именно эту проверку видит гость: пока стенд не собран, демо-режим не
     * включается — посетитель попадёт на обычную страницу входа, а не на
     * пустую базу.
     *
     * @param   bool    $force
     * @return  bool
     */
    public function available($force = FALSE)
    {
        if ($this->_available !== NULL AND ! $force)
        {
            return $this->_available;
        }

        $this->_available = FALSE;

        $pdo = $this->pdo();

        if ($pdo === NULL)
        {
            return FALSE;
        }

        try
        {
            $qualified = ($this->uses_shared_database() ? $this->schema_name() : 'public').'.erp_811_demo';
            $table_exists = ($pdo->query("SELECT to_regclass(".$pdo->quote($qualified).")")->fetchColumn() !== NULL);
            $this->_available = $table_exists
                && (bool) $pdo->query('SELECT EXISTS (SELECT 1 FROM '.$qualified.')')->fetchColumn();
        }
        catch (Exception $e)
        {
            $this->_available = FALSE;
        }

        return $this->_available;
    }

    /**
     * Удалить из основной базы аккаунты, которые старый переключатель ORM мог
     * ошибочно записать туда. Ограничение доменом demo.promerp.local не даёт
     * затронуть пользовательские адреса при кастомной конфигурации стенда.
     * Вызывать нужно до use_demo_database().
     *
     * @return int
     */
    public function clean_leaked_demo_users()
    {
        if ( ! $this->uses_shared_database() OR $this->_active)
        {
            return 0;
        }

        $emails = array();
        foreach ((array) $this->config('users', array()) as $user)
        {
            if (isset($user['email']) AND preg_match('/@demo\.promerp\.local$/i', (string) $user['email']))
            {
                $emails[] = strtolower(trim((string) $user['email']));
            }
        }
        $guest = strtolower(trim((string) $this->config('guest_email', 'guest@demo.promerp.local')));
        if (preg_match('/@demo\.promerp\.local$/i', $guest))
        {
            $emails[] = $guest;
        }

        $emails = array_values(array_unique($emails));
        if ( ! $emails)
        {
            return 0;
        }

        return (int) DB::delete('users')
            ->where(DB::expr('LOWER(TRIM(email))'), 'IN', $emails)
            ->execute();
    }

    /**
     * Переключить приложение на демо-базу.
     *
     * Подменяется группа `default`: модули обращаются к ней по имени, поэтому
     * ни один из них не знает, что работает на демо-данных. Соединения кэшируются
     * фреймворком, поэтому кэш сбрасывается — иначе осталось бы подключение к
     * боевой базе.
     *
     * @return  bool
     */
    public function use_demo_database()
    {
        if ($this->_active)
        {
            return TRUE;
        }

        if ( ! $this->enabled() OR ! $this->can_connect())
        {
            return FALSE;
        }

        $group = Kohana::$config->load('database');

        $this->_original_config = (array) $group->get('default');

        $group->set('default', $this->database_config());

        $this->reset_runtime_database_instances();

        $this->_active = TRUE;

        return TRUE;
    }

    /**
     * Сбросить оба уровня request-local кэша соединений.
     *
     * Database::$instances знает о группах, но ORM дополнительно запоминает
     * сам объект Database отдельно для каждого класса модели. Без второго
     * сброса User/Role, впервые открытые контроллером до переключения, пишут в
     * public основной базы, тогда как весь производственный контур уже пишет в
     * promerp_demo. Именно поэтому сборка могла иметь 93 таблицы и 0 users.
     */
    protected function reset_runtime_database_instances()
    {
        Database::$instances = array();
        ORM::reset_database_instances();
    }

    /**
     * Вернуть основную базу: нужно для команд сборки, которые сначала создают
     * базу, а потом в неё пишут.
     *
     * @return  void
     */
    public function use_main_database()
    {
        if ( ! $this->_active)
        {
            return;
        }

        $group = Kohana::$config->load('database');

        $group->set('default', $this->_original_config);

        $this->reset_runtime_database_instances();

        $this->_active = FALSE;
    }

    /**
     * Точка входа для bootstrap: включить демо-режим до первого запроса к базе.
     *
     * @return  bool  включён ли демо-режим
     */
    public function boot()
    {
        if ( ! $this->enabled() OR ! $this->active())
        {
            return FALSE;
        }

        if ( ! $this->use_demo_database())
        {
            // Демо ещё не собрано (или база недоступна): снимаем переключатель,
            // чтобы посетитель не получил вместо страницы ошибку подключения.
            // Сессию тоже закрываем: учётная запись стенда живёт только в его
            // базе, а в рабочей под тем же номером может быть другой человек.
            $this->leave();
            $this->drop_session();

            return FALSE;
        }

        return TRUE;
    }

    /**
     * Войти в демо-режим: поставить cookie. Сессия гостя поднимается отдельно
     * (`login_guest`), потому что для этого уже нужна демо-база.
     *
     * @return  bool
     */
    public function enter()
    {
        if ( ! $this->enabled() OR ! $this->available(TRUE))
        {
            return FALSE;
        }

        // Соединения и кэш — на демо-базу.
        $this->use_demo_database();

        $cookie = (string) $this->config('cookie', 'promerp_demo');

        Cookie::set($cookie, '1', (int) $this->config('cookie_lifetime', 0), '/');

        return TRUE;
    }

    /**
     * Выйти из демо-режима.
     *
     * @return  void
     */
    public function leave()
    {
        $cookie = (string) $this->config('cookie', 'promerp_demo');

        Cookie::delete($cookie, '/');

        $this->_active = FALSE;
    }

    /**
     * Закрыть сессию посетителя стенда.
     *
     * Сессия, поднятая в демо-базе, в рабочей базе смысла не имеет:
     * идентификаторы пользователей там свои, и та же цифра может указывать на
     * другого человека (гость стенда — на сотрудника). Поэтому вместе с
     * выходом из демо сессия уничтожается, а не «переезжает» в рабочую базу.
     *
     * @return  void
     */
    public function drop_session()
    {
        if (PHP_SAPI === 'cli' OR ! class_exists('Auth'))
        {
            return;
        }

        try
        {
            if (Auth::instance()->logged_in())
            {
                Auth::instance()->logout(TRUE);
            }
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp811: сессия стенда не закрыта — :error', array(
                ':error' => $e->getMessage(),
            ));
        }
    }

    /**
     * Несёт ли учётная запись стенда нужную роль.
     *
     * @param   Model_User  $user
     * @param   string      $role
     * @return  bool
     */
    protected function user_has_role($user, $role)
    {
        foreach ($user->roles->find_all() as $row)
        {
            if ((string) $row->name === (string) $role)
            {
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * Сменить посетителя стенда: закрыть прежнюю сессию и поднять новую.
     *
     * `logout(TRUE)` уничтожает сессию вместе с идентификатором, но объект
     * сессии остаётся прежним, и следующая запись (force_login) падает с
     * «Session ID cannot be regenerated when there is no active session»:
     * переключение роли молча не срабатывало, и посетитель оставался без входа
     * на странице, которую его роль не открывает («Доступ ограничен»).
     *
     * @return  void
     */
    protected function reset_session()
    {
        if (PHP_SAPI === 'cli' OR ! class_exists('Auth'))
        {
            return;
        }

        $auth = Auth::instance();

        if ($auth->logged_in())
        {
            $auth->logout(TRUE);
        }

        // Сессию поднимаем заново под тем же объектом: Auth держит ссылку на
        // него, поэтому пересоздавать объект нельзя — запись ушла бы в старую,
        // уже уничтоженную сессию.
        if (class_exists('Session'))
        {
            Session::instance()->restart();
        }
    }

    /**
     * Войти гостем демо-стенда: учётная запись создаётся при сборке.
     *
     * @return  bool
     */
    public function login_guest()
    {
        $email = (string) $this->config('guest_email', 'guest@demo.promerp.local');

        try
        {
            $user = ORM::factory('User')->where('email', '=', $email)->find();

            if ( ! $user->loaded())
            {
                return FALSE;
            }

            // Посетитель мог прийти на стенд уже авторизованным в рабочей
            // базе (или войти ролью раньше): сессия обязана принадлежать гостю
            // стенда, иначе в демо-базе она указывает на чужую учётную запись.
            $this->reset_session();

            Auth::instance()->force_login($user);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp811: гостевой вход не удался — :error', array(':error' => $e->getMessage()));

            return FALSE;
        }

        return TRUE;
    }

    /**
     * Роли, под которыми посетитель может осмотреть стенд.
     *
     * Гость (`demo`) видит витрины, но не рабочие места: кабинет собирается под
     * роль. Чтобы показать смысл системы — «что делать этому человеку прямо
     * сейчас», — посетителю дают выбрать роль ещё на входе. Список задаётся в
     * `config/demo.php` (`roleplay`), но показываются только те роли, для
     * которых в демо-базе действительно есть учётная запись: предложить выбор,
     * который закончится отказом, хуже, чем не предлагать его вовсе.
     *
     * Если демо-база недоступна, список отдаётся как есть — решение о входе
     * всё равно принимает `login_as_role()`.
     *
     * @return  array
     */
    public function roleplay()
    {
        if ($this->_roleplay !== NULL)
        {
            return $this->_roleplay;
        }

        $roles = array();

        foreach ((array) $this->config('roleplay', array()) as $entry)
        {
            $entry = (array) $entry;

            // Без кода роли и адреса учётной записи вход невозможен: такая
            // строка конфига бесполезна для посетителя.
            if (empty($entry['role']) OR empty($entry['email']))
            {
                continue;
            }

            $roles[] = $entry + array(
                'title' => (string) $entry['email'],
                'hint'  => '',
                'start' => '/',
                'icon'  => 'fa-user',
            );
        }

        $this->_roleplay = $this->only_existing_accounts($roles);

        return $this->_roleplay;
    }

    /**
     * Найти роль осмотра по её коду.
     *
     * @param   string  $role
     * @return  array|NULL
     */
    public function roleplay_entry($role)
    {
        foreach ($this->roleplay() as $entry)
        {
            if ((string) $entry['role'] === (string) $role)
            {
                return $entry;
            }
        }

        return NULL;
    }

    /**
     * Войти на стенд под выбранной ролью.
     *
     * Пароль не спрашивается — это демонстрация, а не аутентификация. Но войти
     * можно только под учёткой из `roleplay`: адрес приходит из браузера, и
     * принимать произвольный email значило бы дать посетителю выбрать любого
     * пользователя демо-базы, включая администратора.
     *
     * @param   string  $role
     * @return  bool
     */
    public function login_as_role($role)
    {
        $entry = $this->roleplay_entry($role);

        if ($entry === NULL)
        {
            return FALSE;
        }

        try
        {
            $user = ORM::factory('User')->where('email', '=', $entry['email'])->find();

            if ( ! $user->loaded())
            {
                return FALSE;
            }

            // Учётная запись стенда обязана нести свою роль. Если роль не
            // назначена (стенд собран старой версией), вход пройдёт, но первый
            // же экран роли ответит «Доступ ограничен» — лучше сказать об этом
            // сразу и понятно.
            if ( ! $this->user_has_role($user, $entry['role']))
            {
                Kohana::$log->add(Log::WARNING, 'erp811: у учётной записи :email нет роли :role — стенд собран старой версией', array(
                    ':email' => (string) $entry['email'],
                    ':role'  => (string) $entry['role'],
                ));

                return FALSE;
            }

            // Посетитель мог уже войти гостем, а теперь выбрать роль (или
            // сменить роль на другую). Сессию закрываем, но не оставляем
            // уничтоженной: иначе force_login не сможет записать пользователя.
            $this->reset_session();

            Auth::instance()->force_login($user);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp811: вход под ролью :role не удался — :error', array(
                ':role'  => $role,
                ':error' => $e->getMessage(),
            ));

            return FALSE;
        }

        return TRUE;
    }

    /**
     * Оставить роли, для которых в демо-базе есть учётная запись.
     *
     * Проверка идёт прямым соединением с демо-базой: страница входа
     * открывается до того, как режим стенда включён, и переключать ORM здесь
     * было бы преждевременно.
     *
     * @param   array   $roles
     * @return  array
     */
    protected function only_existing_accounts(array $roles)
    {
        if ( ! $roles)
        {
            return $roles;
        }

        $emails = array();

        foreach ($roles as $entry)
        {
            $emails[] = mb_strtolower(trim((string) $entry['email']));
        }

        try
        {
            $pdo = $this->pdo();

            if ($pdo === NULL)
            {
                return $roles;
            }

            $placeholders = implode(', ', array_fill(0, count($emails), '?'));

            $statement = $pdo->prepare(
                'SELECT LOWER(TRIM(email)) FROM users WHERE LOWER(TRIM(email)) IN ('.$placeholders.')'
            );
            $statement->execute($emails);

            $found = $statement->fetchAll(PDO::FETCH_COLUMN);

            $existing = array();

            foreach ($roles as $entry)
            {
                if (in_array(mb_strtolower(trim((string) $entry['email'])), $found, TRUE))
                {
                    $existing[] = $entry;
                }
            }

            return $existing;
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp811: роли осмотра не проверены по демо-базе — :error', array(
                ':error' => $e->getMessage(),
            ));

            return $roles;
        }
    }

    /**
     * Демо-режим показывает систему и не принимает правки.
     *
     * @return  void
     * @throws  HTTP_Exception_403
     */
    public function guard_read_only()
    {
        if ( ! $this->active())
        {
            return;
        }

        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';

        if (in_array($method, array('GET', 'HEAD', 'OPTIONS'), TRUE))
        {
            return;
        }

        // Вход и выход из демо — свои же действия стенда: без них посетитель
        // не смог бы выйти из режима «только чтение». Всё остальное — отказ.
        $path = parse_url(isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH);
        $path = '/'.trim((string) $path, '/');

        // Адрес может прийти и с index.php: когда rewrite недоступен, сайт
        // работает по прямым ссылкам вида /index.php/demo/enter.
        if (strpos($path, '/index.php/') === 0 OR $path === '/index.php')
        {
            $path = substr($path, strlen('/index.php'));
        }

        foreach (array('erp811_demo_enter', 'erp811_demo_exit') as $name)
        {
            $route = Route::get($name);

            if ($route AND '/'.trim($route->uri(array()), '/') === $path)
            {
                return;
            }
        }

        // Вход в систему из демо-режима. Пароль проверялся бы по базе стенда,
        // а сессия осталась бы от неё, поэтому сначала выходим из демо и
        // возвращаем человека на ту же страницу входа — уже в рабочую базу.
        // Иначе получается тупик: выход из учётной записи снимает сессию, но
        // cookie стенда остаётся, и вход отвечает 403 вместо страницы входа.
        if ($path === '/auth/login')
        {
            $this->leave();
            $this->drop_session();

            throw HTTP_Exception::factory(302)->location($path);
        }

        throw new HTTP_Exception_403(
            'Демо-стенд работает только на чтение: изменения отключены, чтобы система осталась '
            .'такой же для следующего посетителя. Полную версию можно поднять по инструкции из README.'
        );
    }

    // =========================================================================
    //  Состояние стенда
    // =========================================================================

    /**
     * Подписи счётчиков стенда: и консоль, и страница показывают одни и те же
     * цифры одними и теми же словами. Ключи — из `counts` журнала сборки.
     *
     * @return  array
     */
    public function count_labels()
    {
        return array(
            'users'                => 'Учётных записей',
            'departments'          => 'Подразделений',
            'positions'            => 'Должностей',
            'staff_units'          => 'Строк штатного расписания',
            'employees'            => 'Сотрудников',
            'crm_profiles'         => 'Профилей клиентов в CRM',
            'crm_contacts'         => 'Контактов клиентов',
            'crm_deals'            => 'Сделок',
            'crm_activities'       => 'Звонков, встреч и задач по сделкам',
            'crm_knowledge'        => 'Статей базы знаний',
            'structures'           => 'Ревизий составов изделий',
            'structure_lines'      => 'Строк составов',
            'qr_objects'           => 'Свободных объектов QR',
            'qr_tokens'            => 'QR-наклеек',
            'qr_scans'             => 'Сканирований и сценариев терминала',
            'qr_inventories'       => 'Инвентаризаций',
            'registry_types'       => 'Типов объектов',
            'counterparties'       => 'Контрагентов',
            'items'                => 'Позиций номенклатуры',
            'warehouses'           => 'Складов',
            'centers'              => 'Рабочих центров',
            'shifts'               => 'Смен',
            'stock_receipts'       => 'Приходов на склад',
            'stock_qty'            => 'Закуплено материалов, кг',
            'tech_cards'           => 'Технологических карт',
            'orders'               => 'Производственных заказов',
            'operations'           => 'Операций цеха',
            'downtimes'            => 'Простоев',
            'inspections'          => 'Контролей качества',
            'defect_codes'         => 'Кодов дефектов',
            'good_qty'             => 'Годных деталей, шт.',
            'shipments'            => 'Отгрузок клиентам',
            'shipped_qty'          => 'Отгружено деталей, шт.',
            'shipped_value'        => 'Отгружено по прайсу, ₽',
            'samples_write_off'    => 'Пробных образцов, списаний',
            'samples_qty'          => 'Пробных образцов, кг',
            'programs'             => 'Программ выпуска',
            'requirements'         => 'Строк потребности',
            'proposals'            => 'Предложений по дефицитам',
            'requests'             => 'Заявок на закупку',
            'purchase_orders'      => 'Заказов поставщикам',
            'purchase_orders_overdue' => 'Просроченных поставок',
            'purchase_orders_open' => 'Заказов в пути',
            'receipts'             => 'Приёмок',
            'documents'            => 'Печатных документов',
            'settings'             => 'Порогов витрин',
            'snapshots'            => 'Снимков витрин',
            'purchase_orders_transit' => 'Заказов поставщикам в пути',
            'truncated_tables'     => 'Стенд собран поверх непустой базы (таблиц с прежними данными)',
        );
    }

    /**
     * Что известно о демо-базе: есть ли она, собрана ли, чем наполнена.
     *
     * @return  array
     */
    public function status()
    {
        $status = array(
            'enabled'    => $this->enabled(),
            'active'     => $this->active(),
            'database'   => $this->database_name(),
            'storage'    => $this->storage_mode(),
            'schema'     => $this->uses_shared_database() ? $this->schema_name() : 'public',
            'available'  => $this->available(TRUE),
            'build_state'=> 'empty',
            'scenario'   => (string) $this->config('scenario', 'print3d'),
            'built_at'   => NULL,
            'build_user' => NULL,
            'counts'     => array(),
            'problems'   => array(),
            'tables'     => 0,
            'error'      => NULL,
        );

        try
        {
            $pdo = $this->pdo();

            if ($pdo === NULL)
            {
                return $status;
            }

            $schema = $this->uses_shared_database() ? $this->schema_name() : 'public';
            $status['tables'] = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '.$pdo->quote($schema))->fetchColumn();

            $row = $status['available']
                ? $pdo->query('SELECT built_at, built_by, counts FROM erp_811_demo ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC)
                : FALSE;

            if ($row)
            {
                $status['built_at']   = $row['built_at'];
                $status['build_user'] = $row['built_by'] !== NULL ? (int) $row['built_by'] : NULL;
                $status['counts']     = json_decode((string) $row['counts'], TRUE) ?: array();
            }
        }
        catch (Exception $e)
        {
            $status['error'] = $e->getMessage();
        }

        $status['build_state'] = $status['available']
            ? 'ready'
            : ($status['tables'] > 0 ? 'schema_ready_data_incomplete' : 'empty');

        return $status;
    }

    /**
     * Журнал сборок демо-стенда (из демо-базы).
     *
     * @param   int $limit
     * @return  array
     */
    public function runs($limit = 10)
    {
        if ( ! $this->available())
        {
            return array();
        }

        $was_active = $this->_active;

        if ( ! $this->use_demo_database())
        {
            return array();
        }

        try
        {
            $rows = DB::query(Database::SELECT, 'SELECT id, built_at, built_by, scenario, counts FROM erp_811_demo ORDER BY id DESC LIMIT :limit')
                ->param(':limit', (int) $limit)
                ->execute()
                ->as_array();
        }
        catch (Exception $e)
        {
            $rows = array();
        }

        if ( ! $was_active)
        {
            $this->use_main_database();
        }

        return $rows;
    }

    // =========================================================================
    //  Сборка стенда (используется командой demo:build)
    // =========================================================================

    /**
     * Есть ли демо-база на сервере (по списку баз, без подключения к ней).
     *
     * @return  bool
     */
    public function database_exists()
    {
        if ($this->uses_shared_database())
        {
            return TRUE;
        }

        try
        {
            $row = DB::query(Database::SELECT, 'SELECT 1 FROM pg_database WHERE datname = :name')
                ->param(':name', $this->database_name())
                ->execute()
                ->get('?column?');

            return ($row !== NULL);
        }
        catch (Exception $e)
        {
            return FALSE;
        }
    }

    /**
     * Создать локальный реестр миграций внутри demo-схемы.
     *
     * В schema-mode `public` присутствует в search_path ради типов расширений.
     * Без собственного реестра migrator увидит `public.migrations_pg`, решит,
     * что всё уже применено, и оставит demo-схему пустой.
     */
    protected function ensure_schema_migration_registry()
    {
        if ( ! $this->uses_shared_database())
        {
            return;
        }

        $schema = '"'.str_replace('"', '""', $this->schema_name()).'"';
        DB::query(NULL, 'CREATE TABLE IF NOT EXISTS '.$schema.'.migrations_pg (
            id SERIAL PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            applied_at TIMESTAMP NOT NULL
        )')->execute();
    }

    /**
     * Создать демо-базу либо изолированную схему в основной базе.
     *
     * @return  bool  создано ли хранилище (FALSE — уже было)
     */
    public function create_database()
    {
        if ($this->uses_shared_database())
        {
            $schema = $this->schema_name();
            $exists = DB::query(Database::SELECT, 'SELECT 1 FROM pg_namespace WHERE nspname = :schema')
                ->param(':schema', $schema)->execute()->get('?column?');

            if ($exists !== NULL)
            {
                $this->ensure_schema_migration_registry();

                return FALSE;
            }

            DB::query(NULL, 'CREATE SCHEMA "'.str_replace('"', '""', $schema).'"')->execute();
            $this->ensure_schema_migration_registry();

            return TRUE;
        }

        if ($this->database_exists())
        {
            return FALSE;
        }

        // CREATE DATABASE не принимает параметры-плейсхолдеры, поэтому имя
        // экранируется вручную и только кавычками идентификатора.
        DB::query(NULL, 'CREATE DATABASE "'.str_replace('"', '""', $this->database_name()).'"')->execute();

        return TRUE;
    }

    /**
     * Применить миграции модулей демо-стенда в его базу.
     *
     * Работает после `use_demo_database()`: миграции идут по группе `default`,
     * а она в этот момент смотрит в демо-базу.
     *
     * @param   callable|null   $logger
     * @return  array           применённые миграции по модулям
     */
    public function migrate($logger = NULL)
    {
        $applied = array();
        $this->ensure_schema_migration_registry();

        foreach ((array) $this->config('modules', array()) as $module)
        {
            // Свежий мигратор на каждый модуль: у него свой кэш состояния.
            $migrator = Service_Erp111_Migrator::instance('default');

            $stats = $migrator->migrate(array('module' => $module), $logger);

            if ( ! empty($stats['failed']))
            {
                $error = isset($stats['errors'][$stats['failed']])
                    ? $stats['errors'][$stats['failed']]
                    : 'неизвестная ошибка';
                throw new Kohana_Exception(
                    'Миграция :migration модуля :module не применена: :error',
                    array(':migration' => $stats['failed'], ':module' => $module, ':error' => $error)
                );
            }

            if ( ! empty($stats['applied']))
            {
                $applied[$module] = $stats['applied'];
            }
        }

        return $applied;
    }

    /**
     * Очистить демо-базу: снести схему и создать заново.
     *
     * @return  void
     */
    public function drop_schema()
    {
        $schema = $this->uses_shared_database() ? $this->schema_name() : 'public';
        $quoted = '"'.str_replace('"', '""', $schema).'"';

        // В shared mode удаляется исключительно выделенная demo-схема.
        // `public` основной базы никогда не является целью этой ветки.
        DB::query(NULL, 'DROP SCHEMA IF EXISTS '.$quoted.' CASCADE')->execute();
        DB::query(NULL, 'CREATE SCHEMA '.$quoted)->execute();
        $this->ensure_schema_migration_registry();
    }

    /**
     * Отметка о сборке — пишется в демо-базу.
     *
     * @param   array       $counts
     * @param   int|null    $user_id
     * @param   string|null $scenario
     * @return  void
     */
    public function mark_built(array $counts, $user_id = NULL, $scenario = NULL)
    {
        DB::insert('erp_811_demo', array('built_at', 'built_by', 'scenario', 'counts'))
            ->values(array(
                date('Y-m-d H:i:s'),
                $user_id ? (int) $user_id : NULL,
                (string) ($scenario !== NULL ? $scenario : $this->config('scenario', 'print3d')),
                json_encode($counts, JSON_UNESCAPED_UNICODE),
            ))
            ->execute();
    }
}
