<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Erp100_Auth extends Controller_Erp100_Base {

    public function action_login()
    {
        if (Auth::instance()->logged_in())
        {
            throw HTTP_Exception::factory(302)->location(Route::url('default', array('controller' => 'welcome')));
        }

        $errors = array();
        $referrer = $this->request->referrer();
        
        if ($referrer && strpos($referrer, 'auth/login') === false) 
        {
            Session::instance()->set('login_redirect', $referrer);
        }

        if ($this->request->method() === Request::POST)
        {
            $username = $this->request->post('username');
            $password = $this->request->post('password');
            $remember = (bool) $this->request->post('remember');

            if (Auth::instance()->login($username, $password, $remember))
            {
                $user = Auth::instance()->get_user();
                if ($user->email_confirmed != 1)
                {
                    Auth::instance()->logout();
                    $errors[] = 'Пожалуйста, подтвердите ваш email.';
                }
                else
                {
                    $redirect_url = Session::instance()->get_once('login_redirect', '/');
                    throw HTTP_Exception::factory(302)->location($redirect_url);
                }
            }
            else
            {
                $errors[] = 'Неверное имя пользователя или пароль.';
            }
        }

        $yandex_login = NULL;
        
        // Проверяем наличие модуля и файла конфигурации для Яндекс.Авторизации
        if (array_key_exists('yandexauth', Kohana::modules()))
        {
            try 
            {
                $config = Kohana::$config->load('yandex_oauth');
                if ($config->get('client_id') && $config->get('redirect_uri'))
                {
                    $yandex_login = View::factory('yandexauth/login')
                        ->set('client_id', $config['client_id'])
                        ->set('redirect_uri', $config['redirect_uri'])
                        ->set('token_page_origin', $config['token_page_origin']);

                    $this->_scripts[] = array('src' => 'https://yastatic.net/s3/passport-sdk/autofill/v1/sdk-suggest-with-polyfills-latest.js');
                    
                    $this->template->title = 'Вход';
        			$this->template->content = $yandex_login;
                }
            }
            catch (Exception $e)
            {
                // Если конфиг не найден или ошибка инициализации, yandex_login останется NULL
            }
        }
        else
        {
           	$this->template->title = 'Вход';
            $this->template->content = View::factory('auth/login')
                ->bind('errors', $errors); 
        }

        
    }

    public function action_logout()
    {
        // Выход из учётной записи завершает и демо-режим стенда (erp811).
        // Иначе посетитель остаётся с cookie стенда: он закрыт от POST, и
        // следующий вход в систему отвечает 403 вместо страницы входа.
        if (class_exists('Service_Erp811_Demo'))
        {
            try
            {
                $demo = Service_Erp811_Demo::instance();

                if ($demo->active())
                {
                    $demo->leave();
                }
            }
            catch (Exception $e)
            {
                // Стенд — вспомогательный контур: его поломка не мешает выйти.
                Kohana::$log->add(Log::WARNING, 'erp811: демо-режим не завершён при выходе — :error', array(
                    ':error' => $e->getMessage(),
                ));
            }
        }

        Auth::instance()->logout();
        throw HTTP_Exception::factory(302)->location('/');
    }

    public function action_register()
    {
        if (Auth::instance()->logged_in())
        {
            throw HTTP_Exception::factory(302)->location('/');
        }

        $errors = array();

        if ($this->request->method() === Request::POST)
        {
            try 
            {
                $user = ORM::factory('User');
                $post = Validation::factory($this->request->post())
                    ->rule('username', 'not_empty')->rule('username', 'min_length', array(':value', 4))->rule('username', 'alpha_dash')
                    ->rule('email', 'not_empty')->rule('email', 'email')
                    ->rule('password', 'not_empty')->rule('password', 'min_length', array(':value', 6))
                    ->rule('password_confirm', 'matches', array(':validation', 'password', 'password_confirm'))
                    ->rule('terms', 'not_empty');

                if ($post->check())
                {
                    $user->values($post->as_array(), array('username', 'email', 'password'));
                    $user->email_confirmed = 0;
                    $user->confirmation_token = Text::random('alnum', 64);
                    $user->save();
                    $user->add('roles', ORM::factory('Role', array('name' => 'login')));

                    $link = URL::site('auth/confirm_email/' . $user->confirmation_token, TRUE);
                    
                    try {
                        $body = View::factory('auth/confirm_registration_email')
                            ->set('username', $user->username)
                            ->set('confirmation_link', $link)
                            ->render();
                    } catch (Exception $e) {
                        $body = "Здравствуйте, {$user->username}! Для подтверждения регистрации перейдите по ссылке: {$link}";
                    }

                    Email::send($user->email, 'info@email.ru', 'Подтверждение регистрации', $body, TRUE);
                    
                    throw HTTP_Exception::factory(302)->location('auth/success_registration?email=' . urlencode($user->email));
                }
                else
                {
                    $errors = $post->errors('auth');
                }
            }
            catch (ORM_Validation_Exception $e) { $errors = $e->errors('models'); }
            catch (HTTP_Exception $e) { throw $e; }
            catch (Exception $e) { $errors[] = 'Ошибка при регистрации: ' . $e->getMessage(); }
        }

        $this->template->title = 'Регистрация';
        $this->template->content = View::factory('auth/register')
            ->bind('errors', $errors)
            ->set('post', $this->request->post());
    }

    public function action_success_registration()
    {
        $this->template->title = 'Регистрация завершена';
        $this->template->content = View::factory('auth/success_registration')
            ->set('email', $this->request->query('email'));
    }

    public function action_confirm_email()
    {
        $token = $this->request->param('id');
        if (!$token) throw HTTP_Exception::factory(302)->location('auth/login');

        $user = ORM::factory('User', array('confirmation_token' => $token));

        if ($user->loaded())
        {
            $user->email_confirmed = 1;
            $user->confirmation_token = NULL;
            $user->save();
            
            Auth::instance()->force_login($user);
            throw HTTP_Exception::factory(302)->location('auth/profile');
        }
        
        $this->template->title = 'Ошибка подтверждения';
        $this->template->content = View::factory('auth/error')->set('message', 'Ссылка недействительна или уже использована.');
    }

    public function action_forgot_password()
    {
        $errors = array();
        $success = FALSE;

        if ($this->request->method() === Request::POST)
        {
            $email = $this->request->post('email');
            $user = ORM::factory('User', array('email' => $email));
            
            if ($user->loaded())
            {
                $token = Text::random('alnum', 32);
                $user->reset_token = $token;
                $user->reset_expires = time() + 3600; 
                $user->save();
                
                $link = URL::site('auth/reset_password/' . $token, TRUE);
                
                try {
                    $body = View::factory('emails/reset_password')
                        ->set('username', $user->username)
                        ->set('reset_link', $link)
                        ->render();
                } catch (Exception $e) {
                    $body = "Для сброса пароля перейдите по ссылке: $link";
                }

                Email::send($user->email, 'info@mlcode.ru', 'Сброс пароля', $body, TRUE);
                $success = TRUE;
            }
            else
            {
                $errors[] = 'Пользователь с таким email не найден.';
            }
        }
        
        $this->template->title = 'Восстановление пароля';
        $this->template->content = View::factory('auth/forgot_password')
            ->bind('errors', $errors)
            ->set('success', $success);
    }

    public function action_reset_password()
    {
        $token = $this->request->param('id');
        $user = ORM::factory('User', array('reset_token' => $token));

        if (!$user->loaded() || $user->reset_expires < time()) 
        {
            $this->template->title = 'Ошибка сброса пароля';
            $this->template->content = View::factory('auth/error')->set('message', 'Ссылка истекла или неверна.');
            return;
        }

        $errors = array();
        if ($this->request->method() === Request::POST)
        {
            $password = $this->request->post('password');
            $confirm = $this->request->post('password_confirm');

            if ($password === $confirm && strlen($password) >= 6)
            {
                $user->password = $password;
                $user->reset_token = NULL;
                $user->reset_expires = NULL;
                $user->save();
                
                throw HTTP_Exception::factory(302)->location('auth/login?reset=success');
            }
            else
            {
                $errors[] = 'Пароли не совпадают или слишком короткие (минимум 6 символов).';
            }
        }
        
        $this->template->title = 'Новый пароль';
        $this->template->content = View::factory('auth/reset_password')->bind('errors', $errors);
    }

    public function action_profile()
    {
        if (!Auth::instance()->logged_in('login')) throw HTTP_Exception::factory(302)->location('auth/login');

        $user = Auth::instance()->get_user();
        
        $this->template->title = 'Мой Профиль';
        $this->template->content = View::factory('auth/profile')
            ->set('user', $user);
    }

    public function action_index()
    {
        if (!Auth::instance()->logged_in('admin')) throw HTTP_Exception::factory(302)->location('auth/profile');
        
        $this->template->title = 'Управление пользователями';
        $this->template->content = View::factory('auth/index')
            ->set('users', ORM::factory('User')->find_all());
    }
}