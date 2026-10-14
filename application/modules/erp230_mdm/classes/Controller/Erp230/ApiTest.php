<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Функциональный тест REST API модуля MDM / Digital Twin.
 *
 * Маршрут: /erp230_apitest
 *
 * Исправления:
 *   - _attribute_history: проверка наличия actor_id (кто менял историю)
 *   - _baseline_list: исправлено ожидание деталей (snapshot vs snapshot_data)
 */
class Controller_Erp230_ApiTest extends Controller_Erp230_Main {

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


	/** Общий префикс API. */
	protected $_api = 'erp230_api';

	/** Сквозной контекст между шагами. */
	protected $_ctx = [];

	public function action_index()
	{
		$r = [];

		$r['0_driver_tx']    = $this->_probe_transactions();
		$r['1_setup']        = $this->_safe([$this, '_setup']);
		$r['2_create']       = $this->_safe([$this, '_create_objects']);
		$r['3_properties']   = $this->_safe([$this, '_properties']);
		$r['3b_attr_history']= $this->_safe([$this, '_attribute_history']);
		$r['4_structure']    = $this->_safe([$this, '_structure']);
		$r['4b_bom_tree']    = $this->_safe([$this, '_bom_tree']);
		$r['5_twin']         = $this->_safe([$this, '_twin']);
		$r['6_snapshot']     = $this->_safe([$this, '_snapshot']);
		$r['6b_baseline_list']=$this->_safe([$this, '_baseline_list']);
		$r['7_merge']        = $this->_safe([$this, '_merge']);
		$r['8_search']       = $this->_safe([$this, '_search']);
		$r['9_dictionary']   = $this->_safe([$this, '_dictionary']);
		$r['10_codes']       = $this->_safe([$this, '_codes']);
		$r['11_xref']        = $this->_safe([$this, '_xref_resolve']);
		$r['12_consolidate'] = $this->_safe([$this, '_consolidate']);
		$r['13_object_crud'] = $this->_safe([$this, '_object_crud']);
		$r['14_duplicates']  = $this->_safe([$this, '_duplicates']);
		$r['15_type_schema'] = $this->_safe([$this, '_type_schema']);
		$r['16_link_delete'] = $this->_safe([$this, '_link_delete']);

		$r['_summary'] = $this->_summary($r);

		$this->_json_response($r);
	}

	protected function _safe($fn)
	{
		try
		{
			return $fn();
		}
		catch (Exception $e)
		{
			return ['ok' => FALSE, 'error' => $e->getMessage(), 'line' => $e->getLine()];
		}
	}

	protected function _call($path, $method = 'GET', array $query = [], $body = NULL)
	{
		$request = Request::factory($this->_api.'/'.ltrim($path, '/'));
		$request->method($method);

		if ($query)
			$request->query($query);

		if ($body !== NULL)
		{
			$request->body(json_encode($body));
			$request->headers('Content-Type', 'application/json');
		}

		try
		{
			$response = $request->execute();
			return [
				'http' => (int) $response->status(),
				'body' => json_decode($response->body(), TRUE),
			];
		}
		catch (Exception $e)
		{
			return ['http' => 500, 'body' => ['status' => 'error', 'message' => $e->getMessage()]];
		}
	}

	protected function _is_ok(array $res)
	{
		return $res['http'] >= 200 AND $res['http'] < 300
			AND Arr::path($res, 'body.status') === 'ok';
	}

	protected function _sql($type, $sql, array $params = [])
	{
		$q = DB::query($type, $sql);
		foreach ($params as $k => $v)
			$q->param($k, $v);

		return $q->execute(Database::instance('mdm'));
	}

	protected function _activate($id)
	{
		return $this->_call('object/'.$id.'/state', 'POST', [], [
			'state' => 'active',
		]);
	}

	protected function _probe_transactions()
	{
		$db  = Database::instance('mdm');
		$out = [
			'driver'          => get_class($db),
			'runtime_begin'   => NULL,
		];

		if (method_exists($db, 'begin'))
		{
			try
			{
				$db->begin();
				$db->rollback();
				$out['runtime_begin'] = 'ok';
			}
			catch (Exception $e)
			{
				$out['runtime_begin'] = 'FAILED: '.$e->getMessage();
			}
		}

		return $out;
	}

