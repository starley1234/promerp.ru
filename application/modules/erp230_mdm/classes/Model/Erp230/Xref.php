<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель кросс-референсов (object_xref).
 *
 * ВАЖНО: таблица имеет составной первичный ключ (source_id, remote_id),
 * поэтому стандартный механизм ORM Kohana (одиночный PK) здесь неприменим.
 *
 * READ-ONLY для ORM:
 *  - НЕ используйте save()/create()/update()/find($id) по одиночному ключу;
 *  - любая запись/обновление/удаление выполняется ТОЛЬКО через DB::query(...)
 *    с явным указанием всех полей составного ключа.
 *
 * Пример записи:
 *   DB::query(Database::INSERT,
 *     'INSERT INTO object_xref (source_id, remote_id, object_id)
 *        VALUES (:s, :r, :o)
 *      ON CONFLICT (source_id, remote_id)
 *        DO UPDATE SET object_id = EXCLUDED.object_id')
 *     ->parameters([':s' => $source, ':r' => $remote, ':o' => $object])
 *     ->execute('mdm');
 */
class Model_Erp230_Xref extends Model_Erp230_Main {

	protected $_table_name  = 'object_xref';
	protected $_primary_key = 'source_id';   // логический PK для ORM (в БД составной)

	protected $_table_columns = [
		'source_id' => ['type' => 'string'],
		'remote_id' => ['type' => 'string'],
		'object_id' => ['type' => 'string'],
	];

	/**
	 * Запрет записи через ORM: составной PK не поддерживается стандартным
	 * механизмом Kohana ORM. Используйте DB::query() напрямую.
	 *
	 * @throws Kohana_Exception
	 */
	public function save(?Validation $validation = NULL)
	{
		throw new Kohana_Exception(
			'Model_Erp230_Xref is read-only for ORM: используйте DB::query() '.
			'для записи (составной PK: source_id + remote_id).'
		);
	}

	/**
	 * Запрет создания через ORM.
	 *
	 * @throws Kohana_Exception
	 */
	public function create(?Validation $validation = NULL)
	{
		throw new Kohana_Exception(
			'Model_Erp230_Xref is read-only for ORM: используйте DB::query() '.
			'для создания записи (составной PK: source_id + remote_id).'
		);
	}

	/**
	 * Запрет обновления через ORM.
	 *
	 * @throws Kohana_Exception
	 */
	public function update(?Validation $validation = NULL)
	{
		throw new Kohana_Exception(
			'Model_Erp230_Xref is read-only for ORM: используйте DB::query() '.
			'для обновления записи (составной PK: source_id + remote_id).'
		);
	}

	/**
	 * Запрет удаления через ORM.
	 *
	 * @throws Kohana_Exception
	 */
	public function delete()
	{
		throw new Kohana_Exception(
			'Model_Erp230_Xref is read-only for ORM: используйте DB::query() '.
			'для удаления записи (составной PK: source_id + remote_id).'
		);
	}
}