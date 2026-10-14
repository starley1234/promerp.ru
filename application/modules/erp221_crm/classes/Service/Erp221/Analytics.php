<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Аналитика продаж (erp221_crm): воронка, динамика, менеджеры, источники.
 *
 * Все цифры считаются по сделкам и стадиям самого модуля; клиенты берутся у
 * erp301. Прогноз — это сумма сделок, взвешенная по вероятности стадии: рабочая
 * оценка, а не бухгалтерская выручка, и в интерфейсе это написано прямо.
 *
 * @package    Erp221
 * @category   Services
 */
class Service_Erp221_Analytics
{
    /** @var Service_Erp221_Analytics|null */
    protected static $_instance;

    /**
     * @return  Service_Erp221_Analytics
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Ключевые цифры за период.
     *
     * @param   array  $filters  from, to, assigned_to, source
     * @return  array
     */
    public function overview(array $filters = array())
    {
        $query = $this->query($filters);

        $row = $query->select(
                array(DB::expr("COUNT(*) FILTER (WHERE s.kind = 'open')"), 'open_count'),
                array(DB::expr("COALESCE(SUM(d.amount) FILTER (WHERE s.kind = 'open'), 0)"), 'open_amount'),
                array(DB::expr("COALESCE(SUM(d.amount * COALESCE(d.probability, s.probability) / 100.0) FILTER (WHERE s.kind = 'open'), 0)"), 'forecast'),
                array(DB::expr("COUNT(*) FILTER (WHERE s.kind = 'won')"), 'won_count'),
                array(DB::expr("COALESCE(SUM(d.amount) FILTER (WHERE s.kind = 'won'), 0)"), 'won_amount'),
                array(DB::expr("COUNT(*) FILTER (WHERE s.kind = 'lost')"), 'lost_count'),
                array(DB::expr("COALESCE(SUM(d.amount) FILTER (WHERE s.kind = 'lost'), 0)"), 'lost_amount'),
                array(DB::expr("COALESCE(AVG(EXTRACT(EPOCH FROM (d.closed_at - d.created_at)) / 86400) FILTER (WHERE s.kind <> 'open' AND d.closed_at IS NOT NULL), 0)"), 'cycle_days')
            )
            ->execute()->current();

        $overview = array(
            'open_count'  => (int) $row['open_count'],
            'open_amount' => (float) $row['open_amount'],
            'forecast'    => (float) $row['forecast'],
            'won_count'   => (int) $row['won_count'],
            'won_amount'  => (float) $row['won_amount'],
            'lost_count'  => (int) $row['lost_count'],
            'lost_amount' => (float) $row['lost_amount'],
            'cycle_days'  => round((float) $row['cycle_days'], 1),
        );

        $closed = $overview['won_count'] + $overview['lost_count'];

        $overview['conversion']   = $closed > 0 ? round($overview['won_count'] * 100 / $closed, 1) : NULL;
        $overview['average_deal'] = $overview['won_count'] > 0
            ? round($overview['won_amount'] / $overview['won_count'], 2)
            : 0;

        return $overview;
    }

    /**
     * Воронка: сколько сделок и денег стоит на каждой стадии.
     *
     * @param   array  $filters
     * @return  array
     */
    public function funnel(array $filters = array())
    {
        $rows = $this->query($filters)
            ->select(
                's.id', 's.code', 's.label', 's.kind', 's.probability', 's.sort', 's.color',
                array(DB::expr('COUNT(d.id)'), 'count'),
                array(DB::expr('COALESCE(SUM(d.amount), 0)'), 'amount'),
                array(DB::expr('COALESCE(SUM(d.amount * COALESCE(d.probability, s.probability) / 100.0), 0)'), 'forecast')
            )
            ->group_by('s.id', 's.code', 's.label', 's.kind', 's.probability', 's.sort', 's.color')
            ->order_by('s.sort', 'ASC')
            ->execute()->as_array();

        $stages  = array();
        $entered = NULL;

        foreach ($rows as $row)
        {
            $count = (int) $row['count'];

            // Конверсия считается «от предыдущей стадии»: сколько дошло дальше.
            $conversion = $entered === NULL || $entered === 0 ? NULL : round($count * 100 / $entered, 1);

            $stages[] = array(
                'id'          => (int) $row['id'],
                'code'        => $row['code'],
                'label'       => $row['label'],
                'kind'        => $row['kind'],
                'probability' => (int) $row['probability'],
                'color'       => $row['color'],
                'count'       => $count,
                'amount'      => (float) $row['amount'],
                'forecast'    => (float) $row['forecast'],
                'conversion'  => $conversion,
            );

            $entered = $count;
        }

        return $stages;
    }

