<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Генератор и нормализатор мастер-кодов (Code Generator).
 *
 * Отвечает за:
 *   - нормализацию master_code согласно политике типа (x-mdm.code_policy);
 *   - проверку соответствия шаблону (pattern);
 *   - авто-выделение следующего кода (allocate) при policy mode=auto.
 *
 * Политика читается из JSON-схемы типа (types.schema → x-mdm.code_policy).
 * По умолчанию: { "mode": "manual", "normalize": "upper_trim" }.
 *
 * Генерация кода из UUID запрещена (см. manual_for_file.md §D).
 *
 * @package Erp230
 * @version 1.0.0
 */
class Service_Erp230_CodeGenerator {

	/**
	 * Политика по умолчанию, если в схеме типа нет x-mdm.code_policy.
	 */
	const DEFAULT_POLICY = [
		'mode'      => 'manual',
		'normalize' => 'upper_trim',
	];

	/**
	 * @var Service_Erp230_CodeGenerator
	 */
	protected static $_instance;

	/**
	 * Singleton instance
	 *
	 * @return Service_Erp230_CodeGenerator
	 */
	public static function instance()
	{
		if (self::$_instance === NULL)
		{
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	protected function __construct() {}

	/**
	 * Нормализация кода согласно политике.
	 *
	 * Режимы normalize:
	 *   - upper_trim (default): trim + схлопывание внутренних пробелов + mb_strtoupper;
	 *   - trim: только trim;
	 *   - nfkc: нормализация Unicode NFKC + trim + upper (если доступен Normalizer);
	 *   - none / raw: без изменений (только приведение к строке).
	 *
	 * @param  mixed $code
	 * @param  array $policy
	 * @return string
	 */
	public function normalize($code, array $policy = [])
	{
		if ($code === NULL)
		{
			return '';
		}

		$code = (string) $code;
		$mode = Arr::get($policy, 'normalize', 'upper_trim');

		switch ($mode)
		{
			case 'none':
			case 'raw':
				return $code;

			case 'trim':
				return trim($code);

			case 'nfkc':
				if (class_exists('Normalizer'))
				{
					$normalized = Normalizer::normalize($code, Normalizer::FORM_KC);
					if ($normalized !== FALSE)
					{
						$code = $normalized;
					}
				}
				return $this->_upper_trim($code);

			case 'upper_trim':
			default:
				return $this->_upper_trim($code);
		}
	}

	/**
	 * Внутренняя нормализация upper_trim:
	 * trim → схлопывание внутренних пробелов → mb_strtoupper.
	 *
	 * @param  string $code
	 * @return string
	 */
	protected function _upper_trim($code)
	{
		$code = trim($code);
		$code = preg_replace('/\s+/u', ' ', $code);

		return function_exists('mb_strtoupper')
			? mb_strtoupper($code, 'UTF-8')
			: strtoupper($code);
	}

	/**
	 * Проверка соответствия кода шаблону из политики.
	 * Поддерживается:
	 *   - policy['pattern'] как regex (в разделителях), либо
	 *   - как простой шаблон с '#' (цифра) и '?' (любой символ).
	 * Если pattern не задан — считается валидным.
	 *
	 * @param  string $code
	 * @param  array  $policy
	 * @return bool
	 */
	public function validate_pattern($code, array $policy)
	{
		$pattern = Arr::get($policy, 'pattern');

		if (empty($pattern))
		{
			return TRUE;
		}

		$code = (string) $code;

		// Готовое regex-выражение в разделителях (например, "/^MAT-\d+$/").
		if ($this->_is_regex($pattern))
		{
			return (bool) preg_match($pattern, $code);
		}

		// Простой шаблон: '#' → цифра, '?' → любой символ, прочее — литералы.
		$regex = '/^'.$this->_simple_pattern_to_regex($pattern).'$/u';

		return (bool) preg_match($regex, $code);
	}

	/**
	 * Является ли строка готовым regex в разделителях.
	 *
	 * @param  string $pattern
	 * @return bool
	 */
	protected function _is_regex($pattern)
	{
		if (strlen($pattern) < 2)
		{
			return FALSE;
		}

		$delim = $pattern[0];

		if ( ! in_array($delim, ['/', '#', '~', '@', '%'], TRUE))
		{
			return FALSE;
		}

		return (strrpos($pattern, $delim) > 0);
	}

	/**
	 * Преобразование простого шаблона в regex.
	 *
	 * @param  string $pattern
	 * @return string
	 */
	protected function _simple_pattern_to_regex($pattern)
	{
		$out = '';
		$len = mb_strlen($pattern, 'UTF-8');

		for ($i = 0; $i < $len; $i++)
		{
			$ch = mb_substr($pattern, $i, 1, 'UTF-8');

			if ($ch === '#')
			{
				$out .= '\d';
			}
			elseif ($ch === '?')
			{
				$out .= '.';
			}
			else
			{
				$out .= preg_quote($ch, '/');
			}
		}

		return $out;
	}

	/**
	 * Выделить следующий код для комбинации type + org.
	 * Работает только при policy mode=auto.
	 *
	 * Требует таблицы code_series (см. Model_Erp230_CodeSeries).
	 * При отсутствии инфраструктуры серий — бросает исключение.
	 *
	 * @param  string     $type_id
	 * @param  string     $org_id
	 * @param  array|NULL $policy
	 * @return string
	 * @throws Kohana_Exception
	 */
	public function allocate($type_id, $org_id, ?array $policy = NULL)
	{
		if ($policy === NULL)
		{
			$policy = $this->policy_for_type($type_id);
		}

		if (Arr::get($policy, 'mode') !== 'auto')
		{
			throw new Kohana_Exception('Auto code allocation requires policy mode=auto (type: :t)', [
				':t' => $type_id,
			]);
		}

		// Инфраструктура серий (итерация 4). Если модель/таблица недоступна —
		// явно сообщаем, что авто-код не сконфигурирован.
		if ( ! class_exists('Model_Erp230_CodeSeries'))
		{
			throw new Kohana_Exception('Auto code not configured: Model_Erp230_CodeSeries is unavailable');
		}

		$series = new Model_Erp230_CodeSeries();
		$seq    = $series->next_value($type_id, $org_id);

		$prefix = Arr::get($policy, 'prefix');
		if ($prefix === NULL OR $prefix === '')
		{
			// Фолбэк-префикс из type/org для читаемости кода.
			$prefix = strtoupper($type_id).'-'.strtoupper($org_id);
		}

		$pad = (int) Arr::get($policy, 'pad', 6);
		$pad = max($pad, 1);

		$number = str_pad((string) $seq, $pad, '0', STR_PAD_LEFT);

		$code = rtrim($prefix, '-').'-'.$number;

		return $this->normalize($code, $policy);
	}

	/**
	 * Получить политику кодирования для типа.
	 * Читает x-mdm.code_policy из схемы типа; при отсутствии — DEFAULT_POLICY.
	 *
	 * @param  string $type_id
	 * @return array
	 */
	public function policy_for_type($type_id)
	{
		$default = self::DEFAULT_POLICY;

		if (empty($type_id))
		{
			return $default;
		}

		try
		{
			$type = ORM::factory('Erp230_Type', $type_id);

			if ( ! $type->loaded())
			{
				return $default;
			}

			$schema = $this->_schema_array($type);
			$x_mdm  = Arr::get($schema, 'x-mdm', []);
			$policy = Arr::get($x_mdm, 'code_policy', []);

			if ( ! is_array($policy) OR empty($policy))
			{
				return $default;
			}

			// Слияние с дефолтами: заданные ключи переопределяют.
			return Arr::merge($default, $policy);
		}
		catch (Exception $e)
		{
			return $default;
		}
	}

	/**
	 * Извлечение schema типа как массива.
	 * Поддерживает метод schema_array() модели, если он реализован,
	 * иначе декодирует поле schema самостоятельно.
	 *
	 * @param  Model_Erp230_Type $type
	 * @return array
	 */
	protected function _schema_array(Model_Erp230_Type $type)
	{
		if (method_exists($type, 'schema_array'))
		{
			$arr = $type->schema_array();
			return is_array($arr) ? $arr : [];
		}

		$schema = $type->schema;

		if (is_array($schema))
		{
			return $schema;
		}

		if (is_string($schema) AND $schema !== '')
		{
			$decoded = json_decode($schema, TRUE);
			return (json_last_error() === JSON_ERROR_NONE AND is_array($decoded)) ? $decoded : [];
		}

		return [];
	}
}