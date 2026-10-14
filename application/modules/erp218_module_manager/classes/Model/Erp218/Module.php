<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель реестра модулей ERP-системы.
 * 
 * @package    ERP
 * @category   Model
 * @author     Kohana Programmer
 * 
 * @property int        $id              Идентификатор модуля
 * @property string     $system_name     Имя папки модуля в application/modules/
 * @property string     $system_id       Префикс модуля, например, erp_218
 * @property string     $display_name    Понятное название модуля
 * @property string     $description     Краткое описание функционала
 * @property string     $icon            Иконка модуля (например, fa-cogs)
 * @property string     $version         Версия в формате SemVer (например, 1.0.0)
 * @property string     $status          Статус жизненного цикла (alpha, beta, stable, archived)
 * @property int        $is_published    Флаг доступности модуля в системе (0/1)
 * @property string     $developer_notes Технические заметки и особенности внедрения
 * @property string     $path            Путь к директории модуля
 * @property string     $main_page_uri   URI главной страницы модуля
 * @property string     $functionality   JSON-объект с перечнем функционала
 * @property string     $entities        JSON-объект с перечнем сущностей модуля
 * @property int        $sort_order      Порядок сортировки модулей в интерфейсе
 * @property int        $created_by      ID пользователя, создавшего запись
 * @property string     $created_at      Дата и время создания
 * @property string     $updated_at      Дата и время последнего обновления
 * @property int        $is_deleted      Флаг мягкого удаления (0/1)
 * 
 * @property Model_Erp218_Dependency[] $dependencies Список зависимостей модуля
 * @property Model_Erp218_History[]    $history      История изменений версий
 * @property Model_Erp218_Llm        $analysis     Результаты LLM анализа
 */
class Model_Erp218_Module extends ORM {

	protected $_table_name = 'erp_218_modules';

	protected $_has_many = array(
		'dependencies' => array(
			'model'       => 'Erp218_Dependency',
			'foreign_key' => 'module_id',
		),
		'history' => array(
			'model'       => 'Erp218_History',
			'foreign_key' => 'module_id',
		),
	);

	protected $_has_one = array(
		'analysis' => array(
			'model'       => 'Erp218_Llm',
			'foreign_key' => 'module_id',
		),
	);

	protected $_created_column = array(
		'column' => 'created_at',
		'format' => 'Y-m-d H:i:s',
	);

	protected $_updated_column = array(
		'column' => 'updated_at',
		'format' => 'Y-m-d H:i:s',
	);

	/**
	 * Правила валидации для полей модели
	 * 
	 * @return array
	 */
	public function rules()
	{
		return array(
			'system_name' => array(
				array('not_empty'),
				array('max_length', array(':value', 100)),
				array(array($this, 'unique'), array('system_name', ':value')),
			),
			'system_id' => array(
				array('max_length', array(':value', 50)),
			),
			'display_name' => array(
				array('not_empty'),
				array('max_length', array(':value', 255)),
			),
			'icon' => array(
				array('max_length', array(':value', 50)),
			),
			'version' => array(
				array('max_length', array(':value', 50)),
			),
			'status' => array(
				array('in_array', array(':value', array('alpha', 'beta', 'stable', 'archived'))),
			),
			'is_published' => array(
				array('digit'),
			),
			'path' => array(
				array('max_length', array(':value', 255)),
			),
			'main_page_uri' => array(
				array('max_length', array(':value', 255)),
			),
			'sort_order' => array(
				array('digit'),
			),
			'created_by' => array(
				array('not_empty'),
				array('digit'),
			),
		);
	}

	/**
	 * Человекопонятные метки полей
	 * 
	 * @return array
	 */
	public function labels()
	{
		return array(
			'id'              => 'ID',
			'system_name'     => 'Системное имя (папка)',
			'system_id'       => 'Системный ID (префикс)',
			'display_name'    => 'Название модуля',
			'description'     => 'Описание',
			'icon'            => 'Иконка',
			'version'         => 'Версия',
			'status'          => 'Статус',
			'is_published'    => 'Опубликован',
			'developer_notes' => 'Заметки разработчика',
			'path'            => 'Путь к файлам',
			'main_page_uri'   => 'URI главной страницы',
			'functionality'   => 'Функционал (JSON)',
			'entities'        => 'Сущности (JSON)',
			'sort_order'      => 'Порядок сортировки',
			'created_by'      => 'Автор',
			'created_at'      => 'Дата создания',
			'updated_at'      => 'Дата обновления',
		);
	}

	/**
	 * Фильтры очистки данных
	 * 
	 * @return array
	 */
	public function filters()
	{
		return array(
			'system_name' => array(
				array('trim'),
			),
			'system_id' => array(
				array('trim'),
			),
			'display_name' => array(
				array('trim'),
				array('Security::xss_clean'),
			),
			'description' => array(
				array('trim'),
			),
			'icon' => array(
				array('trim'),
			),
			'developer_notes' => array(
				array('trim'),
			),
			'path' => array(
				array('trim'),
			),
			'main_page_uri' => array(
				array('trim'),
			),
			'functionality' => array(
				array('trim'),
			),
			'entities' => array(
				array('trim'),
			),
		);
	}

	/**
	 * Возвращает дату и время последнего сканирования модулей.
	 * Это может быть дата последнего обновления любого модуля или
	 * дата последнего анализа LLM, если она есть.
	 *
	 * @return string|null Дата и время в формате 'Y-m-d H:i:s' или null, если сканирований не было.
	 */
	public static function get_last_scan_date()
	{
		$query = "
			SELECT GREATEST(
				(SELECT MAX(updated_at) FROM erp_218_modules),
				(SELECT MAX(last_scan_at) FROM erp_218_llm_analysis)
			) AS last_scan_date;
		";

		$result = DB::query(Database::SELECT, $query)
			->execute()
			->as_array();

		return $result[0]['last_scan_date'] ?? null;
	}

	/**
	 * Получает модуль по его ID.
	 *
	 * @param int $module_id
	 * @return Model_Erp218_Module
	 */
	public static function get_by_id($module_id)
	{
		// Используем стандартный способ загрузки ORM-объекта
		$module = ORM::factory('Erp218_Module', $module_id);
		
		// Если модуль не найден, $module->loaded() вернет false, что и требуется
		return $module;
	}

	/**
	 * Получает список модулей, которые ожидают LLM анализа.
	 *
	 * @return array Массив объектов Model_Erp218_Module
	 */
	public static function get_modules_for_llm_analysis()
	{
		// Используем ORM для построения запроса, это более идиоматично для Kohana
		// и гарантирует корректную загрузку объектов.
		$modules = ORM::factory('Erp218_Module')
			->select('erp218_module.*') // Выбираем все поля из таблицы модуля
			//->join('erp_218_llm_analysis', 'LEFT')
			//->on('erp218_module.id', '=', 'erp_218_llm_analysis.module_id')
			//->where('erp218_module.is_deleted', '=', 0)
            ->where('erp218_module.description', 'IS', NULL)
			//->and_where_open()
				//->where('erp_218_llm_analysis.analysis_status', '=', 'failed')
				// ->or_where('erp_218_llm_analysis.ai_summary', 'IS', NULL)
			//->and_where_close()
			->order_by('sort_order', 'ASC')
			->find_all(); // Получаем все найденные объекты

		return $modules->as_array(); // Возвращаем массив объектов
	}
}