    /**
     * Динамика по месяцам: создано и закрыто сделок.
     *
     * @param   integer $months
     * @return  array
     */
    public function dynamics($months = 6)
    {
        $months = max(1, min(24, (int) $months));

        $rows = DB::select(
                array(DB::expr("to_char(date_trunc('month', d.created_at), 'YYYY-MM')"), 'month'),
                array(DB::expr('COUNT(*)'), 'created'),
                array(DB::expr("COUNT(*) FILTER (WHERE s.kind = 'won')"), 'won'),
                array(DB::expr("COALESCE(SUM(d.amount) FILTER (WHERE s.kind = 'won'), 0)"), 'won_amount')
            )
            ->from(array('erp_221_deals', 'd'))
            ->join(array('erp_221_stages', 's'), 'LEFT')->on('s.id', '=', 'd.stage_id')
            ->where('d.is_deleted', '=', FALSE)
            ->where('d.created_at', '>=', DB::expr("date_trunc('month', CURRENT_DATE) - INTERVAL '".($months - 1)." months'"))
            ->group_by(DB::expr("date_trunc('month', d.created_at)"))
            ->order_by(DB::expr("date_trunc('month', d.created_at)"), 'ASC')
            ->execute()->as_array();

        $result = array();

        foreach ($rows as $row)
        {
            $result[] = array(
                'month'      => $row['month'],
                'created'    => (int) $row['created'],
                'won'        => (int) $row['won'],
                'won_amount' => (float) $row['won_amount'],
            );
        }

        return $result;
    }

    /**
     * Работа менеджеров: у кого сколько сделок и денег.
     *
     * @param   array  $filters
     * @return  array
     */
    public function managers(array $filters = array())
    {
        $rows = $this->query($filters)
            ->select(
                'd.assigned_to',
                'u.username',
                array(DB::expr("COUNT(*) FILTER (WHERE s.kind = 'open')"), 'open_count'),
                array(DB::expr("COALESCE(SUM(d.amount) FILTER (WHERE s.kind = 'open'), 0)"), 'open_amount'),
                array(DB::expr("COUNT(*) FILTER (WHERE s.kind = 'won')"), 'won_count'),
                array(DB::expr("COALESCE(SUM(d.amount) FILTER (WHERE s.kind = 'won'), 0)"), 'won_amount'),
                array(DB::expr("COUNT(*) FILTER (WHERE s.kind = 'lost')"), 'lost_count')
            )
            ->join(array('users', 'u'), 'LEFT')->on('u.id', '=', 'd.assigned_to')
            ->group_by('d.assigned_to', 'u.username')
            ->order_by(DB::expr("COALESCE(SUM(d.amount) FILTER (WHERE s.kind = 'won'), 0)"), 'DESC')
            ->execute()->as_array();

        $result = array();

        foreach ($rows as $row)
        {
            $closed = (int) $row['won_count'] + (int) $row['lost_count'];

            $result[] = array(
                'assigned_to' => $row['assigned_to'] === NULL ? NULL : (int) $row['assigned_to'],
                'username'    => $row['username'] ? $row['username'] : 'Не назначен',
                'open_count'  => (int) $row['open_count'],
                'open_amount' => (float) $row['open_amount'],
                'won_count'   => (int) $row['won_count'],
                'won_amount'  => (float) $row['won_amount'],
                'lost_count'  => (int) $row['lost_count'],
                'conversion'  => $closed > 0 ? round((int) $row['won_count'] * 100 / $closed, 1) : NULL,
            );
        }

        return $result;
    }