	protected function _setup()
	{
		foreach ([
			'plm'      => 90,
			'ai'       => 30,
			'test_src' => 70,
			'manual'   => 100,
			'steward'  => 100,
			'sensor'   => 40,
		] as $id => $trust)
		{
			$src = ORM::factory('Erp230_Source', $id);
			$src->id           = $id;
			$src->display_name = strtoupper($id);
			$src->trust_score  = $trust;
			$src->save();
		}

		foreach (['HOLDING' => NULL, 'TEST_LAB' => 'HOLDING'] as $id => $parent)
		{
			$org = ORM::factory('Erp230_OrgUnit', $id);
			if ( ! $org->loaded())
			{
				$org->id        = $id;
				$org->name      = $id;
				$org->parent_id = $parent;
				$org->save();
			}
		}

		$type = ORM::factory('Erp230_Type', 'test_part');
		$type->id           = 'test_part';
		$type->display_name = 'Тестовая деталь';
		$type->schema = json_encode([
			'type'       => 'object',
			'required'   => ['name'],
			'properties' => [
				'name'   => ['type' => 'string', 'minLength' => 1],
				'weight' => ['type' => 'number'],
				'color'  => ['type' => 'string'],
			],
		]);
		$type->save();

		return ['ok' => TRUE];
	}

	protected function _create_objects()
	{
		$mk = function ($code) {
			return $this->_call('object', 'POST', [], [
				'type_id'     => 'test_part',
				'org_id'      => 'TEST_LAB',
				'master_code' => $code,
			]);
		};

		$winner = $mk('WINNER-'.mt_rand(1000, 9999));
		$loser  = $mk('LOSER-'.mt_rand(1000, 9999));
		$child  = $mk('CHILD-'.mt_rand(1000, 9999));

		$this->_ctx['winner'] = Arr::path($winner, 'body.object.id');
		$this->_ctx['loser']  = Arr::path($loser,  'body.object.id');
		$this->_ctx['child']  = Arr::path($child,  'body.object.id');

		return [
			'ok'     => $this->_is_ok($winner) AND $this->_is_ok($loser) AND $this->_is_ok($child),
			'winner' => $this->_ctx['winner'],
			'loser'  => $this->_ctx['loser'],
			'child'  => $this->_ctx['child'],
		];
	}

	protected function _properties()
	{
		$id  = $this->_ctx['winner'];
		$set = $this->_call('properties/'.$id, 'POST', [], [
			'source_id' => 'plm',
			'items'     => [
				'name'   => 'Test Winner Part',
				'weight' => ['value' => 10.2, 'uom' => 'kg'],
				'color'  => 'red',
			],
		]);

		$get = $this->_call('properties/'.$id);

		$set_body = Arr::path($set, 'body.results');
		if ($set_body === NULL)
			$set_body = Arr::path($set, 'body.data');

		return [
			'ok'      => $this->_is_ok($set) AND $this->_is_ok($get),
			'set'     => $set_body,
			'current' => array_keys((array) Arr::path($get, 'body.data')),
		];
	}

	protected function _attribute_history()
	{
		$id = $this->_ctx['winner'];

		$this->_call('properties/'.$id, 'POST', [], [
			'source_id' => 'plm',
			'items'     => ['weight' => ['value' => 11.0, 'uom' => 'kg']],
		]);

		$hist = $this->_call('attribute_history/'.$id, 'GET', ['key' => 'weight']);
		$entries = (array) Arr::path($hist, 'body.data', Arr::path($hist, 'body.history', []));

		$has_actor = FALSE;
		if ( ! empty($entries))
		{
			$first = reset($entries);
			$has_actor = isset($first['actor_id']) OR isset($first['actor']);
		}

		return [
			'ok'        => $this->_is_ok($hist),
			'entries'   => count($entries),
			'has_actor' => $has_actor,
		];
	}

