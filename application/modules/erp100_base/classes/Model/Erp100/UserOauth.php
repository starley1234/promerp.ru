<?php defined('SYSPATH') or die('No direct script access.');

class Model_Erp100_UserOauth extends ORM {

    protected $_table_name = 'users_oauth';
    protected $_primary_key = 'id';

    protected $_belongs_to = array(
        'user' => array(
            'model'       => 'User',
            'foreign_key' => 'user_id',
        ),
    );

    /**
     * Правила валидации для полей модели.
     *
     * @return array
     */
    public function rules()
    {
        return array(
            'user_id' => array(
                array('not_empty'),
                array('digit'),
            ),
            'provider' => array(
                array('not_empty'),
                array('max_length', array(':value', 50)),
            ),
            'provider_id' => array(
                array('not_empty'),
                array('max_length', array(':value', 255)),
            ),
            'access_token' => array(
                array('not_empty'),
            ),
            'expires_in' => array(
                array('digit'),
            ),
            'first_name' => array(
                array('max_length', array(':value', 255)),
            ),
            'last_name' => array(
                array('max_length', array(':value', 255)),
            ),
            'email' => array(
                array('email'),
                array('max_length', array(':value', 254)),
            ),
            'sex' => array(
                array('max_length', array(':value', 10)),
            ),
            'avatar_id' => array(
                array('max_length', array(':value', 255)),
            ),
        );
    }

    /**
     * Метки полей.
     *
     * @return array
     */
    public function labels()
    {
        return array(
            'user_id'      => 'ID пользователя',
            'provider'     => 'Провайдер',
            'provider_id'  => 'ID провайдера',
            'access_token' => 'Токен доступа',
            'refresh_token'=> 'Токен обновления',
            'expires_in'   => 'Срок действия',
            'created_at'   => 'Дата создания',
            'updated_at'   => 'Дата обновления',
            'first_name'   => 'Имя',
            'last_name'    => 'Фамилия',
            'email'        => 'Email',
            'sex'          => 'Пол',
            'birthday'     => 'Дата рождения',
            'avatar_id'    => 'ID аватара',
        );
    }

    /**
     * Валидация уникальности пары provider + provider_id.
     *
     * @param  Validation $validation
     * @return Validation
     */
    public function unique_key_validation_rules(Validation $validation)
    {
        $validation->rule('provider_id', array($this, 'unique_provider_id'), array(':validation', ':field'));
        return parent::unique_key_validation_rules($validation);
    }

    /**
     * Проверка уникальности связки провайдера и его ID.
     *
     * @param Validation $validation
     * @param string $field
     * @return void
     */
    public function unique_provider_id(Validation $validation, $field)
    {
        $provider = $this->provider;
        
        // Если провайдер не задан в модели, пытаемся взять из контекста валидации
        if (empty($provider) && isset($validation['provider']))
        {
            $provider = $validation['provider'];
        }

        $query = DB::select(array(DB::expr('COUNT(*)'), 'total'))
            ->from($this->_table_name)
            ->where('provider', '=', $provider)
            ->and_where('provider_id', '=', $validation[$field]);

        if ($this->loaded())
        {
            $query->and_where($this->_primary_key, '!=', $this->pk());
        }

        if ($query->execute($this->_db)->get('total') > 0)
        {
            $validation->error($field, 'unique_provider_id', array($validation[$field]));
        }
    }
}