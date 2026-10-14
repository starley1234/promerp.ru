<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Перекрытие правил валидации ядра (application/classes/Valid.php подменяет
 * system/classes/Kohana/Valid.php в каскадной файловой системе Kohana).
 *
 * ЗАЧЕМ ЭТО НУЖНО
 * ---------------
 * Начиная с PHP 8.1 передача NULL в строковые параметры встроенных функций
 * считается устаревшей (E_DEPRECATED). Kohana по умолчанию превращает такое
 * сообщение в ErrorException — запрос падает в 500 на любом сохранении модели,
 * где необязательное поле осталось пустым.
 *
 * Правила ниже приводят значение к строке и считают пустое значение корректным:
 * проверка обязательности — задача правила not_empty, а не длины/формата.
 *
 * Файл намеренно повторяет сигнатуры ядра (совместимость со сторонним кодом).
 */
class Valid extends Kohana_Valid {

	/**
	 * Приводит значение к строке, не вызывая deprecation на NULL
	 * (в том числе для массивов и объектов, приведённых в ядре через array).
	 *
	 * @param   mixed   $value
	 * @return  string
	 */
	protected static function as_string($value)
	{
		return ($value === NULL OR $value === FALSE) ? '' : (string) $value;
	}

	/**
	 * Пустое значение формату не противоречит.
	 *
	 * @param   mixed   $value
	 * @return  boolean
	 */
	protected static function is_empty($value)
	{
		return ($value === NULL OR $value === '' OR $value === FALSE);
	}

	// -- Длина -----------------------------------------------------------------

	public static function min_length($value, $length)
	{
		if (static::is_empty($value))
			return TRUE;

		return UTF8::strlen(static::as_string($value)) >= $length;
	}

	public static function max_length($value, $length)
	{
		if (static::is_empty($value))
			return TRUE;

		return UTF8::strlen(static::as_string($value)) <= $length;
	}

	public static function exact_length($value, $length)
	{
		if (static::is_empty($value))
			return TRUE;

		$value = UTF8::strlen(static::as_string($value));

		if (is_array($length))
		{
			return in_array($value, array_map('intval', $length), TRUE);
		}

		return $value === (int) $length;
	}

	// -- Форматы ---------------------------------------------------------------

	public static function date($str)
	{
		$str = static::as_string($str);

		if ($str === '')
			return TRUE;

		return (strtotime($str) !== FALSE);
	}

	public static function digit($str, $utf8 = FALSE)
	{
		$str = static::as_string($str);

		if ($utf8 === TRUE)
			return (bool) preg_match('/^\pN++$/uD', $str);

		return (bool) preg_match('/^[0-9]+$/D', $str);
	}

	public static function numeric($str)
	{
		return parent::numeric(static::as_string($str));
	}

	public static function decimal($str, $places = 2, $digits = NULL)
	{
		return parent::decimal(static::as_string($str), $places, $digits);
	}

	public static function color($str)
	{
		return parent::color(static::as_string($str));
	}

	public static function phone($number, $lengths = NULL)
	{
		return parent::phone(static::as_string($number), $lengths);
	}

	public static function alpha($str, $utf8 = FALSE)
	{
		return parent::alpha(static::as_string($str), $utf8);
	}

	public static function alpha_numeric($str, $utf8 = FALSE)
	{
		return parent::alpha_numeric(static::as_string($str), $utf8);
	}

	public static function alpha_dash($str, $utf8 = FALSE)
	{
		return parent::alpha_dash(static::as_string($str), $utf8);
	}

	public static function url($url)
	{
		return parent::url(static::as_string($url));
	}

	public static function ip($ip, $allow_private = TRUE)
	{
		return parent::ip(static::as_string($ip), $allow_private);
	}

	public static function credit_card($number, $type = NULL)
	{
		return parent::credit_card(static::as_string($number), $type);
	}

	public static function luhn($number)
	{
		return parent::luhn(static::as_string($number));
	}

	public static function regex($value, $expression)
	{
		return parent::regex(static::as_string($value), $expression);
	}

	public static function equals($value, $required)
	{
		return parent::equals(static::as_string($value), $required);
	}

	public static function email($email, $strict = FALSE)
	{
		if (static::is_empty($email))
			return FALSE;

		return parent::email(static::as_string($email), $strict);
	}

	public static function email_domain($email)
	{
		return static::is_empty($email) ? FALSE : parent::email_domain(static::as_string($email));
	}

	public static function range($number, $min, $max, $step = NULL)
	{
		return parent::range(static::as_string($number), $min, $max, $step);
	}

	public static function matches($array, $field, $match)
	{
		$left  = isset($array[$field]) ? static::as_string($array[$field]) : '';
		$right = isset($array[$match]) ? static::as_string($array[$match]) : '';

		return ($left === $right);
	}

}
