<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Комплексный smoke-тест модуля MDM (ERP230): НСИ → объект → консолидация →
 * BOM → аудит целостности → поиск.
 *
 * Маршрут: /erp230_test
 */
class Controller_Erp230_Test extends Controller_Erp230_Main {

    /**
     * Диагностический контроллер: в PRODUCTION отключён.
     */
    public function before()
    {
        parent::before();

        if (Kohana::$environment === Kohana::PRODUCTION)
        {
            throw new HTTP_Exception_403('Диагностический контроллер отключён в PRODUCTION');
        }
    }


	/** UUID объекта, созданного на шаге 2 (нужен последующим шагам). */
	protected $_oid;

	public function action_index()
	{
		$r = [];
		$r['step1_setup']         = $this->_safe([$this, '_setup']);
		$r['step2_object']        = $this->_safe([$this, '_create_object']);
		$this->_oid               = Arr::path($r, 'step2_object.id');
		$r['step3_consolidation'] = $this->_safe([$this, '_consolidation']);
		$r['step4_bom']           = $this->_safe([$this, '_bom']);
		$r['step5_integrity']     = $this->_safe([$this, '_integrity']);
		$r['step6_search']        = $this->_safe([$this, '_search']);

		if (Kohana::find_file('views', 'erp230/test_results'))
		{
			return $this->response->body(
				View::factory('erp230/test_results', ['results' => $r])
			);
		}

		$this->_json_response($r);
	}

	/** Единый перехватчик: любой шаг либо возвращает массив, либо кидает исключение. */
	protected function _safe($fn)
	{
		try
		{
			return $fn();
		}
		catch (Exception $e)
		{
			return [
				'status'  => 'error',
				'message' => $e->getMessage(),
				'line'    => $e->getLine(),
			];
		}
	}

	/** Объект должен существовать — иначе зависимый шаг не имеет смысла. */
	protected function _need_object()
	{
		if (empty($this->_oid))
			throw new Kohana_Exception('Объект не создан на шаге 2');

		return $this->_oid;
	}

	// --- Шаги ---------------------------------------------------------------

	/** Шаг 1: справочники (источники, оргструктура, тип изделия). */
	protected function _setup()
	{
		$sources = [
			'ai'  => ['name' => 'AI Engine',  'trust' => 30],
			'plm' => ['name' => 'PLM System', 'trust' => 90],
		];

		foreach ($sources as $id => $d)
		{
			$src = ORM::factory('Erp230_Source', $id);
			$src->id           = $id;
			$src->display_name = $d['name'];   // алиас -> name
			$src->trust_score  = $d['trust'];  // алиас -> trust
			$src->save();
		}

		$this->_ensure_org('HOLDING',  'Главный офис');
		$org = $this->_ensure_org('TEST_LAB', 'Тестовая лаборатория', 'HOLDING');

		$type = ORM::factory('Erp230_Type', 'test_part');
		if ( ! $type->loaded())
		{
			$type->id           = 'test_part';
			$type->display_name = 'Тестовая деталь';
			$type->save();
		}

		return ['status' => 'success', 'type_id' => 'test_part', 'org_path' => (string) $org->path];
	}

	protected function _ensure_org($id, $name, $parent = NULL)
	{
		$org = ORM::factory('Erp230_OrgUnit', $id);
		if ( ! $org->loaded())
		{
			$org->id        = $id;
			$org->name      = $name;
			$org->parent_id = $parent;
			$org->save();
		}
		return $org;
	}

	/** Шаг 2: мастер-запись (Golden Record). */
	protected function _create_object()
	{
		$obj = ORM::factory('Erp230_Object');
		$obj->type_id     = 'test_part';
		$obj->org_id      = 'TEST_LAB';
		$obj->master_code = 'TEST-OBJ-'.mt_rand(1000, 9999);
		$obj->state       = 'active';
		$obj->save();

		return ['status' => 'success', 'id' => $obj->id, 'code' => $obj->master_code];
	}

	/** Шаг 3: консолидация с проверкой Trust Score. */
	protected function _consolidation()
	{
		$oid  = $this->_need_object();
		$prop = ORM::factory('Erp230_Property');

		$res = [
			'res_ai_initial'  => $prop->upsert_safe($oid, 'weight', 10.5, 'ai',  'kg'), // ok
			'res_plm_update'  => $prop->upsert_safe($oid, 'weight', 10.2, 'plm', 'kg'), // ok (доверие выше)
			'res_ai_rejected' => $prop->upsert_safe($oid, 'weight', 99.9, 'ai',  'kg'), // rejected_low_trust
		];

		$current = ORM::factory('Erp230_Property')
			->where('object_id', '=', $oid)
			->where('key', '=', 'weight')
			->where('is_current', '=', TRUE)
			->find();

		return $res + [
			'final_value'  => $current->value,
			'final_source' => $current->source_id,
		];
	}

	/** Шаг 4: структура BOM и защита от циклов. */
	protected function _bom()
	{
		$parent = $this->_need_object();

		$child = ORM::factory('Erp230_Object');
		$child->type_id     = 'test_part';
		$child->org_id      = 'TEST_LAB';
		$child->master_code = 'SUB-'.mt_rand(1000, 9999);
		$child->save();

		// Корректная связь parent -> child.
		$link = ORM::factory('Erp230_Link');
		$link->values([
			'parent_id' => $parent,
			'child_id'  => $child->id,
			'link_type' => 'ebom',
			'qty'       => 2,
		])->save();

		// Обратная связь child -> parent должна быть отклонена как цикл.
		$cycle = ORM::factory('Erp230_Link');
		$cycle->values([
			'parent_id' => $child->id,
			'child_id'  => $parent,
			'link_type' => 'ebom',
			'qty'       => 1,
		]);

		$valid = $cycle->check();

		return [
			'link_created'       => $link->id,
			'circular_prevented' => ! $valid,
			'errors'             => $cycle->validation()->errors('models'),
		];
	}

	/** Шаг 5: снимки (Baseline) и целостность hash-chain. */
	protected function _integrity()
	{
		$oid     = $this->_need_object();
		$service = Service_Erp230_Consolidation::instance();

		$b1 = $service->create_snapshot($oid, 'admin', 'V1');
		ORM::factory('Erp230_Property')->upsert_safe($oid, 'color', 'red', 'plm');
		$b2 = $service->create_snapshot($oid, 'admin', 'V2');

		$model = ORM::factory('Erp230_Baseline');

		return [
			'v1_hash'      => $b1->snapshot_hash,
			'v2_hash'      => $b2->snapshot_hash,
			'v2_prev'      => $b2->prev_hash,
			'integrity_ok' => $model->is_chain_valid($oid),
			'chain_length' => count($model->verify_chain($oid)),
		];
	}

	/** Шаг 6: поиск по мастер-коду. */
	protected function _search()
	{
		$oid = $this->_need_object();
		$obj = ORM::factory('Erp230_Object', $oid);

		$found = ORM::factory('Erp230_Object')
			->where('master_code', '=', $obj->master_code)
			->find();

		return [
			'found_by_code' => $found->loaded(),
			'matched_id'    => $found->loaded() ? $found->id : NULL,
		];
	}
}