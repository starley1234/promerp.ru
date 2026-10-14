# 6b_baseline_list — единственный failed

Итог: passed=18, failed=1.  
`6_snapshot` уже ok (baseline создаётся, hash/verify ок).  
`6b_baseline_list`: `count=1` → list работает, `details_ok=false` → падает `baselines/details/<id>`.

---

## Причина

Файл: `application/modules/erp230_mdm/classes/Controller/Erp230/Api/Baselines.php`  
Метод: `action_details`

Сейчас:
```php
'snapshot_data' => $this->_decode($baseline->snapshot_data),
```

В модели и БД колонка называется **`snapshot`**, не `snapshot_data`.

Доказательства:
- `Model_Erp230_Baseline`: в `$_table_columns` есть `snapshot`, нет `snapshot_data`
- `Service_Erp230_Consolidation::create_snapshot()` пишет `'snapshot' => $snapshot_data`
- ORM при обращении к несуществующему полю/`snapshot_data` → exception → `_is_ok($det) = false` → `details_ok=false` → `ok=false`

List при этом живой: он `snapshot` не трогает, только id/seq/code/hash — поэтому `count=1`.

---

## Исправление

В `Baselines.php` → `action_details` заменить чтение поля:

```php
public function action_details()
{
	$this->_handle(function () {
		$this->_require('mdm.baseline.view');

		$id = $this->request->param('id');
		$baseline = ORM::factory('Erp230_Baseline', $id);

		if ( ! $baseline->loaded())
			throw new Kohana_Exception('Baseline not found', NULL, 404);

		// колонка в БД/ORM — snapshot (не snapshot_data)
		$snap = $baseline->snapshot;
		if ($snap === NULL OR $snap === '')
			$snap = isset($baseline->snapshot_data) ? $baseline->snapshot_data : NULL;

		return [
			'status'   => 'ok',
			'baseline' => [
				'id'            => $baseline->id,
				'object_id'     => $baseline->object_id,
				'code'          => $baseline->code,
				'snapshot_data' => $this->_decode($snap),
				'hash'          => $baseline->snapshot_hash,
				'created_at'    => $baseline->created_at,
			],
		];
	});
}
```

Минимальный патч (одна строка):
```php
'snapshot_data' => $this->_decode($baseline->snapshot),
```
Ключ ответа `snapshot_data` можно оставить — тест смотрит только `body.status === 'ok'`.

---

## Проверить после правки

1. `GET erp230_api/baselines/details/<baseline_id>` → http 200, `status: ok`
2. Прогон ApiTest: `6b_baseline_list.details_ok=true`, `ok=true`, verdict `ALL GREEN`

---

## Не трогать

- `init.php` — роут extended уже ок (list/snapshot доходят)
- `ApiTest::_baseline_list` — контракт верный
- `create_snapshot` / `Model_Erp230_Baseline` — snapshot пишется правильно
- Остальные 18 шагов — зелёные