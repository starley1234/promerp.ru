<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель для управления сериями кодов (автогенерация master_code).
 * Работает с таблицей code_series, созданной в pgMigration_003_erp230_mdm_create_tables.
 */
class Model_Erp230_CodeSeries extends Model_Erp230_Main {

    protected $_db_group = 'mdm';
    protected $_table_name = 'code_series';

    /**
     * Выделяет следующее значение серии для указанного типа и организации.
     * Использует хранимую процедуру PostgreSQL next_code_value для атомарности.
     *
     * @param string $type_id
     * @param string $org_id
     * @return int
     */
    public static function next_value($type_id, $org_id)
    {
        $result = DB::query(Database::SELECT, 'SELECT next_code_value(:type_id, :org_id) AS val')
            ->parameters([
                ':type_id' => $type_id,
                ':org_id'  => $org_id,
            ])
            ->execute(Database::instance('mdm'))
            ->current();

        return (int) Arr::get($result, 'val', 0);
    }

    /**
     * Генерирует отформатированный код с учетом префикса и дополнения нулями (padding).
     * Использует хранимую процедуру PostgreSQL next_code.
     *
     * @param string $type_id
     * @param string $org_id
     * @return string
     */
    public static function next_formatted($type_id, $org_id)
    {
        $result = DB::query(Database::SELECT, 'SELECT next_code(:type_id, :org_id) AS code')
            ->parameters([
                ':type_id' => $type_id,
                ':org_id'  => $org_id,
            ])
            ->execute(Database::instance('mdm'))
            ->current();

        return Arr::get($result, 'code');
    }

    /**
     * Настройка параметров серии (префикс, длина дополнения).
     *
     * @param string $type_id
     * @param string $org_id
     * @param array  $params ['prefix' => string, 'pad' => int]
     * @return bool
     */
    public static function configure($type_id, $org_id, array $params)
    {
        $set = [];
        if (isset($params['prefix'])) $set['prefix'] = $params['prefix'];
        if (isset($params['pad']))    $set['pad']    = (int) $params['pad'];

        if (empty($set)) return FALSE;

        $query = DB::insert('code_series', array_merge(['type_id', 'org_id'], array_keys($set)))
            ->values(array_merge([$type_id, $org_id], array_values($set)));

        // Синтаксис PostgreSQL для обновления при конфликте
        $sql = $query->compile(Database::instance('mdm'));
        $sql .= ' ON CONFLICT (type_id, org_id) DO UPDATE SET ';
        
        $updates = [];
        foreach ($set as $col => $val) {
            $updates[] = $col . ' = EXCLUDED.' . $col;
        }
        $sql .= implode(', ', $updates);

        DB::query(Database::INSERT, $sql)->execute(Database::instance('mdm'));
        return TRUE;
    }
}