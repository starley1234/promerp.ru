<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Реакции складского учёта на события (erp401_wms).
 *
 * Методы зарегистрированы как слушатели в шине событий erp208 (таблица
 * erp_208_event_subscribers) и вызываются при публикации событий модуля.
 * Формат колбэка шины: function(array $payload, $event_name, $initiator_id).
 *
 * @package    Erp401
 * @category   Services
 */
class Service_Erp401_Notifications
{
    /**
     * Остаток опустился до минимума: уведомляем ответственных за склад.
     *
     * @param array       $payload      warehouse_id, item_id, qty, min_qty, uom_code
     * @param string|null $event_name
     * @param int|null    $initiator_id
     * @return int Количество отправленных уведомлений
     */
    public static function on_stock_low($payload, $event_name = NULL, $initiator_id = NULL)
    {
        if ( ! class_exists('Service_Erp217_Bus'))
        {
            return 0;
        }

        $item_id = (int) Arr::get((array) $payload, 'item_id', 0);

        if ($item_id <= 0)
        {
            return 0;
        }

        $stock = Service_Erp401_Stock::instance();
        $item  = $stock->item($item_id);

        $data = array(
            'item_code' => $item['code'],
            'item_name' => $item['name'],
            'qty'       => (float) Arr::get((array) $payload, 'qty', 0),
            'min_qty'   => (float) Arr::get((array) $payload, 'min_qty', 0),
            'uom_code'  => (string) Arr::get((array) $payload, 'uom_code', 'pcs'),
            'url'       => '/warehouse/balances?low_only=1',
        );

        $sent = 0;

        foreach (self::_responsible_user_ids() as $user_id)
        {
            if ($initiator_id !== NULL AND (int) $user_id === (int) $initiator_id)
            {
                continue;
            }

            try
            {
                Service_Erp217_Bus::instance()->send('erp401.stock_low', $user_id, $data);
                $sent++;
            }
            catch (Exception $e)
            {
                Kohana::$log->add(Log::WARNING, 'erp401: не удалось отправить уведомление пользователю :user — :error',
                    array(':user' => $user_id, ':error' => $e->getMessage()));
            }
        }

        return $sent;
    }

    /**
     * ID пользователей, ответственных за склад: роль 401_stock_manager и админы.
     *
     * Таблицы users/roles — ядро системы (erp100_base), а не чужой модуль,
     * поэтому прямой запрос здесь допустим.
     *
     * @return array
     */
    protected static function _responsible_user_ids()
    {
        $ids = array();

        try
        {
            $rows = DB::select(array('users.id', 'id'))
                ->distinct(TRUE)
                ->from('users')
                ->join('roles_users')->on('roles_users.user_id', '=', 'users.id')
                ->join('roles')->on('roles.id', '=', 'roles_users.role_id')
                ->where('roles.name', 'IN', array('401_stock_manager', 'admin'))
                ->execute()
                ->as_array();

            foreach ($rows as $row)
            {
                $ids[] = (int) $row['id'];
            }
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp401: не удалось получить ответственных за склад — :error',
                array(':error' => $e->getMessage()));
        }

        return $ids;
    }
}