    /**
     * Источники обращений: откуда приходят сделки.
     *
     * @param   array  $filters
     * @return  array
     */
    public function sources(array $filters = array())
    {
        $rows = $this->query($filters)
            ->select(
                array(DB::expr("COALESCE(NULLIF(d.source, ''), 'не указан')"), 'source'),
                array(DB::expr('COUNT(*)'), 'count'),
                array(DB::expr("COALESCE(SUM(d.amount) FILTER (WHERE s.kind = 'won'), 0)"), 'won_amount')
            )
            ->group_by(DB::expr("COALESCE(NULLIF(d.source, ''), 'не указан')"))
            ->order_by(DB::expr('COUNT(*)'), 'DESC')
            ->execute()->as_array();

        $result = array();

        foreach ($rows as $row)
        {
            $result[] = array(
                'source'     => $row['source'],
                'count'      => (int) $row['count'],
                'won_amount' => (float) $row['won_amount'],
            );
        }

        return $result;
    }

    /**
     * Причины отказов с суммами.
     *
     * @param   array  $filters
     * @return  array
     */
    public function loss_reasons(array $filters = array())
    {
        $filters['kind'] = 'lost';

        $rows = $this->query($filters)
            ->select(
                'r.id', 'r.code', 'r.label',
                array(DB::expr('COUNT(*)'), 'count'),
                array(DB::expr('COALESCE(SUM(d.amount), 0)'), 'amount')
            )
            ->join(array('erp_221_loss_reasons', 'r'), 'LEFT')->on('r.id', '=', 'd.loss_reason_id')
            ->group_by('r.id', 'r.code', 'r.label')
            ->order_by(DB::expr('COUNT(*)'), 'DESC')
            ->execute()->as_array();

        $result = array();

        foreach ($rows as $row)
        {
            $result[] = array(
                'id'     => $row['id'] === NULL ? NULL : (int) $row['id'],
                'code'   => $row['code'],
                'label'  => $row['label'] ? $row['label'] : 'Причина не указана',
                'count'  => (int) $row['count'],
                'amount' => (float) $row['amount'],
            );
        }

        return $result;
    }

    /**
     * Базовый запрос аналитики: сделки без удалённых со стадией.
     *
     * @param   array  $filters  from, to, assigned_to, source, counterparty
     * @return  Database_Query_Builder_Select
     */
    protected function query(array $filters)
    {
        $query = DB::select()
            ->from(array('erp_221_deals', 'd'))
            ->join(array('erp_221_stages', 's'), 'LEFT')->on('s.id', '=', 'd.stage_id')
            ->where('d.is_deleted', '=', FALSE);

        if ( ! empty($filters['from']))
        {
            $query->where('d.created_at', '>=', (string) $filters['from']);
        }

        if ( ! empty($filters['to']))
        {
            $query->where('d.created_at', '<=', (string) $filters['to'].' 23:59:59');
        }

        if ( ! empty($filters['assigned_to']))
        {
            $query->where('d.assigned_to', '=', (int) $filters['assigned_to']);
        }

        if ( ! empty($filters['source']))
        {
            $query->where('d.source', '=', (string) $filters['source']);
        }

        if ( ! empty($filters['counterparty']))
        {
            $query->where('d.counterparty_id', '=', (int) $filters['counterparty']);
        }

        if ( ! empty($filters['kind']))
        {
            $query->where('s.kind', '=', (string) $filters['kind']);
        }

        return $query;
    }
}
