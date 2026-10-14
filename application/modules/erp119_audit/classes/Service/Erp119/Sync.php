<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Дочитывание событий шины в журнал действий (erp119_audit).
 *
 * Логика отделена от задачи `erp119:sync`, чтобы её можно было проверить
 * тестом без запуска CLI (тонкая задача — сервис с логикой).
 *
 * Почему не слушатель события в запросе: подписка выполняется в момент
 * действия пользователя, и её сбой бьёт по бизнес-операции. Здесь обратный
 * порядок — шина сохраняет событие в erp_208_events (outbox), а журнал
 * дочитывает его по указателю. Повторный запуск ничего не задваивает:
 * указатель двигается только после успешной записи.
 *
 * @package    Erp119
 * @category   Services
 */
class Service_Erp119_Sync
{
    /** @var string Таблица-источник событий (шина erp208) */
    const EVENTS_TABLE = 'erp_208_events';

    /** @var Service_Erp119_Sync */
    protected static $_instance;

    /** @var Service_Erp119_Journal */
    protected $_journal;

    /**
     * @return  Service_Erp119_Sync
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    protected function __construct()
    {
        $this->_journal = Service_Erp119_Journal::instance();
    }

    /**
     * Дочитать новые события шины.
     *
     * @param   int     $limit
     * @param   bool    $dry_run   только показать, что попало бы в журнал
     * @return  array   array('read', 'journaled', 'skipped', 'entries', 'cursor', 'last_id')
     */
    public function run($limit = 1000, $dry_run = FALSE)
    {
        $limit  = (int) $limit;
        $cursor = $this->_journal->cursor();

        $events = DB::select('id', 'event_name', 'payload', 'initiator_id', 'created_at')
            ->from(self::EVENTS_TABLE)
            ->where('id', '>', $cursor)
            ->order_by('id', 'ASC')
            ->limit($limit > 0 ? $limit : 1000)
            ->execute()
            ->as_array();

        $stat = array(
            'read'      => count($events),
            'journaled' => 0,
            'skipped'   => 0,
            'known'     => 0,
            'entries'   => array(),
            'cursor'    => $cursor,
            'last_id'   => $cursor,
            // Имена событий, не попавших в журнал: по ним видно, каких правил
            // разбора не хватает (шум шины отделяем от новых событий модулей)
            'ignored'   => array(),
            'unparsed'  => array(),
        );

        foreach ($events as $event)
        {
            $payload = json_decode((string) $event['payload'], TRUE);
            $payload = is_array($payload) ? $payload : array();

            $entry = $this->_journal->mapper()->parse(
                $event['event_name'],
                $payload,
                $event['initiator_id'] ? (int) $event['initiator_id'] : NULL
            );

            if ($entry)
            {
                // След события: по нему перенос не задваивает записи
                $entry['event_id'] = (int) $event['id'];
            }

            if ( ! $entry)
            {
                $stat['skipped']++;

                $bucket = $this->_journal->mapper()->wanted($event['event_name']) ? 'unparsed' : 'ignored';

                if ( ! isset($stat[$bucket][$event['event_name']]))
                {
                    $stat[$bucket][$event['event_name']] = 0;
                }

                $stat[$bucket][$event['event_name']]++;
            }
            else
            {
                // Событие могло быть опубликовано задним числом — берём его время.
                // erp_208_events.created_at — timestamp без пояса, заполняется
                // значением БД (то есть UTC), поэтому трактуем его как UTC явно:
                // иначе смена часового пояса сессии сдвинула бы время в журнале.
                $entry['occurred_at'] = gmdate('c', strtotime($event['created_at'].' UTC'));
                $stat['entries'][] = $entry;
            }

            $stat['last_id'] = (int) $event['id'];
        }

        // Повторное чтение (--reset после правки правил) не задваивает записи:
        // уже перенесённые события пропускаются по своему номеру.
        $known = $this->_journal->imported_event_ids(array_column($stat['entries'], 'event_id'));

        if ($known)
        {
            $fresh = array();

            foreach ($stat['entries'] as $entry)
            {
                if (isset($known[(int) $entry['event_id']]))
                {
                    // Событие уже перенесено раньше — это не «пропуск по правилам»
                    $stat['known']++;
                    continue;
                }

                $fresh[] = $entry;
            }

            $stat['entries'] = $fresh;
        }

        if ($dry_run)
        {
            return $stat;
        }

        if ( ! $stat['entries'])
        {
            // Записывать нечего: событий нет либо они уже перенесены. Указатель
            // всё равно двигаем — шина прочитана до последнего события, и
            // повторный запуск не станет читать её заново.
            if ($stat['last_id'] > $stat['cursor'])
            {
                $this->_journal->set_cursor($stat['last_id']);
                $stat['cursor'] = $stat['last_id'];
            }

            return $stat;
        }

        $stat['journaled'] = $this->_journal->record_many($stat['entries']);
        $this->_journal->set_cursor($stat['last_id']);
        $stat['cursor'] = $stat['last_id'];

        return $stat;
    }
}
