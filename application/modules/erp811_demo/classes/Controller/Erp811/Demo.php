<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Демонстрационный стенд (erp811_demo): лендинг, вход гостя, тур по системе,
 * интерактивная инициализация стенда из UI и сборка диагностического лога.
 *
 * @package    Erp811
 * @category   Controllers
 */
class Controller_Erp811_Demo extends Controller_Base
{
    /** @var Service_Erp811_Demo|null */
    protected $_demo;

    /**
     * Стенд открыт без предварительного логина для свободного исследования и демонстрации.
     */
    protected $public_actions = array('index', 'tour', 'workspace', 'enter', 'exit', 'setup', 'build', 'progress', 'diagnose', 'log_download');

    public function before()
    {
        parent::before();

        $this->_demo = Service_Erp811_Demo::instance();
    }

    // =========================================================================
    //  Страницы
    // =========================================================================

    /**
     * Лендинг стенда: что за производство, что смотреть, как войти.
     */
    public function action_index()
    {
        $scenario = $this->_scenario();
        $status   = $this->_demo->enabled() ? $this->_demo->status() : array('available' => FALSE, 'error' => 'Модуль выключен');

        $this->template->title   = $scenario->company()['name'].' — демонстрация PromERP';
        $this->template->content = View::factory('erp811/demo')
            ->set('scenario', $scenario)
            ->set('status', $status)
            ->set('inside', $this->_demo->active())
            ->set('scenario_code', $scenario->code())
            ->set('guest', $this->_demo->config('guest', array()))
            ->set('roleplay', $this->_demo->roleplay())
            ->set('tour', $this->_tour_rows())
            ->set('labels', $this->_demo->count_labels())
            ->set('problems', $this->_problems());
    }

    /**
     * Тур по системе: маршрут «куда идти и что смотреть» — с данными стенда.
     */
    public function action_tour()
    {
        $scenario = $this->_scenario();

        $this->template->title   = 'Тур по демонстрационному стенду';
        $this->template->content = View::factory('erp811/tour')
            ->set('scenario', $scenario)
            ->set('tour', $this->_tour_rows())
            ->set('inside', $this->_demo->active())
            ->set('labels', $this->_demo->count_labels())
            ->set('counts', $this->_counts());
    }

    /**
     * Рабочее место: один экран на вопрос «что делать прямо сейчас».
     */
    public function action_workspace()
    {
        if ( ! $this->_demo->active() AND ! $this->_current_user)
        {
            $this->redirect(Route::get('erp811_demo_index')->uri());
        }

        $from = (string) $this->request->query('from');
        $to   = (string) $this->request->query('to');

        $filters = array(
            'from' => ($from !== '' AND preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) ? $from : date('Y-m-d', strtotime('-30 days')),
            'to'   => ($to !== '' AND preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) ? $to : date('Y-m-d'),
        );

        $scenario = $this->_scenario();

        $this->template->title   = 'Рабочее место — '.$scenario->company()['name'];
        $this->template->content = View::factory('erp811/workspace')
            ->set('scenario', $scenario)
            ->set('data', Service_Erp811_Workspace::instance()->build($filters))
            ->set('inside', $this->_demo->active())
            ->set('filters', $filters);
    }

    // =========================================================================
    //  Интерактивная панель инициализации и тестирования стенда (UI Setup)
    // =========================================================================

    /**
     * Центр управления демо-стендом, интерактивная сборка и диагностика.
     */
    public function action_setup()
    {
        $scenario = $this->_scenario();
        $status   = $this->_demo->enabled() ? $this->_demo->status() : array('available' => FALSE, 'error' => 'Модуль выключен');
        $session  = Session::instance();
        $last_log = $session->get('erp811_last_log', NULL);
        $last_report = $session->get('erp811_last_report', NULL);

        if (empty($last_log))
        {
            $log_file = APPPATH . 'logs' . DIRECTORY_SEPARATOR . 'demo_last_run.log';
            if (file_exists($log_file))
            {
                $last_log = @file_get_contents($log_file);
            }
        }

        $this->template->title   = 'Управление и диагностика демо-стенда — PromERP';
        $this->template->content = View::factory('erp811/setup')
            ->set('scenario', $scenario)
            ->set('status', $status)
            ->set('inside', $this->_demo->active())
            ->set('labels', $this->_demo->count_labels())
            ->set('build_profiles', $this->_demo->build_profiles())
            ->set('selected_profile', (string) $this->_demo->config('build_profile', 'quick'))
            ->set('last_log', $last_log)
            ->set('last_report', $last_report);
    }

