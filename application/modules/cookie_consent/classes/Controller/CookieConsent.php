<?php defined('SYSPATH') or die('No direct script access.');

class Controller_CookieConsent extends Controller {

    // Определяем путь к файлу для сохранения информации о согласии
    // Рекомендуется использовать директорию, доступную для записи веб-сервером
    const CONSENT_LOG_FILE = APPPATH . 'logs' . DIRECTORY_SEPARATOR . 'cookie_consents.log';
	//const CONSENT_LOG_FILE = APPPATH . 'logs/cookie_consents.log';

    public function action_close()
    {
        // Устанавливаем cookie, чтобы окно больше не появлялось
        // Срок действия cookie - 1 год (365 дней * 24 часа * 60 минут * 60 секунд)
        Cookie::set('cookie_consent_closed', '1', Date::YEAR);

        // --- Добавляем сохранение информации о пользователе в файл ---
        try {
            $user_info = array(
                'timestamp'  => date('Y-m-d H:i:s'),
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'cookie_set' => 'cookie_consent_closed', // Какое куки было установлено
                'consent_status' => 'accepted' // Статус согласия
            );

            // Преобразуем массив в строку JSON для удобства чтения и парсинга
            $log_entry = json_encode($user_info) . PHP_EOL;

            // Открываем файл для добавления (append) и записываем данные
            // Используем FILE_APPEND для добавления в конец файла
            // LOCK_EX для предотвращения одновременной записи
            file_put_contents(self::CONSENT_LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);

        } catch (Exception $e) {
            // Логируем ошибку, если не удалось записать в файл
            // В продакшене лучше не выводить ошибку пользователю
            Kohana::$log->add(Log::ERROR, 'Failed to write cookie consent log: ' . $e->getMessage());
			
            // Можно также отправить уведомление администратору
        }
        // --- Конец добавления сохранения информации ---

        // Отправляем пустой ответ или JSON-ответ, если это AJAX-запрос
        if ($this->request->is_ajax())
        {
            $this->response->body(json_encode(array('status' => 'success')));
            $this->response->headers('Content-Type', 'application/json');
        }
        else
        {
            // Если это не AJAX, можно перенаправить пользователя
            $this->redirect(Request::initial()->referrer());
        }
    }

    /**
     * Метод для получения HTML всплывающего окна.
     * Его можно вызывать из вашего основного контроллера или шаблона.
     */
    public static function get_popup_html()
    {
        // Проверяем, был ли cookie установлен
        if (Cookie::get('cookie_consent_closed'))
        {
            return ''; // Если cookie установлен, не показываем окно
        }

        // Загружаем представление для всплывающего окна
        $view = View::factory('cookie_consent/popup');

        // Добавляем CSS и JS файлы
        // Это можно сделать через Kohana::auto_load_assets() или вручную в шаблоне
        // Для простоты, мы будем предполагать, что вы подключите их в своем основном шаблоне
        // или в этом методе, если вы хотите, чтобы они загружались только при необходимости.
        // Пример:
        // HTML::style('media/cookie_consent/css/cookie_consent.css');
        // HTML::script('media/cookie_consent/js/cookie_consent.js');

        return $view->render();
    }
}