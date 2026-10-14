<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Сводка аналитики: один экран для руководителя (erp801_analytics).
 *
 * Собирает KPI четырёх блоков (запасы, производство, качество, закупки) и
 * список того, что требует внимания. Каждый блок считается независимо: если
 * источник выключен или таблицы соседа ещё не мигрированы, блок помечается
 * «недоступен», а остальные показываются. Экран, который при отказе одного
 * источника показывает нули, опаснее пустого экрана: ноль выглядит как факт.
 *
 * KPI всегда идёт с целью и состоянием (`ok` / `warn` / `bad` / `unknown`):
 * «покрытие 12 дней» без цели — это не показатель, а число.
 *
 * @package    Erp801
 * @category   Services
 */
class Service_Erp801_Summary
{
    /** @var Service_Erp801_Summary|null */
    protected static $_instance;

    /** @var Service_Erp801_Sources */
    protected $_sources;

    /** @var Service_Erp801_Settings */
    protected $_settings;

    /**
     * @param   Service_Erp801_Sources|null   $sources
     * @param   Service_Erp801_Settings|null  $settings
     */
    public function __construct(Service_Erp801_Sources $sources = NULL, Service_Erp801_Settings $settings = NULL)
    {
        $this->_sources  = $sources ? $sources : Service_Erp801_Sources::instance();
        $this->_settings = $settings ? $settings : Service_Erp801_Settings::instance();
    }

    /**
     * @return  Service_Erp801_Summary
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    /**
     * Сводка целиком.
     *
     * @param   array   $filters  from, to, warehouse_id, center_id, limit
     * @return  array
     */
    public function build(array $filters = array())
    {
        $blocks = array(
            'stock'      => $this->_block('stock', $filters),
            'production' => $this->_block('production', $filters),
            'quality'    => $this->_block('quality', $filters),
            'purchases'  => $this->_block('purchases', $filters),
        );

        $kpi       = $this->_kpi($blocks);
        $attention = $this->_attention($blocks);

        return array(
            'generated_at' => date('c'),
            'filters'      => $this->_period($filters),
            'kpi'          => $kpi,
            'attention'    => $attention,
            'blocks'       => $blocks,
            'sources'      => $this->_sources->available(),
        );
    }

    /**
     * Период сводки одним объектом (для формы и снимка).
     *
     * @param   array   $filters
     * @return  array
     */
    protected function _period(array $filters)
    {
        $period = array();

        foreach (array('from', 'to', 'warehouse_id', 'center_id') as $key)
        {
            if (isset($filters[$key]) AND $filters[$key] !== NULL AND $filters[$key] !== '')
            {
                $period[$key] = $filters[$key];
            }
        }

        return $period;
    }

    /**
     * Один блок сводки.
     *
     * @param   string  $code
     * @param   array   $filters
     * @return  array
     */
    protected function _block($code, array $filters)
    {
        try
        {
            switch ($code)
            {
                case 'stock':
                    $service = Service_Erp801_Stock::instance();
                    $summary = $service->summary($filters);
                    $deficit = $service->deficit($filters + array('limit' => 50));

                    return array(
                        'available'    => TRUE,
                        'code'         => $code,
                        'title'        => 'Запасы',
                        'summary'      => $summary,
                        'deficit_rows' => array_slice($deficit['rows'], 0, 5),
                        'deficit'      => array(
                            'items'        => $deficit['items'],
                            'out_items'    => $deficit['out_items'],
                            'shortage_sum' => $deficit['shortage_sum'],
                        ),
                        'url'          => '/analytics/stock',
                    );

                case 'production':
                    $summary = Service_Erp801_Production::instance()->summary($filters);

                    return array(
                        'available' => TRUE,
                        'code'      => $code,
                        'title'     => 'Производство',
                        'summary'   => $summary,
                        'url'       => '/analytics/production',
                    );

                case 'quality':
                    $summary = Service_Erp801_Quality::instance()->summary($filters);

                    return array(
                        'available' => TRUE,
                        'code'      => $code,
                        'title'     => 'Качество',
                        'summary'   => $summary,
                        'url'       => '/analytics/quality',
                    );

                case 'purchases':
                    $summary = Service_Erp801_Purchases::instance()->summary($filters);

                    return array(
                        'available' => TRUE,
                        'code'      => $code,
                        'title'     => 'Закупки',
                        'summary'   => $summary,
                        'url'       => '/analytics/purchases',
                    );
            }
        }
        catch (Exception_Erp801_Analytics $e)
        {
            // Недоступный источник — не ошибка экрана, а состояние витрины.
            return array(
                'available' => FALSE,
                'code'      => $code,
                'title'     => $this->_title($code),
                'reason'    => $e->reason(),
                'message'   => $e->getMessage(),
            );
        }

        return array('available' => FALSE, 'code' => $code, 'title' => $this->_title($code), 'reason' => 'unknown');
    }