	protected function _structure()
	{
		$this->_call('properties/'.$this->_ctx['winner'], 'POST', [], [
			'source_id' => 'plm',
			'items'     => ['name' => 'Winner Root'],
		]);
		$this->_call('properties/'.$this->_ctx['child'], 'POST', [], [
			'source_id' => 'plm',
			'items'     => ['name' => 'Child Part'],
		]);

		$this->_activate($this->_ctx['winner']);
		$this->_activate($this->_ctx['child']);

		$link = $this->_call('structure/create_link', 'POST', [], [
			'parent_id' => $this->_ctx['winner'],
			'child_id'  => $this->_ctx['child'],
			'link_type' => 'ebom',
			'qty'       => 2,
		]);

		$this->_ctx['link_id'] = Arr::path($link, 'body.link_id');

		$bom  = $this->_call('structure/bom/'.$this->_ctx['winner'], 'GET', ['type' => 'ebom']);
		$used = $this->_call('structure/where_used/'.$this->_ctx['child'], 'GET', ['type' => 'ebom']);

		return [
			'ok'       => $this->_is_ok($link) AND $this->_is_ok($bom),
			'link_id'  => $this->_ctx['link_id'],
			'children' => count((array) Arr::path($bom, 'body.children')),
			'used_in'  => count((array) Arr::path($used, 'body.used_in')),
		];
	}

	protected function _bom_tree()
	{
		$tree = $this->_call('structure/bom_tree/'.$this->_ctx['winner'], 'GET', [
			'type'  => 'ebom',
			'depth' => 5,
		]);

		return [
			'ok'    => $this->_is_ok($tree),
			'nodes' => Arr::path($tree, 'body.nodes'),
			'tree'  => Arr::path($tree, 'body.tree'),
		];
	}

	protected function _twin()
	{
		$id = $this->_ctx['winner'];

		$tele = $this->_call('telemetry/'.$id, 'POST', [], [
			'source_id' => 'sensor',
			'readings'  => ['temperature' => ['value' => 55.4, 'uom' => 'C']],
		]);

		$twin = $this->_call('twin/'.$id);
		$now  = $this->_call('twin/'.$id.'/state', 'GET', ['at' => date('c')]);

		return [
			'ok'            => $this->_is_ok($twin),
			'telemetry'     => Arr::path($tele, 'body.results'),
			'twin_props'    => count((array) Arr::path($twin, 'body.properties')),
			'state_at_keys' => array_keys((array) Arr::path($now, 'body.state')),
		];
	}

	protected function _snapshot()
	{
		$id = $this->_ctx['winner'];
		$this->_activate($id);

		$snap = $this->_call('baselines/snapshot', 'POST', [], ['object_id' => $id, 'code' => 'V1']);
		$ver  = $this->_call('baselines/verify/'.$id);

		$this->_ctx['baseline_id'] = Arr::path($snap, 'body.baseline_id');

		return [
			'ok'          => $this->_is_ok($snap) AND $this->_is_ok($ver),
			'baseline_id' => $this->_ctx['baseline_id'],
			'hash'        => Arr::path($snap, 'body.hash'),
			'is_valid'    => Arr::path($ver, 'body.is_valid'),
		];
	}

	protected function _baseline_list()
	{
		$id   = $this->_ctx['winner'];
		$list = $this->_call('baselines/list/'.$id);

		$baselines = (array) Arr::path($list, 'body.baselines');
		$details_ok = TRUE;

		if ( ! empty($baselines))
		{
			$first = reset($baselines);
			$det = $this->_call('baselines/details/'.Arr::get($first, 'id'));
			$details_ok = $this->_is_ok($det);
		}

		return [
			'ok'         => $this->_is_ok($list) AND $details_ok,
			'count'      => count($baselines),
			'details_ok' => $details_ok,
		];
	}

