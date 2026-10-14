<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Contact extends Controller_Base {
	
	protected $auth_required = FALSE; // Весь контроллер доступен всем

    public function action_index()
    {
        // Устанавливаем, что рендеринг шаблона будет автоматическим
        $this->auto_render = TRUE;

        // Получаем URL, с которого пришел пользователь
        // HTTP_REFERER может быть не всегда доступен или надежен,
        // но для демонстрационных целей подходит.
        $referer = Request::current()->referrer();

        // Если реферер не установлен, или это текущая страница,
        // устанавливаем значение по умолчанию или пустую строку.
        if (empty($referer) || strpos($referer, URL::site(Request::current()->uri())) !== false) {
            $referer = ''; // Или URL::site('/') для главной страницы
        }

        // Создаем представление для формы контакта
        $content = View::factory('contact/form')
            ->set('referer', $referer); // Передаем реферер в представление

        // Присваиваем содержимое переменной content в шаблоне
        $this->template->content = $content;

        // Устанавливаем мета-данные для страницы
        $this->_meta['title'] = "Связаться с нами";
        $this->_meta['description'] = "Форма обратной связи. Задайте свой вопрос или оставьте отзыв.";
    }

    public function action_send()
    {
        // Устанавливаем, что рендеринг шаблона будет автоматическим
        $this->auto_render = TRUE;

        // Устанавливаем мета-данные для страницы
        $this->_meta['title'] = "Отправка сообщения";
        $this->_meta['description'] = "Обработка отправки сообщения через форму обратной связи.";

        // Проверяем, что запрос является POST-запросом
        if ($this->request->method() === Request::POST) {
            // Получаем данные из POST-запроса
            $post = $this->request->post();

            // Определяем правила валидации
            $validation = Validation::factory($post)
                ->rule('message', 'not_empty')
                ->rule('message', 'min_length', array(':value', 10))
                ->rule('message', 'max_length', array(':value', 1000));

            // Если пользователь не авторизован, добавляем правила для email
            if (!Auth::instance()->logged_in('login')) {
                $validation
                    ->rule('email', 'not_empty')
                    ->rule('email', 'email')
                    ->rule('email', 'max_length', array(':value', 255));
            }

            // Проверяем валидацию
            if ($validation->check()) {
                try {

                    // Получаем текущего пользователя
                    $user = Auth::instance()->get_user();
                    $user_id = $user ? $user->id : NULL;
                    $email = $user ? $user->email : (isset($post['email']) ? $post['email'] : NULL);

                    // Вставляем данные в таблицу
                    DB::insert('contact_messages', array('user_id', 'email', 'message', 'referer'))
                        ->values(array(
                            $user_id,
                            $email,
                            $post['message'],
                            isset($post['referer']) ? $post['referer'] : NULL
                        ))
                        ->execute();

                    // Устанавливаем сообщение об успехе
                    $success_message = 'Ваше сообщение успешно отправлено! Мы свяжемся с вами в ближайшее время.';

                    // Создаем представление для формы контакта с сообщением об успехе
                    $content = View::factory('contact/form')
                        ->set('success_message', $success_message);

                    // Присваиваем содержимое переменной content в шаблоне
                    $this->template->content = $content;

                } catch (Exception $e) {
                    // В случае ошибки базы данных
                    $errors = array('database' => 'Произошла ошибка при сохранении сообщения. Пожалуйста, попробуйте еще раз. ' . $e->getMessage());
                    $content = View::factory('contact/form')
                        ->set('errors', $errors)
                        ->set('values', $post); // Возвращаем введенные значения
                    $this->template->content = $content;
                }

            } else {
                // Если валидация не прошла, получаем ошибки
                $errors = $validation->errors('contact'); // 'contact' - это имя файла с сообщениями об ошибках

                // Создаем представление для формы контакта с ошибками и введенными значениями
                $content = View::factory('contact/form')
                    ->set('errors', $errors)
                    ->set('values', $post); // Возвращаем введенные значения

                // Присваиваем содержимое переменной content в шаблоне
                $this->template->content = $content;
            }
        } else {
            // Если запрос не POST, перенаправляем обратно на форму
            $this->redirect('contact');
        }
    }

}