<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Создание и обновление учётных записей из командной строки.
 *
 *   bin/console auth:user --email=admin@promerp.local --password=secret --username=admin --admin=1
 *   bin/console auth:user --email=admin@promerp.local --password=newsecret   # смена пароля
 *   bin/console auth:user --email=user@promerp.local --password=secret --role=manager
 *
 * Пароль хешируется фильтром модели (Model_Auth_User::filters) с ключом из
 * application/config/auth.php (переменная окружения AUTH_HASH_KEY).
 *
 * @package Minion
 */
class Task_Auth_User extends Minion_Task {

    protected $_options = [
        'email'    => NULL,
        'password' => NULL,
        'username' => NULL,
        'admin'    => NULL,
        'role'     => NULL,
        'activate' => TRUE,
    ];

    protected function _execute(?array $options = NULL)
    {
        $email    = $this->_value($options, 'email');
        $password = $this->_value($options, 'password');
        $username = $this->_value($options, 'username');
        $admin    = $this->_flag($options, 'admin');
        $role     = $this->_value($options, 'role');
        $activate = $this->_flag($options, 'activate');

        if ( ! $email OR ! $password)
        {
            echo 'Укажите --email и --password'.PHP_EOL;
            echo 'Пример: bin/console auth:user --email=admin@promerp.local --password=secret --admin=1'.PHP_EOL;

            return;
        }

        $is_admin = (bool) $admin;

        if ( ! $username)
        {
            $username = preg_replace('/[^a-z0-9_\-]/i', '_', strstr($email, '@', TRUE) ?: $email);
        }

        $user = ORM::factory('User')->where('email', '=', $email)->find();

        $created = FALSE;

        if ( ! $user->loaded())
        {
            $user = ORM::factory('User');
            $created = TRUE;
        }

        $user->values([
            'email'    => $email,
            'username' => $username,
            'password' => $password,
        ], ['email', 'username', 'password']);

        if ($activate)
        {
            $user->email_confirmed = 1;
            $user->confirmation_token = NULL;
        }

        $user->save();

        // Роли
        $roles = ['login'];

        if ($is_admin)
        {
            $roles[] = 'admin';
        }

        if ($role)
        {
            foreach (explode(',', $role) as $extra)
            {
                $roles[] = trim($extra);
            }
        }

        $roles = array_unique(array_filter($roles));

        foreach ($roles as $role_name)
        {
            $role_model = ORM::factory('Role', ['name' => $role_name]);

            if ( ! $role_model->loaded())
            {
                $role_model = ORM::factory('Role');
                $role_model->name = $role_name;
                $role_model->description = 'Создана CLI-задачей auth:user';
                $role_model->save();
                echo '  + роль '.$role_name.' создана'.PHP_EOL;
            }

            if ( ! $user->has('roles', $role_model))
            {
                $user->add('roles', $role_model);
            }
        }

        echo sprintf('%s пользователь #%d %s (%s), роли: %s%s',
            $created ? 'Создан' : 'Обновлён',
            $user->id,
            $user->username,
            $user->email,
            implode(', ', $roles),
            PHP_EOL);
    }

}