    /**
     * Подпись блока.
     *
     * @param   string  $code
     * @return  string
     */
    protected function _title($code)
    {
        $titles = array(
            'stock'      => 'Запасы',
            'production' => 'Производство',
            'quality'    => 'Качество',
            'purchases'  => 'Закупки',
        );

        return isset($titles[$code]) ? $titles[$code] : $code;
    }

    /**
     * KPI сводки: значение, цель, состояние и куда идти за подробностями.
     *
     * @param   array   $blocks
     * @return  array
     */
    protected function _kpi(array $blocks)
    {
        $kpi = array();

        if ($blocks['stock']['available'])
        {
            $stock = $blocks['stock']['summary'];

            $kpi[] = array(
                'code'   => 'cover_days',
                'title'  => 'Покрытие запасов',
                'value'  => $stock['cover_days'],
                'unit'   => 'дней',
                'target' => $stock['cover_target'],
                'state'  => $stock['cover_state'],
                'hint'   => 'Хватит ли запаса при текущем темпе расхода',
                'url'    => '/analytics/stock',
            );

            $kpi[] = array(
                'code'   => 'dead_stock',
                'title'  => 'Мёртвый запас',
                'value'  => $stock['dead_stock'],
                'unit'   => 'ед.',
                'target' => 0,
                'state'  => ((int) $stock['items'] === 0) ? 'unknown' : (((float) $stock['dead_stock'] <= 0) ? 'ok' : 'warn'),
                'hint'   => 'Остаток без расхода дольше '.$this->_settings->get('dead_stock_days', 90).' дней ('.$stock['dead_items'].' поз.)',
                'url'    => '/analytics/stock?class=dead',
            );

            $kpi[] = array(
                'code'   => 'deficit_items',
                'title'  => 'Позиций к дозаказу',
                'value'  => $blocks['stock']['deficit']['items'],
                'unit'   => 'поз.',
                'target' => 0,
                'state'  => ((int) $blocks['stock']['deficit']['items'] === 0) ? 'ok' : 'warn',
                'hint'   => 'Покрытие ниже цели или остаток ниже минимума',
                'url'    => '/analytics/stock?tab=deficit',
            );
        }

        if ($blocks['production']['available'])
        {
            $production = $blocks['production']['summary'];

            $kpi[] = array(
                'code'   => 'oee',
                'title'  => 'OEE',
                'value'  => $production['oee'],
                'unit'   => '%',
                'target' => $production['oee_target'],
                'state'  => $production['oee_state'],
                'hint'   => 'Доступность × производительность × качество',
                'url'    => '/analytics/production',
            );

            $kpi[] = array(
                'code'   => 'plan_complete',
                'title'  => 'Выполнение плана выпуска',
                'value'  => $production['plan_complete'],
                'unit'   => '%',
                'target' => 100,
                'state'  => $this->_higher($production['plan_complete'], 100),
                'hint'   => 'Годного к плановому количеству',
                'url'    => '/analytics/production',
            );
        }

        if ($blocks['quality']['available'])
        {
            $quality = $blocks['quality']['summary'];

            $kpi[] = array(
                'code'   => 'defect_share',
                'title'  => 'Забракованные контроли',
                'value'  => $quality['defect_share'],
                'unit'   => '%',
                'target' => $quality['target'],
                'state'  => $quality['state'],
                'hint'   => 'Доля контролей с итогом «не прошёл»',
                'url'    => '/analytics/quality',
            );
        }

        if ($blocks['purchases']['available'])
        {
            $purchases = $blocks['purchases']['summary'];

            $kpi[] = array(
                'code'   => 'overdue_share',
                'title'  => 'Просроченные поставки',
                'value'  => $purchases['overdue_share'],
                'unit'   => '%',
                'target' => $purchases['target'],
                'state'  => $purchases['state'],
                'hint'   => $purchases['overdue'].' из '.$purchases['open'].' открытых заказов',
                'url'    => '/analytics/purchases',
            );
        }

        return $kpi;
    }