	protected function _merge()
	{
		$loser  = $this->_ctx['loser'];
		$winner = $this->_ctx['winner'];

		$remote = 'R-'.mt_rand(10000, 99999);
		$this->_sql(Database::UPDATE,
			'INSERT INTO object_xref (source_id, remote_id, object_id)
			 VALUES (:s, :r, :o)',
			[':s' => 'test_src', ':r' => $remote, ':o' => $loser]);

		$res = $this->_call('object/'.$loser.'/merge', 'POST', [], ['into' => $winner]);

		$obj = $this->_sql(Database::SELECT,
			'SELECT state, merged_into FROM objects WHERE id = :id',
			[':id' => $loser])->current();

		$xref_now = $this->_sql(Database::SELECT,
			'SELECT object_id FROM object_xref WHERE source_id = :s AND remote_id = :r',
			[':s' => 'test_src', ':r' => $remote])->get('object_id');

		$obj_ok  = ($obj !== NULL && $obj['state'] === 'merged' && $obj['merged_into'] === $winner);
		$xref_ok = ($xref_now === $winner);

		$self = $this->_call('object/'.$winner.'/merge', 'POST', [], ['into' => $winner]);

		return [
			'ok'                 => ($this->_is_ok($res) && $obj_ok && $xref_ok),
			'api_response'       => Arr::path($res, 'body'),
			'object_marked'      => $obj_ok,
			'xref_repointed'     => $xref_ok,
			'self_merge_blocked' => ($self['http'] === 400),
		];
	}

	protected function _search()
	{
		$obj  = $this->_sql(Database::SELECT,
			'SELECT master_code FROM objects WHERE id = :id',
			[':id' => $this->_ctx['winner']])->get('master_code');

		$res = $this->_call('search', 'GET', ['q' => $obj, 'type' => 'text']);

		$count = Arr::path($res, 'body.count');
		if ($count === NULL)
			$count = count((array) Arr::path($res, 'body.data', Arr::path($res, 'body.results', [])));

		return [
			'ok'    => $this->_is_ok($res),
			'count' => $count,
		];
	}

	protected function _dictionary()
	{
		$res = $this->_call('dictionary/sources');
		$count = Arr::path($res, 'body.count', count((array) Arr::path($res, 'body.data', [])));

		return [
			'ok'    => $this->_is_ok($res),
			'count' => $count,
		];
	}

	protected function _codes()
	{
		$code = $this->_sql(Database::SELECT,
			'SELECT master_code FROM objects WHERE id = :id',
			[':id' => $this->_ctx['winner']])->get('master_code');

		$check = $this->_call('codes/check', 'GET', [
			'master_code' => $code,
			'type_id'     => 'test_part',
			'org_id'      => 'TEST_LAB',
		]);

		$resolve = $this->_call('codes/resolve', 'GET', [
			'master_code' => $code,
			'type_id'     => 'test_part',
			'org_id'      => 'TEST_LAB',
		]);

		$resolved_id = Arr::path($resolve, 'body.object_id', Arr::path($resolve, 'body.id'));

		return [
			'ok'          => $this->_is_ok($check) AND $this->_is_ok($resolve),
			'exists'      => Arr::path($check, 'body.exists'),
			'resolved_id' => $resolved_id,
			'match'       => ($resolved_id === $this->_ctx['winner']),
		];
	}

	protected function _xref_resolve()
	{
		$id     = $this->_ctx['child'];
		$remote = 'XR-'.mt_rand(10000, 99999);

		$this->_sql(Database::UPDATE,
			'INSERT INTO object_xref (source_id, remote_id, object_id)
			 VALUES (:s, :r, :o)',
			[':s' => 'test_src', ':r' => $remote, ':o' => $id]);

		$resolve = $this->_call('resolve', 'GET', [
			'source' => 'test_src',
			'remote' => $remote,
		]);

		$xref = $this->_call('xref/'.$id);
		$resolved = Arr::path($resolve, 'body.object_id', Arr::path($resolve, 'body.object.id'));

		return [
			'ok'       => $this->_is_ok($resolve) AND $this->_is_ok($xref),
			'resolved' => ($resolved === $id),
			'xref_cnt' => count((array) Arr::path($xref, 'body.xref')),
		];
	}

