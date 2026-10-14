<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель единиц измерения (ОКЕИ)
 * 
 * Справочник содержит перечень единиц измерения, используемых в системе.
 * 
 * Примеры единиц времени:
 * 354 - Секунда (с / s) - Код: С / SEC
 * 355 - Минута (мин / min) - Код: МИН / MIN
 * 356 - Час (ч / h) - Код: Ч / HUR
 * 359 - Сутки (сут; дн / d) - Код: СУТ; ДН / DAY
 * 360 - Неделя (нед / -) - Код: НЕД / WEE
 * 361 - Декада (дек / -) - Код: ДЕК / DAD
 *
 * @package    ERP230
 * @category   Model
 */
class Model_Erp230_Uom extends Model_Erp230_Main {

	/**
	 * @var string Имя таблицы в БД
	 */
	protected $_table_name  = 'uom';

	/**
	 * @var string Первичный ключ (Код ОКЕИ)
	 */
	protected $_primary_key = 'code';

	/**
	 * @var array Список полей таблицы
	 */
	protected $_table_columns = [
		'code'        => ['type' => 'string'], // Код
		'base_code'   => ['type' => 'string'], // Код базовой единицы
		'factor'      => ['type' => 'float'],  // Коэффициент пересчета
		'name'        => ['type' => 'string'], // Наименование единицы измерения
		'symbol_nat'  => ['type' => 'string'], // Условное обозначение национальное
		'symbol_intl' => ['type' => 'string'], // Условное обозначение международное
		'code_nat'    => ['type' => 'string'], // Кодовое обозначение национальное
		'code_intl'   => ['type' => 'string'], // Кодовое обозначение международное
	];
}