    /**
     * «Что требует внимания»: короткие факты со ссылкой на источник.
     *
     * @param   array   $blocks
     * @return  array
     */
    protected function _attention(array $blocks)
    {
        $rows = array();

        if ($blocks['stock']['available'])
        {
            $stock = $blocks['stock']['summary'];

            if ($stock['out_items'] > 0)
            {
                $rows[] = array(
                    'state' => 'bad',
                    'title' => 'Позиции без запаса при наличии расхода',
                    'value' => $stock['out_items'].' поз.',
                    'hint'  => 'Склад пуст, а расход за период есть — срыв производства или отгрузки',
                    'url'   => '/analytics/stock?tab=deficit',
                );
            }

            if ((float) $stock['dead_stock'] > 0)
            {
                $rows[] = array(
                    'state' => 'warn',
                    'title' => 'Мёртвый запас',
                    'value' => $stock['dead_stock'].' ед.',
                    'hint'  => $stock['dead_items'].' позиций без расхода дольше '.$this->_settings->get('dead_stock_days', 90).' дней',
                    'url'   => '/analytics/stock?class=dead',
                );
            }

            if ($stock['below_min'] > 0)
            {
                $rows[] = array(
                    'state' => 'warn',
                    'title' => 'Остатки ниже минимума',
                    'value' => $stock['below_min'].' поз.',
                    'hint'  => 'Минимум склада пробит — повод посмотреть заявки',
                    'url'   => '/analytics/stock?tab=deficit',
                );
            }
        }

        if ($blocks['production']['available'])
        {
            $production = $blocks['production']['summary'];

            if ($production['top_downtime'])
            {
                $top = $production['top_downtime'];

                $rows[] = array(
                    'state' => ((float) $top['share'] >= 30) ? 'warn' : 'ok',
                    'title' => 'Основной простой: '.$top['reason_label'],
                    'value' => $top['hours'].' ч ('.$top['share'].'%)',
                    'hint'  => 'Парето простоев за период — с этого разбора начинать',
                    'url'   => '/analytics/production',
                );
            }

            if ((float) $production['scrap_share'] > 0)
            {
                $rows[] = array(
                    'state' => ((float) $production['scrap_share'] > (float) $this->_settings->get('defect_share_target', 5)) ? 'warn' : 'ok',
                    'title' => 'Брак в цехе',
                    'value' => $production['scrap_share'].'%',
                    'hint'  => 'Доля брака от выпуска по операциям',
                    'url'   => '/analytics/production',
                );
            }

            if ((int) $production['late'] > 0)
            {
                $rows[] = array(
                    'state' => 'warn',
                    'title' => 'Операции с опозданием',
                    'value' => $production['late'].' из '.$production['done'],
                    'hint'  => 'Завершены позже планового дня',
                    'url'   => '/analytics/production',
                );
            }
        }

        if ($blocks['quality']['available'])
        {
            $quality = $blocks['quality']['summary'];

            if ($quality['top_cause'])
            {
                $rows[] = array(
                    'state' => ((float) $quality['top_cause']['share'] >= 50) ? 'warn' : 'ok',
                    'title' => 'Основная причина брака: '.$quality['top_cause']['label'],
                    'value' => $quality['top_cause']['share'].'%',
                    'hint'  => 'Доля в общем браке за период',
                    'url'   => '/analytics/quality',
                );
            }

            if ((int) $quality['pending'] > 0)
            {
                $rows[] = array(
                    'state' => 'warn',
                    'title' => 'Ждут решения по браку',
                    'value' => $quality['pending'].' контр.',
                    'hint'  => 'Контроль не прошёл, решения (переделать / вернуть / утиль) нет',
                    'url'   => '/analytics/quality',
                );
            }
        }

        if ($blocks['purchases']['available'])
        {
            $purchases = $blocks['purchases']['summary'];

            if ($purchases['overdue'] > 0)
            {
                $rows[] = array(
                    'state' => ($purchases['state'] === 'ok') ? 'warn' : 'bad',
                    'title' => 'Просроченные заказы поставщикам',
                    'value' => $purchases['overdue'].' из '.$purchases['open'],
                    'hint'  => 'Открытая сумма '.number_format($purchases['amount_open'], 2, '.', ' ').' — деньги в незакрытых поставках',
                    'url'   => '/analytics/purchases',
                );
            }
        }

        return $rows;
    }

    /**
     * Состояние «чем больше, тем лучше».
     *
     * @param   float|null  $value
     * @param   float       $target
     * @return  string
     */
    protected function _higher($value, $target)
    {
        if ($value === NULL)
        {
            return 'unknown';
        }

        if ((float) $value >= $target)
        {
            return 'ok';
        }

        return ((float) $value >= $target * 0.8) ? 'warn' : 'bad';
    }
}
