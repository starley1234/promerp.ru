<?php defined('SYSPATH') or die('No direct script access.');

class Model_Erp230_Search extends Model_Erp230_Main {

	protected $_table_name  = 'object_state';
	protected $_primary_key = 'object_id';

	protected $_table_columns = [
		'object_id'  => ['type' => 'string'],
		'type_id'    => ['type' => 'string'],
		'org_path'   => ['type' => 'string'],
		'attributes' => ['type' => 'string'],
		'search'     => ['type' => 'string'],
		'updated_at' => ['type' => 'string'],
	];

	/** Заглушка под очередь переиндексации. */
	public function mark_for_reindex($object_id)
	{
		DB::query(Database::UPDATE,
			'UPDATE object_state SET updated_at = now() WHERE object_id = :id')
			->param(':id', $object_id)
			->execute($this->_db);
		return TRUE;
	}

	/**
	 * Семантический поиск по векторным эмбеддингам.
	 *
	 * Выполняет запрос к таблице object_embeddings, вычисляя косинусное
	 * расстояние (vector_cosine_ops, оператор <=>) между переданным вектором
	 * и сохранёнными эмбеддингами объектов.
	 *
	 * @param  string|array $vector_json  Вектор запроса: JSON-строка формата
	 *                                     "[0.1, 0.2, ...]" либо массив float.
	 * @param  int          $limit        Максимальное число результатов.
	 * @return array                      Массив ['object_id' => uuid, 'distance' => float].
	 * @throws Kohana_Exception           Если расширение/таблица недоступны.
	 */
	public function semantic_search($vector_json, $limit = 10)
	{
		// Нормализуем вход: допускаем как массив, так и готовую JSON-строку.
		if (is_array($vector_json))
		{
			$vector_json = json_encode(array_map('floatval', $vector_json));
		}

		$vector_json = trim((string) $vector_json);

		if ($vector_json === '' OR $vector_json === '[]')
		{
			return [];
		}

		// Валидация формата вектора: только цифры, точки, запятые, знаки,
		// экспонента и обрамляющие скобки. Защита от инъекций в ::vector.
		if ( ! preg_match('/^\[\s*-?\d/', $vector_json)
			OR preg_match('/[^\[\]\s0-9eE+\-.,]/', $vector_json))
		{
			throw new Kohana_Exception('Invalid vector format for semantic search');
		}

		$limit = max(1, (int) $limit);

		try
		{
			$rows = DB::query(Database::SELECT,
				'SELECT object_id, (embedding <=> :vec::vector) AS distance
				 FROM object_embeddings
				 ORDER BY embedding <=> :vec::vector
				 LIMIT :lim')
				->param(':vec', $vector_json)
				->param(':lim', $limit)
				->execute($this->_db)
				->as_array();
		}
		catch (Database_Exception $e)
		{
			// Расширение pgvector или таблица недоступны — честный отказ.
			throw new Kohana_Exception('Semantic search is unavailable: :msg',
				[':msg' => $e->getMessage()], 503, $e);
		}

		$result = [];

		foreach ($rows as $row)
		{
			$result[] = [
				'object_id' => $row['object_id'],
				'distance'  => (float) $row['distance'],
			];
		}

		return $result;
	}
}