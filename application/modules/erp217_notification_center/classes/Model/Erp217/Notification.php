<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель очереди сообщений (Notification)
 * Модуль ERP217: Центр уведомлений
 * Адаптировано под PostgreSQL
 *
 * @package    ERP217
 * @category   Model
 * @author     Generated
 */
class Model_Erp217_Notification extends ORM {

    protected $_table_name = 'erp_217_notification';

    // Константы статусов
    const STATUS_PENDING    = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SENT       = 'sent';
    const STATUS_FAILED     = 'failed';
    const STATUS_READ     	= 'read';

    // Связи модели
    protected $_belongs_to = array(
        'recipient' => array(
            'model'       => 'User',
            'foreign_key' => 'recipient_id',
        ),
        'template' => array(
            'model'       => 'Erp217_Template',
            'foreign_key' => 'template_id',
        ),
        'creator' => array(
            'model'       => 'User',
            'foreign_key' => 'created_by',
        ),
    );

    protected $_has_many = array(
        'logs' => array(
            'model'       => 'Erp217_Log',
            'foreign_key' => 'notification_id',
        ),
    );

    /**
     * Правила валидации
     */
    public function rules()
    {
        return array(
            'recipient_id' => array(
                array('not_empty'),
                array('numeric'),
            ),
            'channel' => array(
                array('not_empty'),
                array('max_length', array(':value', 50)),
            ),
            'destination' => array(
                array('not_empty'),
                array('max_length', array(':value', 255)),
            ),
            'status' => array(
                array('in_array', array(':value', array('pending', 'processing', 'sent', 'read', 'failed'))),
            ),
        );
    }

    /**
     * Автоматическое приведение payload к массиву при чтении
     * и в JSON при сохранении
     */
    public function values(array $values, ?array $expected = NULL)
    {
        if (isset($values['payload']) AND is_array($values['payload']))
        {
            $values['payload'] = json_encode($values['payload'], JSON_UNESCAPED_UNICODE);
        }
        return parent::values($values, $expected);
    }

    /**
     * Геттер для payload (декодирование JSON)
     * @return array
     */
    public function get_payload()
    {
        return json_decode($this->payload, TRUE) ?: array();
    }

    /**
     * Метод для логирования ошибки и инкремента попыток
     * @param string $error_message
     * @return $this
     */
    public function log_error($error_message)
    {
        $this->status = self::STATUS_FAILED;
        $this->attempts = (int)$this->attempts + 1;
        $this->last_error = $error_message;
        $this->updated_at = date('Y-m-d H:i:s');
        $result = $this->save();
        $this->update_statistics($this->channel);
        return $result;
    }

    /**
     * Пометить как успешно отправленное
     * @return $this
     */
    public function set_sent()
    {
        $this->status = self::STATUS_SENT;
        $this->sent_at = date('Y-m-d H:i:s');
        $this->attempts = (int)$this->attempts + 1;
        $result = $this->save();
        $this->update_statistics($this->channel);
        return $result;
    }

    /**
     * Фильтр для получения записей, готовых к отправке (для Cron)
     * @param ORM $query
     * @return ORM
     */
    public function scope_ready_to_send($query)
    {
        return $query
            ->where('status', 'IN', array(self::STATUS_PENDING, self::STATUS_FAILED))
            ->where('attempts', '<', 5) // Лимит попыток из конфига (условно 5)
            ->where('is_deleted', '=', 0)
            ->and_where_open()
                ->where('scheduled_at', 'IS', NULL)
                ->or_where('scheduled_at', '<=', date('Y-m-d H:i:s'))
            ->and_where_close()
            ->order_by('created_at', 'ASC');
    }

    /**
     * Метод для получения перечня элементов очереди, готовых к обработке.
     * Использует scope_ready_to_send для фильтрации.
     *
     * @param int $limit Максимальное количество возвращаемых элементов.
     * @return Database_Result|array Возвращает коллекцию найденных элементов.
     */
    public function get_items_for_processing($limit = NULL)
    {
        $query = ORM::factory('Erp217_Notification');

        // Исправлено: передаем объект запроса в scope метод явно, 
        // так как он ожидает аргумент $query
        $query = $this->scope_ready_to_send($query);

        // Применяем лимит, если он указан
        if ($limit !== NULL) {
            $query->limit($limit);
        }

        // Возвращаем результат запроса
        return $query->find_all();
    }

    /**
     * Автозаполнение полей при создании
     */
    public function create(?Validation $validation = NULL)
    {
        if ( ! $this->created_at) {
            $this->created_at = date('Y-m-d H:i:s');
        }

        // Если payload передан как массив, кодируем
        if (is_array($this->payload)) {
            $this->payload = json_encode($this->payload, JSON_UNESCAPED_UNICODE);
        }

        $result = parent::create($validation);
        $this->update_statistics($this->channel);
        return $result;
    }

    /**
     * Обновление агрегированной статистики в таблице erp_217_statistics
     * Адаптировано под PostgreSQL (UPSERT и интервалы дат)
     * @param string $channel Канал уведомления
     */
    public function update_statistics($channel = NULL)
    {
        $channels = array('all');
        if ($channel && $channel !== 'all') {
            $channels[] = $channel;
        }

        foreach ($channels as $ch) {
            $query = DB::select(
                array(DB::expr('COUNT(*)::integer'), 'total_in_notification'),
                array(DB::expr("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END)::integer"), 'pending_count'),
                array(DB::expr("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END)::integer"), 'error_count'),
                array(DB::expr("SUM(CASE WHEN status IN ('sent', 'read') AND sent_at >= (NOW() - INTERVAL '1 day') THEN 1 ELSE 0 END)::integer"), 'sent_last_24h')
            )->from($this->_table_name);

            if ($ch !== 'all') {
                $query->where('channel', '=', $ch);
            }

            $stats = $query->execute()->current();

            // PostgreSQL UPSERT синтаксис (ON CONFLICT)
            DB::query(Database::INSERT, "
                INSERT INTO erp_217_statistics (channel, total_in_notification, pending_count, error_count, sent_last_24h, updated_at)
                VALUES (:channel, :total, :pending, :error, :sent, :updated)
                ON CONFLICT (channel) DO UPDATE SET 
                    total_in_notification = EXCLUDED.total_in_notification,
                    pending_count = EXCLUDED.pending_count,
                    error_count = EXCLUDED.error_count,
                    sent_last_24h = EXCLUDED.sent_last_24h,
                    updated_at = EXCLUDED.updated_at
            ")->parameters(array(
                ':channel' => $ch,
                ':total'   => (int)$stats['total_in_notification'],
                ':pending' => (int)$stats['pending_count'],
                ':error'   => (int)$stats['error_count'],
                ':sent'    => (int)$stats['sent_last_24h'],
                ':updated' => date('Y-m-d H:i:s'),
            ))->execute();
        }
    }
}