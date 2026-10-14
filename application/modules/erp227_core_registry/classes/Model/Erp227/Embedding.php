<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель для работы с векторными эмбеддингами объектов.
 * Используется для реализации семантического AI-поиска с использованием расширения pgvector и HNSW индекса.
 *
 * @property int        $id           Идентификатор записи
 * @property int        $object_id    ID объекта из реестра (erp_227_objects)
 * @property string     $model_name   Название нейросетевой модели, создавшей вектор (например, 'text-embedding-3-large')
 * @property string     $embedding    Векторное представление объекта (формат VECTOR(1024))
 * @property string     $created_at   Дата и время создания записи
 *
 * @property Model_Erp227_Object $object Связанный объект реестра
 *
 * @package    ERP
 * @category   Model
 * @author     Kohana Programmer
 */
class Model_Erp227_Embedding extends Model_Erp227_Base {

	protected $_table_name = 'erp_227_embeddings';

	protected $_belongs_to = [
		'object' => [
			'model'       => 'Erp227_Object',
			'foreign_key' => 'object_id',
		],
	];

	protected $_created_column = [
		'column' => 'created_at',
		'format' => 'Y-m-d H:i:s',
	];

	/**
	 * Правила валидации для полей модели
	 *
	 * @return array
	 */
	public function rules()
	{
		return [
			'object_id' => [
				['not_empty'],
				['digit'],
			],
			'model_name' => [
				['not_empty'],
				['max_length', [':value', 100]],
			],
			'embedding' => [
				['not_empty'],
			],
		];
	}

	/**
	 * Человекопонятные метки полей
	 *
	 * @return array
	 */
	public function labels()
	{
		return [
			'id'         => 'ID',
			'object_id'  => 'Объект',
			'model_name' => 'Модель эмбеддинга',
			'embedding'  => 'Вектор',
			'created_at' => 'Дата создания',
		];
	}

	/**
	 * Фильтры очистки данных
	 *
	 * @return array
	 */
	public function filters()
	{
		return [
			'model_name' => [
				['trim'],
				['strip_tags'],
			],
			'embedding' => [
				['trim'],
			],
		];
	}
} 