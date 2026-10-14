<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Yandexauth extends Controller {

    protected $client_id;
    protected $redirect_uri;
    protected $token_page_origin;

    public function before()
    {
        parent::before();

        // Загружаем конфигурацию Яндекс OAuth
        $config = Kohana::$config->load('yandex_oauth');
        $this->client_id = $config['client_id'];
        $this->redirect_uri = $config['redirect_uri'];
        $this->token_page_origin = $config['token_page_origin'];

        // Простая проверка, что конфигурация заполнена
        if (empty($this->client_id) || empty($this->redirect_uri) || empty($this->token_page_origin)) {
            throw new Kohana_Exception('Yandex OAuth configuration is incomplete. Please check application/config/yandex_oauth.php');
        }
    }

    /**
     * Страница для инициации авторизации через Яндекс.
     */
    public function action_login()
    {
        // Определяем, откуда пришел пользователь
        $referrer = $this->request->referrer();
        // Если реферер есть и это не страница входа, сохраняем его в сессии
        if ($referrer && strpos($referrer, URL::site('auth/login')) === false) {
            Session::instance()->set('login_redirect', $referrer);
        }
        
        $view = View::factory('yandexauth/login')
            ->set('client_id', $this->client_id)
            ->set('redirect_uri', $this->redirect_uri)
            ->set('token_page_origin', $this->token_page_origin);

        $this->response->body($view);
    }

    /**
     * Вспомогательная страница (Redirect URI) для приема OAuth-токена.
     */
    public function action_callback()
    {
        $view = View::factory('yandexauth/callback')
            ->set('token_page_origin', $this->token_page_origin);

        $this->response->body($view);
    }

    /**
     * Действие для получения информации о пользователе после получения токена.
     */
    public function action_get_user_info()
    {
        // Устанавливаем заголовок для JSON ответа
        $this->response->headers('Content-Type', 'application/json');

        // Получаем OAuth-токен из POST-запроса
        $oauth_token = $this->request->post('oauth_token');

        if (empty($oauth_token)) {
            $this->response->body(json_encode(array('status' => 'error', 'message' => 'OAuth token is missing.')));
            return;
        }

        // URL для запроса информации о пользователе
        $yandex_info_url = 'https://login.yandex.ru/info';

        $options = array(
            'http' => array(
                'header'  => "Authorization: OAuth " . $oauth_token . "\r\n",
                'method'  => 'GET',
            ),
        );
        $context  = stream_context_create($options);
        $response = @file_get_contents($yandex_info_url . '?format=json', false, $context);

        if ($response === FALSE) {
            $this->response->body(json_encode(array('status' => 'error', 'message' => 'Failed to fetch user info from Yandex.')));
            return;
        }

        $user_info = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->response->body(json_encode(array('status' => 'error', 'message' => 'Invalid JSON response from Yandex.')));
            return;
        }

        try {
            $auth = Auth::instance();
            $username = 'yandex_' . $user_info['id']; 
            $email = Arr::get($user_info, 'default_email');

            // 1. Работа с сущностью User
            $user = ORM::factory('User', array('username' => $username));

            if (!$user->loaded()) {
                if (!empty($email)) {
                    $user = ORM::factory('User', array('email' => $email));
                }
                
                if (!$user->loaded()) {
                    $password = Auth::instance()->hash_password(uniqid(rand(), true)); 
                    $user->username = $username;
                    $user->email = $email ?: ($username . '@yandex.local');
                    $user->password = $password;
                    $user->email_confirmed = 1;
                    $user->save();
                    $user->add('roles', ORM::factory('Role', array('name' => 'login')));
                }
            }

            // 2. Работа с сущностью User_Oauth (через прокси-модель)
            $user_oauth = ORM::factory('User_Oauth')
                ->where('provider', '=', 'yandex')
                ->and_where('provider_id', '=', $user_info['id'])
                ->find();

            if (!$user_oauth->loaded()) {
                $user_oauth->user_id = $user->id;
                $user_oauth->provider = 'yandex';
                $user_oauth->provider_id = $user_info['id'];
            }
            
            $user_oauth->access_token = $oauth_token;
            $user_oauth->first_name = Arr::get($user_info, 'first_name');
            $user_oauth->last_name = Arr::get($user_info, 'last_name');
            $user_oauth->email = Arr::get($user_info, 'default_email');
            $user_oauth->sex = Arr::get($user_info, 'sex');
            $user_oauth->birthday = Arr::get($user_info, 'birthday');
            $user_oauth->avatar_id = Arr::get($user_info, 'avatar_id');
            $user_oauth->save();

            // Авторизуем пользователя
            $auth->force_login($user);

            Session::instance()->set('yandex_user_info', $user_info);
            Session::instance()->set('yandex_oauth_token', $oauth_token);

            $this->response->body(json_encode(array(
                'status' => 'success', 
                'user_info' => $user_info, 
                'redirect' => URL::site('yandexauth/profile')
            )));

        } catch (ORM_Validation_Exception $e) {
            $errors = $e->errors('models');
            $this->response->body(json_encode(array('status' => 'error', 'message' => 'Ошибка валидации: ' . implode(', ', $errors))));
        } catch (Exception $e) {
            $this->response->body(json_encode(array('status' => 'error', 'message' => 'Ошибка: ' . $e->getMessage())));
        }
    }

    public function action_profile()
    {
        if (!Auth::instance()->logged_in()) {
            $this->redirect('auth/login');
        }
      
        $redirect_url = Session::instance()->get('login_redirect');
        Session::instance()->delete('login_redirect');
        $this->redirect($redirect_url ?: '/');
    }

    public function action_logout()
    {
        Session::instance()->delete('yandex_user_info');
        Session::instance()->delete('yandex_oauth_token');
        Auth::instance()->logout();
        $this->redirect('yandexauth/login');
    }
}