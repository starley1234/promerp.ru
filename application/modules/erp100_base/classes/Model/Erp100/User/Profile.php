<?php defined('SYSPATH') or die('No direct script access.');

class Model_Erp100_User_Profile extends ORM {

	protected $_table_name = 'user_profiles';
	protected $_primary_key = 'user_id';

	protected $_belongs_to = array(
		'user' => array(
			'model'       => 'User',
			'foreign_key' => 'user_id',
		),
	);

	public function rules()
	{
		return array(
			'first_name'   => array(array('max_length', array(':value', 255))),
			'last_name'    => array(array('max_length', array(':value', 255))),
			'middle_name'  => array(array('max_length', array(':value', 255))),
			'phone_number' => array(array('max_length', array(':value', 20))),
			'address'      => array(array('max_length', array(':value', 255))),
			'city'         => array(array('max_length', array(':value', 100))),
			'country'      => array(array('max_length', array(':value', 100))),
			'gender'       => array(array('in_array', array(':value', array('male', 'female', 'other')))),
			'date_of_birth'=> array(array('date')),
		);
	}

	public function filters()
	{
		return array(
			'first_name'  => array(array('HTML::chars')),
			'last_name'   => array(array('HTML::chars')),
			'middle_name' => array(array('HTML::chars')),
			'address'     => array(array('HTML::chars')),
			'city'        => array(array('HTML::chars')),
			'country'     => array(array('HTML::chars')),
		);
	}
}