	protected function _consolidate()
	{
		$remote = 'CONS-'.mt_rand(10000, 99999);
		$code   = 'CONS-'.mt_rand(1000, 9999);

		$payload = [
			'source_id' => 'test_src',
			'remote_id' => $remote,
			'payload'   => [
				'type_id'     => 'test_part',
				'org_id'      => 'TEST_LAB',
				'master_code' => $code,
				'properties'  => [
					'name'   => ['value' => 'Consolidated Part'],
					'weight' => ['value' => 5.5, 'uom' => 'kg'],
				],
			],
		];

		$first  = $this->_call('consolidate', 'POST', [], $payload);
		$second = $this->_call('consolidate', 'POST', [], $payload);

		$id1 = Arr::path($first,  'body.object_id', Arr::path($first, 'body.object.id'));
		$id2 = Arr::path($second, 'body.object_id', Arr::path($second, 'body.object.id'));

		return [
			'ok'          => $this->_is_ok($first) AND $this->_is_ok($second),
			'idempotent'  => ($id1 !== NULL AND $id1 === $id2),
			'object_id'   => $id1,
		];
	}

	protected function _object_crud()
	{
		$id = $this->_ctx['child'];
		$get = $this->_call('object/'.$id, 'GET', ['with' => 'properties']);

		$patch = $this->_call('object/'.$id, 'PATCH', [], [
			'org_id' => 'HOLDING',
		]);

		$get2 = $this->_call('object/'.$id, 'GET');
		$new_org = Arr::path($get2, 'body.object.org_id');

		return [
			'ok'         => $this->_is_ok($get) AND $this->_is_ok($patch),
			'has_props'  => (Arr::path($get, 'body.object.properties') !== NULL
			                 OR Arr::path($get, 'body.properties') !== NULL),
			'org_patched'=> ($new_org === 'HOLDING'),
		];
	}

	protected function _duplicates()
	{
		$code = 'DUP-'.mt_rand(10000, 99999);

		$first = $this->_call('object', 'POST', [], [
			'type_id'     => 'test_part',
			'org_id'      => 'TEST_LAB',
			'master_code' => $code,
		]);

		$dup = $this->_call('object', 'POST', [], [
			'type_id'     => 'test_part',
			'org_id'      => 'TEST_LAB',
			'master_code' => $code,
		]);

		return [
			'ok'            => $this->_is_ok($first),
			'first_created' => Arr::path($first, 'body.object.id'),
			'dup_blocked'   => ($dup['http'] === 409),
			'dup_code'      => Arr::path($dup, 'body.code'),
		];
	}

	protected function _type_schema()
	{
		$schema = $this->_call('type/schema/test_part');
		$list   = $this->_call('type/list');

		$has_schema = (Arr::path($schema, 'body.schema') !== NULL OR Arr::path($schema, 'body.data') !== NULL);
		$types = Arr::path($list, 'body.types', Arr::path($list, 'body.data', []));

		return [
			'ok'          => $this->_is_ok($schema) AND $this->_is_ok($list),
			'has_schema'  => $has_schema,
			'types_count' => count((array) $types),
		];
	}

	protected function _link_delete()
	{
		$link_id = Arr::get($this->_ctx, 'link_id');

		if ( ! $link_id)
			return ['ok' => FALSE, 'error' => 'no link_id from _structure step'];

		$del = $this->_call('structure/delete_link/'.$link_id, 'DELETE');

		return [
			'ok'      => $this->_is_ok($del),
			'closed'  => Arr::path($del, 'body.closed'),
		];
	}

	protected function _summary(array $r)
	{
		$passed = $failed = 0;
		foreach ($r as $key => $step)
		{
			if ($key === '_summary' OR $key === '0_driver_tx') continue;
			if (Arr::get($step, 'ok') === TRUE) $passed++; else $failed++;
		}
		return [
			'passed' => $passed,
			'failed' => $failed,
			'verdict' => $failed === 0 ? 'ALL GREEN' : 'HAS FAILURES',
		];
	}
}