    /**
     * Сборка и наполнение демо-стенда из UI в 1 клик с формированием полного лога.
     */
    public function action_build()
    {
        // Сборка может пережить таймаут браузера/Apache. На shared hosting PHP
        // продолжит работу, а прогресс останется в demo_last_run.log.
        ignore_user_abort(TRUE);
        @set_time_limit(0);

        $reset = (bool) $this->request->query('reset') || (bool) $this->request->post('reset');
        $seed  = $this->request->query('seed') ?: $this->request->post('seed');
        $profile_name = $this->request->query('profile') ?: $this->request->post('profile');
        $profile = $this->_demo->apply_build_profile($profile_name);
        $started_at = microtime(TRUE);
        $log_file = APPPATH . 'logs' . DIRECTORY_SEPARATOR . 'demo_last_run.log';
        $lock_file = APPPATH . 'logs' . DIRECTORY_SEPARATOR . 'demo_build.lock';
        $lock = @fopen($lock_file, 'c');

        if ( ! $lock OR ! @flock($lock, LOCK_EX | LOCK_NB))
        {
            $message = date('Y-m-d H:i:s').' [WARN] Сборка уже выполняется другим запросом.';
            Session::instance()->set('erp811_last_log', $message);
            if ($this->request->is_ajax() || $this->request->query('format') === 'json')
            {
                $this->auto_render = FALSE;
                $this->response->headers('Content-Type', 'application/json; charset=utf-8');
                $this->response->body(json_encode(array('success' => FALSE, 'running' => TRUE, 'log' => $message), JSON_UNESCAPED_UNICODE));
                return;
            }
            $this->redirect(Route::url('erp811_demo_index', array('action' => 'setup')));
            return;
        }

        @file_put_contents($log_file, '');
        $log_lines = array();
        $logger = function ($msg, $level = 'info') use (&$log_lines, $log_file) {
            $time = date('Y-m-d H:i:s');
            $prefix = array('ok' => '[OK]   ', 'warn' => '[WARN] ', 'error' => '[FAIL] ', 'info' => '[INFO] ');
            $p = isset($prefix[$level]) ? $prefix[$level] : '[LOG]  ';
            $line = "{$time} {$p} {$msg}";
            $log_lines[] = $line;
            @file_put_contents($log_file, $line."\n", FILE_APPEND | LOCK_EX);
        };

        $logger("=== СТАРТ ИНИЦИАЛИЗАЦИИ ДЕМО-СТЕНДА PROMERP ===");
        $logger("Хранилище стенда: " . $this->_demo->storage_label());
        $logger("Сценарий: " . $this->_scenario()->headline() . " (" . $this->_scenario()->code() . ")");
        $logger('Профиль данных: '.$profile['label'].' — '.$profile['orders'].' заказов, '.$profile['history_days'].' дней истории');
        $logger("Режим пересоздания схемы: " . ($reset ? "ДА (DROP & RECREATE SCHEMA)" : "НЕТ (ИДЕМПОТЕНТНО)"));

        $success = TRUE;
        $error_msg = NULL;
        $counts = array();
        $problems = array();

        try
        {
            // 1. Создание отдельной БД либо выделенной схемы основной БД.
            $logger("Шаг 1/5: Проверка и создание хранилища...");
            $created_db = $this->_demo->create_database();
            $logger($created_db ? "Хранилище создано успешно." : "Хранилище уже существует.", "ok");

            // Версии до 06e1038 могли оставить исключительно служебные
            // *@demo.promerp.local аккаунты в public основной базы.
            $leaked_users = $this->_demo->clean_leaked_demo_users();
            if ($leaked_users > 0)
            {
                $logger("Удалены ошибочно попавшие в основную базу demo-аккаунты: {$leaked_users}.", 'warn');
            }

            // 2. Подключение к демо-базе
            $logger("Шаг 2/5: Переключение соединения на демо-базу...");
            if ( ! $this->_demo->use_demo_database())
            {
                throw new Exception("Не удалось подключиться к базе " . $this->_demo->database_name());
            }
            $logger("Соединение с демо-базой установлено.", "ok");

            // 3. Очистка схемы при сбросе
            if ($reset)
            {
                $target_schema = $this->_demo->uses_shared_database() ? $this->_demo->schema_name() : 'public';
                $logger("Шаг 3/5: Очистка схемы {$target_schema}...");
                $this->_demo->drop_schema();
                $logger("Схема {$target_schema} очищена.", "ok");
            }
            else
            {
                $logger("Шаг 3/5: Пропуск сброса схемы.");
            }

            // 4. Миграции
            $logger("Шаг 4/5: Применение миграций модулей...");
            $applied_migrations = $this->_demo->migrate($logger);
            $logger("Миграции применены: обработано модулей — " . count($applied_migrations), "ok");

            // 5. Наполнение данными
            $logger("Шаг 5/5: Наполнение сценарием через сервисы...");
            $seeder_options = $seed ? array('seed' => (int) $seed) : array();
            $seed_result = Service_Erp811_Seeder::instance()->build($seeder_options, $logger);
            $counts = $seed_result['counts'];
            $problems = $seed_result['problems'];

            // Автоматическая диагностика — это quality gate, а не декоративный
            // отчёт: нулевые контуры и пропущенные слои не считаются успехом.
            $logger("=== ЗАПУСК СКВОЗНОГО ТЕСТИРОВАНИЯ И ДИАГНОСТИКИ СИСТЕМЫ ===");
            $diagnostics = $this->_run_system_diagnostics($this->_demo, $logger);

            $duration = sprintf('%.2f', microtime(TRUE) - $started_at);
            if ($problems OR empty($diagnostics['success']))
            {
                $success = FALSE;
                $error_msg = 'Стенд не принят: замечаний сборки '.count($problems)
                    .', провалено диагностик '.(isset($diagnostics['failed_tests']) ? $diagnostics['failed_tests'] : 0).'.';
                $logger("=== СБОРКА ЗАВЕРШЕНА С ОШИБКАМИ ЗА {$duration} СЕК ===", 'error');
            }
            else
            {
                $this->_demo->mark_built($counts, NULL);
                $logger("Отметка о корректной сборке сохранена в erp_811_demo.", "ok");
                $logger("=== СБОРКА И ТЕСТИРОВАНИЕ ЗАВЕРШЕНЫ УСПЕШНО ЗА {$duration} СЕК ===");
            }
        }
        catch (Exception $e)
        {
            $success = FALSE;
            $error_msg = $e->getMessage();
            $logger("ОШИБКА СБОРКИ: " . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
        }
        finally
        {
            $this->_demo->use_main_database();
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }

        $full_log_text = implode("\n", $log_lines);

        $report_data = array(
            'success'     => $success,
            'error'       => $error_msg,
            'duration'    => sprintf('%.2f', microtime(TRUE) - $started_at),
            'timestamp'   => date('Y-m-d H:i:s'),
            'counts'      => $counts,
            'problems'    => $problems,
            'diagnostics' => isset($diagnostics) ? $diagnostics : array(),
        );

        $session = Session::instance();
        $session->set('erp811_last_log', $full_log_text);
        $session->set('erp811_last_report', $report_data);

        // Сохраняем последний лог в файл на диске для надежности
        $log_file = APPPATH . 'logs' . DIRECTORY_SEPARATOR . 'demo_last_run.log';
        @file_put_contents($log_file, $full_log_text);

        if ($this->request->is_ajax() || $this->request->query('format') === 'json')
        {
            $this->auto_render = FALSE;
            $this->response->headers('Content-Type', 'application/json; charset=utf-8');
            $this->response->body(json_encode(array(
                'success' => $success,
                'report'  => $report_data,
                'log'     => $full_log_text,
            ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return;
        }

        $this->redirect(Route::url('erp811_demo_index', array('action' => 'setup')));
    }

    /**
     * Лёгкий polling endpoint: браузер видит живой лог даже если исходный
     * запрос сборки был оборван timeout-ом reverse proxy.
     */
    public function action_progress()
    {
        $this->auto_render = FALSE;
        $log_file = APPPATH . 'logs' . DIRECTORY_SEPARATOR . 'demo_last_run.log';
        $lock_file = APPPATH . 'logs' . DIRECTORY_SEPARATOR . 'demo_build.lock';
        $handle = @fopen($lock_file, 'c');
        $running = FALSE;

        if ($handle)
        {
            $running = ! @flock($handle, LOCK_EX | LOCK_NB);
            if ( ! $running)
            {
                @flock($handle, LOCK_UN);
            }
            @fclose($handle);
        }

        $this->response->headers('Content-Type', 'application/json; charset=utf-8');
        $this->response->headers('Cache-Control', 'no-store');
        $this->response->body(json_encode(array(
            'running' => $running,
            'log' => is_file($log_file) ? (string) @file_get_contents($log_file) : '',
            'status' => $this->_demo->status(),
        ), JSON_UNESCAPED_UNICODE));
    }

    /**
     * Запуск сквозного диагностического тестирования без пересборки данных.
     */
    public function action_diagnose()
    {
        $log_lines = array();
        $logger = function ($msg, $level = 'info') use (&$log_lines) {
            $time = date('Y-m-d H:i:s');
            $prefix = array('ok' => '[PASS] ', 'warn' => '[WARN] ', 'error' => '[FAIL] ', 'info' => '[TEST] ');
            $p = isset($prefix[$level]) ? $prefix[$level] : '[DIAG] ';
            $log_lines[] = "{$time} {$p} {$msg}";
        };

        $logger("=== СТАРТ ДИАГНОСТИЧЕСКОГО ТЕСТИРОВАНИЯ СИСТЕМЫ PROMERP ===");
        $started_at = microtime(TRUE);

        if ( ! $this->_demo->available(TRUE))
        {
            $logger("Стенд не собран: выполните сборку перед тестированием.", "error");
            $report_data = array(
                'success'   => FALSE,
                'error'     => 'Стенд не собран',
                'timestamp' => date('Y-m-d H:i:s'),
                'tests'     => array(),
            );
        }
        else
        {
            $this->_demo->use_demo_database();
            try
            {
                $report_data = $this->_run_system_diagnostics($this->_demo, $logger);
                $duration = sprintf('%.2f', microtime(TRUE) - $started_at);
                $logger("=== ДИАГНОСТИКА ЗАВЕРШЕНА ЗА {$duration} СЕК: ВСЕГО ТЕСТОВ {$report_data['total_tests']}, ОШИБОК: {$report_data['failed_tests']} ===");
            }
            finally
            {
                $this->_demo->use_main_database();
            }
        }

        $full_log = implode("\n", $log_lines);
        $session = Session::instance();
        $session->set('erp811_last_log', $full_log);
        $session->set('erp811_last_report', $report_data);

        // Сохраняем последний лог в файл на диске для надежности
        $log_file = APPPATH . 'logs' . DIRECTORY_SEPARATOR . 'demo_last_run.log';
        @file_put_contents($log_file, $full_log);

        if ($this->request->is_ajax() || $this->request->query('format') === 'json')
        {
            $this->auto_render = FALSE;
            $this->response->headers('Content-Type', 'application/json; charset=utf-8');
            $this->response->body(json_encode(array(
                'success' => $report_data['success'] ?? TRUE,
                'report'  => $report_data,
                'log'     => $full_log,
            ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return;
        }

        $this->redirect(Route::url('erp811_demo_index', array('action' => 'setup')));
    }

    /**
     * Скачать полный диагностический лог в формате .log/.txt
     */
    public function action_log_download()
    {
        $this->auto_render = FALSE;
        $session = Session::instance();
        $log = $session->get('erp811_last_log', "Лог пуст. Выполните сборку или диагностику стенда.");

        $filename = 'promerp_diagnostic_' . date('Ymd_His') . '.log';
        $this->response->headers('Content-Type', 'text/plain; charset=utf-8');
        $this->response->headers('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $this->response->body($log);
    }

    /**
     * Выполнение набора сквозных тестов компонентов и сервисов в демо-базе.
     */
    protected function _run_system_diagnostics(Service_Erp811_Demo $demo, $logger = NULL)
    {
        $tests = array();
        $total = 0;
        $failed = 0;

        $log = function ($msg, $lvl = 'info') use ($logger) {
            if ($logger && is_callable($logger)) call_user_func($logger, $msg, $lvl);
        };

        // Тест 1: Проверка таблиц БД
        $total++;
        try {
            $schema = $demo->uses_shared_database() ? $demo->schema_name() : 'public';
            $tables_count = (int) DB::query(Database::SELECT, 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :schema')
                ->param(':schema', $schema)->execute()->get('count');
            if ($tables_count >= 20) {
                $tests[] = array('name' => 'Схема БД PostgreSQL', 'status' => 'PASS', 'details' => "Обнаружено {$tables_count} таблиц в схеме {$schema}.");
                $log("Тест БД: {$tables_count} таблиц [PASS]", 'ok');
            } else {
                $failed++;
                $tests[] = array('name' => 'Схема БД PostgreSQL', 'status' => 'WARN', 'details' => "Мало таблиц: {$tables_count} (ожидалось >= 20)");
                $log("Тест БД: мало таблиц ({$tables_count}) [WARN]", 'warn');
            }
        } catch (Exception $e) {
            $failed++;
            $tests[] = array('name' => 'Схема БД PostgreSQL', 'status' => 'FAIL', 'details' => $e->getMessage());
            $log("Тест БД: {$e->getMessage()} [FAIL]", 'error');
        }

        // Тест 2: Проверка пользователей и ролей
        $total++;
        try {
            $users_count = (int) DB::select(array(DB::expr('COUNT(*)'), 'cnt'))
                ->from('users')->execute()->get('cnt');
            $guest_email = trim((string) $demo->config('guest_email', 'guest@demo.promerp.local'));
            $guest_role = (string) $demo->config('guest_role', 'demo');
            $guest_count = (int) DB::select(array(DB::expr('COUNT(DISTINCT u.id)'), 'cnt'))
                ->from(array('users', 'u'))
                ->join(array('roles_users', 'ru'), 'INNER')->on('ru.user_id', '=', 'u.id')
                ->join(array('roles', 'r'), 'INNER')->on('r.id', '=', 'ru.role_id')
                ->where(DB::expr('LOWER(TRIM(u.email))'), '=', strtolower($guest_email))
                ->where('r.name', '=', $guest_role)
                ->execute()->get('cnt');

            if ($guest_count === 1) {
                $tests[] = array('name' => 'Учетные записи и Гость', 'status' => 'PASS', 'details' => "Всего пользователей: {$users_count}, гость {$guest_email} зарегистрирован с ролью {$guest_role}.");
                $log("Тест пользователей: {$users_count} аккаунтов, гость и роль {$guest_role} [PASS]", 'ok');
            } else {
                $failed++;
                $details = "Гость {$guest_email} с ролью {$guest_role} не найден; всего аккаунтов: {$users_count}.";
                $tests[] = array('name' => 'Учетные записи и Гость', 'status' => 'FAIL', 'details' => $details);
                $log("Тест пользователей: {$details} [FAIL]", 'error');
            }
        } catch (Exception $e) {
            $failed++;
            $tests[] = array('name' => 'Учетные записи и Гость', 'status' => 'FAIL', 'details' => $e->getMessage());
            $log("Тест пользователей: {$e->getMessage()} [FAIL]", 'error');
        }

        // Тест 3: Складской учет WMS
        $total++;
        try {
            $balances = DB::select(array(DB::expr('COUNT(*)'), 'cnt'), array(DB::expr('COALESCE(SUM(qty), 0)'), 'total_qty'))->from('erp_401_balances')->execute()->current();
            $movements = DB::select(array(DB::expr('COUNT(*)'), 'cnt'))->from('erp_401_movements')->execute()->get('cnt');
            if ((int) $balances['cnt'] < 1 OR (int) $movements < 1) throw new UnexpectedValueException('Синтетические остатки или движения не созданы');
            $tests[] = array('name' => 'Складской учет (WMS)', 'status' => 'PASS', 'details' => "Позиций на остатках: {$balances['cnt']} (общий объем: " . round((float)$balances['total_qty'], 1) . "), проводок: {$movements}.");
            $log("Тест WMS: {$balances['cnt']} остатков, {$movements} проводок [PASS]", 'ok');
        } catch (Exception $e) {
            $failed++;
            $tests[] = array('name' => 'Складской учет (WMS)', 'status' => 'FAIL', 'details' => $e->getMessage());
            $log("Тест WMS: {$e->getMessage()} [FAIL]", 'error');
        }

        // Тест 4: Производственные заказы и техкарты
        $total++;
        try {
            $orders_count = DB::select(array(DB::expr('COUNT(*)'), 'cnt'))->from('erp_402_orders')->execute()->get('cnt');
            $cards_count = DB::select(array(DB::expr('COUNT(*)'), 'cnt'))->from('erp_228_tech_cards')->execute()->get('cnt');
            if ((int) $orders_count < 1 OR (int) $cards_count < 1) throw new UnexpectedValueException('Техкарты или производственные заказы не созданы');
            $tests[] = array('name' => 'Производство & Техкарты', 'status' => 'PASS', 'details' => "Техкарт: {$cards_count}, Заказов на производство: {$orders_count}.");
            $log("Тест производства: {$cards_count} карт, {$orders_count} заказов [PASS]", 'ok');
        } catch (Exception $e) {
            $failed++;
            $tests[] = array('name' => 'Производство & Техкарты', 'status' => 'FAIL', 'details' => $e->getMessage());
            $log("Тест производства: {$e->getMessage()} [FAIL]", 'error');
        }

        // Тест 5: Цех, рабочие центры и операции
        $total++;
        try {
            $centers = DB::select(array(DB::expr('COUNT(*)'), 'cnt'))->from('erp_408_work_centers')->execute()->get('cnt');
            $ops = DB::select(array(DB::expr('COUNT(*)'), 'cnt'))->from('erp_408_operations')->execute()->get('cnt');
            $logs_cnt = DB::select(array(DB::expr('COUNT(*)'), 'cnt'))->from('erp_408_worklogs')->execute()->get('cnt');
            if ((int) $centers < 1 OR (int) $ops < 1 OR (int) $logs_cnt < 1) throw new UnexpectedValueException('Центры, операции или выработка не созданы');
            $tests[] = array('name' => 'Цех & Сменные операции', 'status' => 'PASS', 'details' => "Рабочих центров: {$centers}, операций: {$ops}, записей выработки: {$logs_cnt}.");
            $log("Тест цеха: {$centers} центров, {$ops} операций, {$logs_cnt} записей [PASS]", 'ok');
        } catch (Exception $e) {
            $failed++;
            $tests[] = array('name' => 'Цех & Сменные операции', 'status' => 'FAIL', 'details' => $e->getMessage());
            $log("Тест цеха: {$e->getMessage()} [FAIL]", 'error');
        }

        // Тест 6: Качество и карантин ОТК
        $total++;
        try {
            $inspections = DB::select(array(DB::expr('COUNT(*)'), 'cnt'))->from('erp_405_inspections')->execute()->get('cnt');
            $defects = DB::select(array(DB::expr('COUNT(*)'), 'cnt'))->from('erp_405_defects')->execute()->get('cnt');
            if ((int) $inspections < 1 OR (int) $defects < 1) throw new UnexpectedValueException('Контроли качества или дефекты не созданы');
            $tests[] = array('name' => 'Контроль качества (ОТК)', 'status' => 'PASS', 'details' => "Проведено контролей: {$inspections}, зафиксировано дефектов: {$defects}.");
            $log("Тест ОТК: {$inspections} контролей, {$defects} дефектов [PASS]", 'ok');
        } catch (Exception $e) {
            $failed++;
            $tests[] = array('name' => 'Контроль качества (ОТК)', 'status' => 'FAIL', 'details' => $e->getMessage());
            $log("Тест ОТК: {$e->getMessage()} [FAIL]", 'error');
        }

        // Тест 7: Закупки и снабжение
        $total++;
        try {
            $pos = DB::select(array(DB::expr('COUNT(*)'), 'cnt'))->from('erp_403_orders')->execute()->get('cnt');
            $receipts = DB::select(array(DB::expr('COUNT(*)'), 'cnt'))->from('erp_403_receipts')->execute()->get('cnt');
            if ((int) $pos < 1 OR (int) $receipts < 1) throw new UnexpectedValueException('Заказы поставщикам или приёмки не созданы');
            $tests[] = array('name' => 'Закупки & Поставки', 'status' => 'PASS', 'details' => "Заказов поставщикам: {$pos}, приемок ТМЦ: {$receipts}.");
            $log("Тест закупок: {$pos} заказов, {$receipts} приемок [PASS]", 'ok');
        } catch (Exception $e) {
            $failed++;
            $tests[] = array('name' => 'Закупки & Поставки', 'status' => 'FAIL', 'details' => $e->getMessage());
            $log("Тест закупок: {$e->getMessage()} [FAIL]", 'error');
        }

        // Тест 8: Аналитика и OEE витрины
        $total++;
        try {
            $settings = DB::select(array(DB::expr('COUNT(*)'), 'cnt'))->from('erp_801_settings')->execute()->get('cnt');
            $snapshots = DB::select(array(DB::expr('COUNT(*)'), 'cnt'))->from('erp_801_snapshots')->execute()->get('cnt');
            if ((int) $settings < 1 OR (int) $snapshots < 1) throw new UnexpectedValueException('Пороги или снимки аналитики не созданы');
            $tests[] = array('name' => 'Аналитика & Витрины', 'status' => 'PASS', 'details' => "Порогов KPI: {$settings}, снимков витрин: {$snapshots}.");
            $log("Тест аналитики: {$settings} порогов, {$snapshots} снимков [PASS]", 'ok');
        } catch (Exception $e) {
            $failed++;
            $tests[] = array('name' => 'Аналитика & Витрины', 'status' => 'FAIL', 'details' => $e->getMessage());
            $log("Тест аналитики: {$e->getMessage()} [FAIL]", 'error');
        }

        // Тест 9: сотрудники, CRM, составы изделий и QR-метки
        $total++;
        try {
            $parts = array();
            $tables = array(
                'erp_310_employees'  => 'сотрудников',
                'erp_221_deals'      => 'сделок',
                'erp_231_structures' => 'составов',
                'erp_199_tokens'     => 'QR-наклеек',
            );
            foreach ($tables as $table => $label) {
                $count = (int) DB::select(array(DB::expr('COUNT(*)'), 'cnt'))->from($table)->execute()->get('cnt');
                if ($count < 1) throw new UnexpectedValueException('Нет данных: '.$label.' ('.$table.')');
                $parts[] = $label.': '.$count;
            }
            $details = 'В демо-базе есть '.implode(', ', $parts).'.';
            $tests[] = array('name' => 'Сотрудники, CRM, составы, QR', 'status' => 'PASS', 'details' => $details);
            $log('Тест контура людей и учёта: '.implode(', ', $parts).' [PASS]', 'ok');
        } catch (Exception $e) {
            $failed++;
            $tests[] = array('name' => 'Сотрудники, CRM, составы, QR', 'status' => 'FAIL', 'details' => $e->getMessage());
            $log('Тест контура людей и учёта: '.$e->getMessage().' [FAIL]', 'error');
        }

        return array(
            'success'      => ($failed === 0),
            'total_tests'  => $total,
            'passed_tests' => ($total - $failed),
            'failed_tests' => $failed,
            'tests'        => $tests,
        );
    }

    // =========================================================================
    //  Вход и выход
    // =========================================================================

    /**
     * Войти в демо: гость входит менеджером в базе стенда.
     */
    public function action_enter()
    {
        if (strtoupper($this->request->method()) !== 'POST')
        {
            throw HTTP_Exception::factory(405, 'Вход в демо выполняется POST-запросом')
                ->allowed(array('POST'));
        }

        if ( ! $this->_demo->enabled())
        {
            $this->_fail('Демо-стенд выключен: включите модуль в application/config/demo.php');

            return;
        }

        $status = $this->_demo->status();

        if (empty($status['available']))
        {
            $this->redirect(Route::url('erp811_demo_index', array('action' => 'setup')));
            return;
        }

        // Посетитель мог выбрать роль осмотра: под ней видно рабочее место, а
        // не только витрины. Роль берётся из списка в конфиге: принимать
        // произвольную учётную запись из браузера нельзя — так посетитель
        // вошёл бы администратором стенда.
        $role  = trim((string) $this->request->post('role'));
        $entry = ($role !== '') ? $this->_demo->roleplay_entry($role) : NULL;

        try
        {
            $this->_demo->enter();

            if ($entry !== NULL)
            {
                if ( ! $this->_demo->login_as_role($entry['role']))
                {
                    $this->_rollback();

                    $this->_fail('Не удалось войти под ролью «'.$entry['title'].'»: в базе стенда нет такой '
                        .'учётной записи или у неё не назначена роль. Похоже, стенд собран старой версией — '
                        .'обновите демо-базу: bin/console demo --build');

                    return;
                }
            }
            elseif ( ! $this->_demo->login_guest())
            {
                $this->_rollback();

                $this->_fail('Не удалось войти гостем: в базе стенда нет учётной записи гостя');

                return;
            }
        }
        catch (Exception $e)
        {
            // Вход не состоялся — снимаем демо-режим: оставлять посетителя
            // гостем с cookie стенда нельзя, иначе первый же переход по меню
            // отвечает «Доступ ограничен» вместо страницы.
            $this->_rollback();

            $this->_fail('Вход в демо не выполнен: '.$e->getMessage());

            return;
        }

        $target = ($entry !== NULL AND ! empty($entry['start']))
            ? (string) $entry['start']
            : (string) $this->_demo->config('after_enter', '/analytics');

        $this->redirect($target);
    }

    /**
     * Выйти из демо: гость возвращается на лендинг, база — рабочая.
     */
    public function action_exit()
    {
        if (strtoupper($this->request->method()) !== 'POST')
        {
            throw HTTP_Exception::factory(405, 'Выход из демо выполняется POST-запросом')
                ->allowed(array('POST'));
        }

        $was_active = $this->_demo->active();

        $this->_demo->leave();

        // Учётная запись принадлежала демо-базе: в рабочей базе под тем же
        // номером может быть другой человек, поэтому выход из демо закрывает
        // и сессию. Иначе гость стенда оставался бы «вошедшим» после возврата.
        if ($was_active)
        {
            $this->_demo->drop_session();
        }

        $this->redirect(Route::get('erp811_demo_index')->uri());
    }

    // =========================================================================
    //  Служебное
    // =========================================================================

    /**
     * Сценарий стенда (или рабочий, если сценарий не найден).
     *
     * @return  Service_Erp811_Scenario
     */
    protected function _scenario()
    {
        $code = (string) $this->_demo->config('scenario', 'print3d');

        return Service_Erp811_Scenario::instance($code);
    }

    /**
     * Шаги тура: маршрут, что смотреть и почему это важно.
     *
     * @return  array
     */
    protected function _tour_rows()
    {
        $rows = array();

        foreach ($this->_scenario()->tour() as $index => $step)
        {
            $rows[] = array(
                'no'      => isset($step['step']) ? (int) $step['step'] : $index + 1,
                'title'   => isset($step['title']) ? $step['title'] : 'Шаг '.($index + 1),
                'url'     => isset($step['url']) ? $step['url'] : '/',
                'what'    => isset($step['what']) ? $step['what'] : '',
                'ask'     => isset($step['ask']) ? $step['ask'] : '',
            );
        }

        return $rows;
    }

    /**
     * Сколько чего на стенде: из журнала сборок, а не «на глаз».
     *
     * @return  array
     */
    protected function _counts()
    {
        if ( ! $this->_demo->active())
        {
            return array();
        }

        $status = $this->_demo->status();

        return isset($status['counts']) ? (array) $status['counts'] : array();
    }

    /**
     * Замечания последней сборки: стенд собран, но эти шаги пропущены.
     *
     * @return  array
     */
    protected function _problems()
    {
        $status = $this->_demo->status();

        return isset($status['problems']) ? (array) $status['problems'] : array();
    }

    /**
     * Показать сообщение вместо страницы демо (с понятным текстом, без стека).
     *
     * @param   string  $message
     * @return  void
     */
    /**
     * Отменить неудавшийся вход в демо.
     *
     * Если вход под ролью не состоялся после переключения на демо-базу,
     * посетитель остался бы гостем с cookie стенда: страницы закрыты ролями,
     * и первый же переход отвечал бы «Доступ ограничен». Поэтому снимаем и
     * демо-режим, и сессию стенда.
     *
     * @return  void
     */
    protected function _rollback()
    {
        $this->_demo->leave();
        $this->_demo->drop_session();
    }

    protected function _fail($message)
    {
        $this->template->title   = 'Демонстрационный стенд';
        $this->template->content = View::factory('erp811/message')
            ->set('message', $message)
            ->set('scenario', $this->_scenario());
    }
}
