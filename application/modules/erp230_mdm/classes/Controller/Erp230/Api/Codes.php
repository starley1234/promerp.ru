<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контроллер для работы с реестром уникальных кодов объектов.
 * 
 * Предоставляет API для проверки уникальности бизнес-ключей,
 * быстрого поиска UUID объектов, а также инструменты нормализации и валидации.
 */
class Controller_Erp230_Api_Codes extends Controller_Erp230_Api {

	/**
	 * GET /erp230_api_codes/check?master_code=...&type_id=...&org_id=...
	 * Проверка существования кода в реестре.
	 */
	public function action_check()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.view');

			$master_code = $this->request->query('master_code');
			$type_id     = $this->request->query('type_id');
			$org_id      = $this->request->query('org_id');

			if ( ! $master_code OR ! $type_id OR ! $org_id)
				throw new Kohana_Exception('Required parameters: master_code, type_id, org_id', NULL, 400);

			$exists = Model_Erp230_Code::code_exists($master_code, $type_id, $org_id);

			return [
				'status' => 'ok',
				'exists' => $exists,
				'query'  => [
					'master_code' => $master_code,
					'type_id'     => $type_id,
					'org_id'      => $org_id
				]
			];
		});
	}

	/**
	 * GET /erp230_api_codes/resolve?master_code=...&type_id=...&org_id=...
	 * Получение UUID объекта по его бизнес-ключам.
	 */
	public function action_resolve()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.view');

			$master_code = $this->request->query('master_code');
			$type_id     = $this->request->query('type_id');
			$org_id      = $this->request->query('org_id');

			if ( ! $master_code OR ! $type_id OR ! $org_id)
				throw new Kohana_Exception('Required parameters: master_code, type_id, org_id', NULL, 400);

			$object_id = Model_Erp230_Code::get_object_id($master_code, $type_id, $org_id);

			if ( ! $object_id)
				throw new Kohana_Exception('Object not found for given codes', NULL, 404);

			return [
				'status'    => 'ok',
				'object_id' => $object_id
			];
		});
	}

	/**
	 * GET /erp230_api_codes/preview?code=...&type_id=...
	 * Предпросмотр нормализации кода перед сохранением.
	 */
	public function action_preview()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.view');

			$code    = $this->request->query('code');
			$type_id = $this->request->query('type_id');

			if ($code === NULL OR ! $type_id)
				throw new Kohana_Exception('Required parameters: code, type_id', NULL, 400);

			$generator = Service_Erp230_CodeGenerator::instance();
			$policy    = $generator->policy_for_type($type_id);
			$normalized = $generator->normalize($code, $policy);

			return [
				'status'     => 'ok',
				'original'   => $code,
				'normalized' => $normalized,
				'policy'     => $policy
			];
		});
	}

	/**
	 * GET /erp230_api_codes/next?type_id=...&org_id=...
	 * Получение следующего свободного кода для типов с mode=auto.
	 */
	public function action_next()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.edit');

			$type_id = $this->request->query('type_id');
			$org_id  = $this->request->query('org_id');

			if ( ! $type_id OR ! $org_id)
				throw new Kohana_Exception('Required parameters: type_id, org_id', NULL, 400);

			$generator = Service_Erp230_CodeGenerator::instance();
			$next_code = $generator->allocate($type_id, $org_id);

			return [
				'status' => 'ok',
				'code'   => $next_code
			];
		});
	}

	/**
	 * GET /erp230_api_codes/policy?type_id=...
	 * Получение расшифрованной политики для конкретного типа.
	 */
	public function action_policy()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.view');

			$type_id = $this->request->query('type_id');
			if ( ! $type_id)
				throw new Kohana_Exception('Required parameter: type_id', NULL, 400);

			$policy = Service_Erp230_CodeGenerator::instance()->policy_for_type($type_id);

			return [
				'status' => 'ok',
				'type_id' => $type_id,
				'policy'  => $policy
			];
		});
	}

	/**
	 * POST /erp230_api_codes/validate
	 * Валидация кода на лету (проверка паттерна + уникальность).
	 */
	public function action_validate()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.view');

			$code    = $this->request->post('code');
			$type_id = $this->request->post('type_id');
			$org_id  = $this->request->post('org_id');

			if ($code === NULL OR ! $type_id OR ! $org_id)
				throw new Kohana_Exception('Required POST parameters: code, type_id, org_id', NULL, 400);

			$generator = Service_Erp230_CodeGenerator::instance();
			$policy    = $generator->policy_for_type($type_id);
			
			$normalized = $generator->normalize($code, $policy);
			$valid_pattern = $generator->validate_pattern($normalized, $policy);
			$exists = Model_Erp230_Code::code_exists($normalized, $type_id, $org_id);

			return [
				'status' => 'ok',
				'valid'  => ($valid_pattern AND ! $exists),
				'errors' => [
					'pattern' => ! $valid_pattern,
					'duplicate' => $exists
				],
				'normalized' => $normalized
			];
		});
	}

	/**
	 * GET /erp230_api_codes/list?limit=...&offset=...
	 * Получение списка всех зарегистрированных кодов.
	 */
	public function action_list()
	{
		$this->_handle(function () {
			$this->_require('mdm.object.view');

			$limit  = min(max((int) $this->request->query('limit') ?: 50, 1), 1000);
			$offset = max((int) $this->request->query('offset'), 0);

			$codes = ORM::factory('Erp230_Code')
				->limit($limit)
				->offset($offset)
				->find_all();

			$data = [];
			foreach ($codes as $c)
			{
				$data[] = [
					'master_code' => $c->master_code,
					'type_id'     => $c->type_id,
					'org_id'      => $c->org_id,
					'object_id'   => $c->object_id
				];
			}

			return [
				'status' => 'ok',
				'count'  => count($data),
				'data'   => $data
			];
		});
	}
}