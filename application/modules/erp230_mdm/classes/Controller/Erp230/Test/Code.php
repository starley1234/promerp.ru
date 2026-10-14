<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контроллер тестирования работы с кодами (Code Generator & Policy).
 * Проверяет нормализацию, валидацию по шаблонам, авто-генерацию и API.
 *
 * @package Erp230
 */
class Controller_Erp230_Test_Code extends Controller_Erp230_Main {

	/**
	 * @var Service_Erp230_CodeGenerator
	 */
	protected $_gen;

	public function before()
	{
		parent::before();
		$this->_gen = Service_Erp230_CodeGenerator::instance();
	}

	/**
	 * Запуск тестов.
	 * URL: /erp230_testcode
	 */
	public function action_index()
	{
		$results = [
			'normalization' => $this->_test_normalization(),
			'validation'    => $this->_test_validation(),
			'policy_read'   => $this->_test_policy_read(),
			'allocation'    => $this->_test_allocation(),
			'api_registry'  => $this->_test_api_registry(),
			'recommendations' => $this->_get_updated_recommendations(),
		];

		$this->_json_response($results);
	}

	/**
	 * Тест логики нормализации.
	 */
	protected function _test_normalization()
	{
		return [
			'upper_trim_default' => $this->_gen->normalize('  part-123  ') === 'PART-123',
			'internal_spaces'    => $this->_gen->normalize('ABC   123') === 'ABC 123',
			'raw_mode'           => $this->_gen->normalize('  low  ', ['normalize' => 'raw']) === '  low  ',
			'trim_only'          => $this->_gen->normalize('  Low  ', ['normalize' => 'trim']) === 'Low',
		];
	}

	/**
	 * Тест валидации по паттернам.
	 */
	protected function _test_validation()
	{
		$simple_policy = ['pattern' => 'RES-####'];
		$regex_policy  = ['pattern' => '/^CAP-[A-Z]{2}$/'];

		return [
			'simple_match'    => $this->_gen->validate_pattern('RES-1234', $simple_policy),
			'simple_fail'     => ! $this->_gen->validate_pattern('RES-123A', $simple_policy),
			'regex_match'     => $this->_gen->validate_pattern('CAP-XY', $regex_policy),
			'regex_fail'      => ! $this->_gen->validate_pattern('CAP-12', $regex_policy),
			'empty_is_valid'  => $this->_gen->validate_pattern('ANYTHING', []),
		];
	}

	/**
	 * Тест чтения политики из БД (типы из миграции 002).
	 */
	protected function _test_policy_read()
	{
		$policy = $this->_gen->policy_for_type('resistor');
		
		return [
			'fallback_to_default' => ($policy['mode'] === 'manual'),
			'type_not_found'      => ($this->_gen->policy_for_type('non_existent')['mode'] === 'manual'),
		];
	}

	/**
	 * Тест авто-генерации (allocate).
	 */
	protected function _test_allocation()
	{
		$policy = [
			'mode'   => 'auto',
			'prefix' => 'TEST',
			'pad'    => 4
		];

		try 
		{
			$code = $this->_gen->allocate('test_part', 'TEST_LAB', $policy);
			return ['ok' => TRUE, 'generated' => $code];
		}
		catch (Exception $e)
		{
			return ['ok' => FALSE, 'reason' => $e->getMessage()];
		}
	}

	/**
	 * Тест доступности новых API методов реестра кодов.
	 */
	protected function _test_api_registry()
	{
		return [
			'model_code_exists'   => class_exists('Model_Erp230_Code'),
			'model_series_exists' => class_exists('Model_Erp230_CodeSeries'),
			'api_controller'      => class_exists('Controller_Erp230_Api_Codes'),
			'uniqueness_check'    => method_exists('Model_Erp230_Code', 'code_exists'),
		];
	}

	/**
	 * Обновленные рекомендации по развитию системы кодирования.
	 */
	protected function _get_updated_recommendations()
	{
		return [
			'status' => 'API реализовано, требуется интеграция в UI',
			'next_steps' => [
				'UI Integration' => 'Добавить в форму создания объекта вызов /erp230_api_codes/next при выборе типа с авто-кодом',
				'Live Validation' => 'Подключить /erp230_api_codes/validate на событие blur поля master_code для мгновенной проверки уникальности',
				'Bulk Generation' => 'Реализовать метод для массовой генерации кодов при импорте (Service_Erp230_CodeGenerator::allocate_batch)',
				'Collision Handling' => 'Добавить логику автоматического разрешения коллизий (суффиксы -REV1, -A) в политику нормализации'
			],
			'security_note' => 'Проверить RLS политики для таблицы object_codes, чтобы исключить утечку кодов между организациями'
		];
	}
}