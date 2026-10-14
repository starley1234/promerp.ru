<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Рабочее место — один экран на вопрос «что делать прямо сейчас».
 *
 * Прототип из `docs/FRONTEND_REVIEW.md` (§3.1), собранный только из компонентов
 * интерфейса (`application/views/components`): карточки, показатели, бейджи
 * состояний и таблицы с подписью, `scope` и прокруткой на узком экране.
 *
 * Ничего не считает: цифры приходят из витрин (аналитика, производство, склад,
 * качество, закупки, заказы, цех). Если витрина недоступна, блок просто не
 * показывается — экран не падает из-за выключенного модуля.
 */
$kpi       = isset($data['kpi']) ? (array) $data['kpi'] : array();
$attention = isset($data['attention']) ? (array) $data['attention'] : array();
$shop      = isset($data['shop']) ? (array) $data['shop'] : array();
$orders    = isset($data['orders']) ? (array) $data['orders'] : array();
$stock     = isset($data['stock']) ? (array) $data['stock'] : array();
$quality   = isset($data['quality']) ? (array) $data['quality'] : array();
$period    = isset($data['period']) ? (array) $data['period'] : array();

$company = $scenario->company();

// Блок цеха может прийти частично (модуль выключен): читаем через isset, чтобы
// экран показал прочерк вместо ошибки.
$shop_oee    = isset($shop['oee']) ? $shop['oee'] : NULL;
$shop_uptime = isset($shop['availability']) ? $shop['availability'] : NULL;
$shop_perf   = isset($shop['performance']) ? $shop['performance'] : NULL;
$shop_target = isset($shop['target']) ? $shop['target'] : NULL;

// Ширина полосы готовности — классами Tailwind, без inline-стилей: инлайн-стили
// в проекте и так в списке долгов (docs/FRONTEND_REVIEW.md, P1-7).
$bar = function ($percent) {
    $percent = max(0, min(100, (int) $percent));

    if ($percent >= 100) return 'w-full';
    if ($percent >= 75)  return 'w-3/4';
    if ($percent >= 50)  return 'w-1/2';
    if ($percent >= 25)  return 'w-1/4';

    return $percent > 0 ? 'w-1/12' : 'w-0';
};

$readable = function ($date) {
    if ( ! $date)
    {
        return '—';
    }

    $time = strtotime((string) $date);

    return $time ? date('d.m.Y', $time) : '—';
};
?>

<div>

    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-gauge-high mr-2 text-blue-600" aria-hidden="true"></i> Рабочее место
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                <?php echo HTML::chars($company['name']); ?> · период <?php echo $readable(isset($period['from']) ? $period['from'] : NULL); ?>
                — <?php echo $readable(isset($period['to']) ? $period['to'] : NULL); ?>
                · цифры из витрин, а не из этой страницы
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="/demo/tour" class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm rounded-md hover:bg-gray-50 dark:hover:bg-gray-700">
                <i class="fas fa-route mr-1" aria-hidden="true"></i> Тур по системе
            </a>
            <a href="/analytics" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                <i class="fas fa-chart-line mr-1" aria-hidden="true"></i> Витрины
            </a>
        </div>
    </div>

    <?php if ($inside): ?>
        <p class="mb-6 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-200" role="status">
            <i class="fas fa-circle-info mr-1" aria-hidden="true"></i>
            Демонстрационный стенд: данные вымышленного производства, изменения не сохраняются.
            Экран собран из тех же витрин, что и рабочая система.
        </p>
    <?php endif; ?>

    <?php if ($kpi): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            <?php foreach (array_slice($kpi, 0, 4) as $row): ?>
                <?php echo Component::render('kpi', array(
                    'label'  => isset($row['title']) ? $row['title'] : 'Показатель',
                    'value'  => isset($row['value']) ? $row['value'] : '—',
                    'unit'   => isset($row['unit']) ? $row['unit'] : '',
                    'hint'   => isset($row['hint']) ? $row['hint'] : NULL,
                    'target' => isset($row['target']) ? $row['target'] : NULL,
                    'state'  => isset($row['state']) ? $row['state'] : 'neutral',
                    'url'    => isset($row['url']) ? $row['url'] : NULL,
                )); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-6">

        <?php
        $attention_rows = array();

        foreach ($attention as $row)
        {
            $attention_rows[] = array(
                Component::render('badge', array(
                    'state' => isset($row['state']) ? $row['state'] : 'neutral',
                )),
                (isset($row['url']) ? '<a href="'.HTML::chars($row['url']).'" class="font-medium text-blue-600 hover:text-blue-800">' : '<span class="font-medium">')
                    .HTML::chars(isset($row['title']) ? $row['title'] : '—')
                    .(isset($row['url']) ? '</a>' : '</span>')
                    .'<div class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars(isset($row['hint']) ? $row['hint'] : '').'</div>',
                '<span class="font-medium tabular-nums">'.HTML::chars(isset($row['value']) ? $row['value'] : '').'</span>',
            );
        }

        Component::begin('card', array(
            'flush'    => TRUE,
            'title'    => 'Требует решения',
            'icon'     => 'fas fa-bell',
            'state'    => $attention ? 'warn' : 'ok',
            'subtitle' => 'Строки внимания из сводки: сначала то, что может сорвать выпуск или отгрузку.',
        ));
        ?>
            <?php echo Component::render('table', array(
                'caption'     => 'Что требует решения: состояние, событие и масштаб',
                'columns'     => array(
                    array('label' => 'Состояние', 'nowrap' => TRUE),
                    array('label' => 'Что произошло'),
                    array('label' => 'Насколько', 'align' => 'right', 'nowrap' => TRUE),
                ),
                'rows'        => $attention_rows,
                'row_header'  => FALSE,
                'empty_title' => 'Ничего не требует решения',
                'empty_hint'  => 'За выбранный период сводка не нашла отклонений: и запас, и план, и качество в норме.',
            )); ?>
        <?php
        Component::end();
        ?>

        <?php
        $operation_rows = array();

        foreach ((array) (isset($shop['operations']) ? $shop['operations'] : array()) as $row)
        {
            $done      = (float) $row['qty_ok'];
            $planned   = (float) $row['qty_plan'];
            $percent   = $planned > 0 ? (int) round($done / $planned * 100) : 0;
            $is_paused = ($row['status'] === 'paused');

            $operation_rows[] = array(
                '<span class="font-medium text-gray-900 dark:text-white">'.HTML::chars($row['number']).'</span>'
                    .'<div class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($row['order']).'</div>',
                HTML::chars($row['name'])
                    .'<div class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($row['center']).'</div>',
                '<div class="text-right tabular-nums">'.number_format($done, 0, ',', ' ').' / '.number_format($planned, 0, ',', ' ').'</div>'
                    .'<div class="mt-1 h-1.5 w-full rounded bg-gray-200 dark:bg-gray-700" role="img" aria-label="Готовность '.$percent.' процентов">'
                    .'<div class="h-1.5 rounded bg-blue-600 '.$bar($percent).'"></div></div>',
                $is_paused
                    ? Component::render('badge', array('label' => 'стоит', 'state' => 'warn', 'title' => 'Операция приостановлена — нужен простой в журнале'))
                    : Component::render('badge', array('label' => 'печатается', 'state' => 'info')),
            );
        }

        Component::begin('card', array(
            'flush'    => TRUE,
            'title'    => 'Цех сейчас',
            'icon'     => 'fas fa-print',
            'subtitle' => 'Задания в работе: что печатается, на каком станке и сколько осталось.',
            'state'    => isset($shop['state']) ? $shop['state'] : NULL,
            'actions_html' => '<a href="/shopfloor" class="text-xs text-blue-600 hover:text-blue-800">терминал цеха</a>',
        ));
        ?>
            <?php echo Component::render('table', array(
                'caption'     => 'Операции в работе',
                'columns'     => array(
                    array('label' => 'Задание', 'nowrap' => TRUE),
                    array('label' => 'Операция'),
                    array('label' => 'Готово / план', 'align' => 'right', 'nowrap' => TRUE),
                    array('label' => 'Состояние', 'nowrap' => TRUE, 'priority' => 3),
                ),
                'rows'        => $operation_rows,
                'row_header'  => FALSE,
                'empty_title' => 'Печатные задания не стоят на станках',
                'empty_hint'  => 'Все выданные операции закрыты: очередь цеха пуста, можно планировать следующую партию.',
            )); ?>
        <?php
        Component::end();
        ?>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-6">

        <?php
        $order_rows = array();

        foreach ((array) (isset($orders['rows']) ? $orders['rows'] : array()) as $row)
        {
            $labels = array(
                'planned'     => array('label' => 'план', 'state' => 'neutral'),
                'released'    => array('label' => 'к запуску', 'state' => 'info'),
                'in_progress' => array('label' => 'в печати', 'state' => 'info'),
            );

            $status  = isset($labels[$row['status']]) ? $labels[$row['status']] : array('label' => $row['status'], 'state' => 'neutral');
            $overdue = ($row['due'] AND strtotime($row['due']) < time());

            $order_rows[] = array(
                '<a href="/production/orders/view/'.(int) preg_replace('/\D/', '', $row['number']).'" class="font-medium text-blue-600 hover:text-blue-800">'.HTML::chars($row['number']).'</a>'
                    .'<div class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($row['item']).'</div>',
                Component::render('badge', array('label' => $status['label'], 'state' => $status['state'])),
                '<div class="text-right tabular-nums">'.number_format($row['planned'], 0, ',', ' ').'</div>'
                    .'<div class="text-xs text-gray-500 dark:text-gray-400 text-right">'.HTML::chars($row['uom']).'</div>',
                '<div class="text-right tabular-nums">'.number_format($row['done'], 0, ',', ' ').'</div>'
                    .'<div class="mt-1 h-1.5 w-full rounded bg-gray-200 dark:bg-gray-700" role="img" aria-label="Готовность '.$row['progress'].' процентов">'
                    .'<div class="h-1.5 rounded bg-emerald-600 '.$bar($row['progress']).'"></div></div>',
                $overdue
                    ? '<span class="'.Component::state_class('bad', 'text').'">'.$readable($row['due']).'</span>'
                    : $readable($row['due']),
            );
        }

        $order_stats = isset($orders['stats']) ? (array) $orders['stats'] : array();

        Component::begin('card', array(
            'flush'    => TRUE,
            'title'    => 'Заказы в работе',
            'icon'     => 'fas fa-clipboard-list',
            'subtitle' => $order_stats
                ? 'Всего заказов: '.number_format(isset($order_stats['total']) ? (int) $order_stats['total'] : 0, 0, ',', ' ').', из них в работе — '
                    .number_format(count($order_rows), 0, ',', ' ').'.'
                : 'Что обещано клиенту и сколько уже напечатано.',
            'actions_html' => '<a href="/production/orders" class="text-xs text-blue-600 hover:text-blue-800">все заказы</a>',
        ));
        ?>
            <?php echo Component::render('table', array(
                'caption'     => 'Производственные заказы в работе',
                'columns'     => array(
                    array('label' => 'Заказ', 'nowrap' => TRUE),
                    array('label' => 'Состояние', 'nowrap' => TRUE, 'priority' => 2),
                    array('label' => 'План', 'align' => 'right', 'nowrap' => TRUE, 'priority' => 3),
                    array('label' => 'Напечатано', 'align' => 'right', 'nowrap' => TRUE),
                    array('label' => 'Срок', 'align' => 'right', 'nowrap' => TRUE),
                ),
                'rows'        => $order_rows,
                'row_header'  => FALSE,
                'empty_title' => 'Открытых заказов нет',
                'empty_hint'  => 'Все заказы закрыты или отменены — самое время принять новые.',
            )); ?>
        <?php
        Component::end();
        ?>

        <?php
        $stock_rows = array();

        foreach ((array) (isset($stock['rows']) ? $stock['rows'] : array()) as $row)
        {
            $cover = $row['cover'];

            $stock_rows[] = array(
                '<span class="font-medium text-gray-900 dark:text-white">'.HTML::chars($row['code']).'</span>'
                    .'<div class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($row['name']).'</div>',
                '<span class="tabular-nums">'.number_format($row['stock'], 3, ',', ' ').'</span>'
                    .'<div class="text-xs text-gray-500 dark:text-gray-400">минимум '.number_format($row['min_qty'], 3, ',', ' ').'</div>',
                $cover === NULL
                    ? '<span class="text-gray-400">нет расхода</span>'
                    : '<span class="'.Component::state_class(((float) $cover < 7) ? 'bad' : 'warn', 'text').'">'.number_format((float) $cover, 0, ',', ' ').' дн.</span>',
                Component::render('badge', array(
                    'label' => $row['reasons'] ? $row['reasons'] : 'запас',
                    'state' => 'warn',
                )),
            );
        }

        $totals = isset($stock['totals']) ? (array) $stock['totals'] : array();

        Component::begin('card', array(
            'flush'    => TRUE,
            'title'    => 'Склад: чего не хватает',
            'icon'     => 'fas fa-boxes',
            'state'    => $stock_rows ? 'warn' : 'ok',
            'subtitle' => 'Остаток ниже минимума или покрытие меньше цели: позиций — '
                .number_format(isset($stock['items']) ? (int) $stock['items'] : 0, 0, ',', ' ')
                .'. Единицы у позиций разные (кг и шт.), поэтому общая сумма не складывается.',
            'actions_html' => '<a href="/warehouse/balances" class="text-xs text-blue-600 hover:text-blue-800">остатки</a>',
        ));
        ?>
            <?php echo Component::render('table', array(
                'caption'     => 'Позиции с дефицитом запаса',
                'columns'     => array(
                    array('label' => 'Позиция'),
                    array('label' => 'Остаток', 'align' => 'right', 'nowrap' => TRUE),
                    array('label' => 'Хватит', 'align' => 'right', 'nowrap' => TRUE),
                    array('label' => 'Почему', 'nowrap' => TRUE, 'priority' => 3),
                ),
                'rows'        => $stock_rows,
                'row_header'  => TRUE,
                'empty_title' => 'Дефицита нет',
                'empty_hint'  => 'Все позиции выше минимума и покрыты запасом на месяц вперёд.',
            )); ?>
        <?php
        Component::end();
        ?>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

        <?php
        $cause_rows = array();

        foreach ((array) (isset($quality['causes']) ? $quality['causes'] : array()) as $row)
        {
            $cause_rows[] = array(
                HTML::chars($row['label']),
                '<span class="tabular-nums">'.number_format($row['qty'], 0, ',', ' ').'</span>',
            );
        }

        Component::begin('card', array(
            'flush'    => TRUE,
            'title'    => 'Качество',
            'icon'     => 'fas fa-clipboard-check',
            'state'    => isset($quality['state']) ? $quality['state'] : 'neutral',
            'subtitle' => isset($quality['share'])
                ? 'Доля брака '.number_format((float) $quality['share'], 1, ',', ' ').' % при цели '.number_format((float) $quality['target'], 1, ',', ' ').' %'
                : 'Качество за период.',
        ));
        ?>
            <?php echo Component::render('table', array(
                'caption'     => 'Причины брака за период',
                'columns'     => array(
                    array('label' => 'Причина'),
                    array('label' => 'Брак, ед.', 'align' => 'right', 'nowrap' => TRUE),
                ),
                'rows'        => $cause_rows,
                'row_header'  => TRUE,
                'empty_title' => 'Брака за период нет',
                'empty_hint'  => 'Все контроли пройдены: причины появятся здесь, как только появится брак.',
            )); ?>
        <?php
        Component::end();
        ?>

        <?php
        $pareto_rows = array();

        foreach ((array) (isset($shop['pareto']) ? $shop['pareto'] : array()) as $row)
        {
            $pareto_rows[] = array(
                HTML::chars($row['label']),
                '<span class="tabular-nums">'.number_format($row['hours'], 1, ',', ' ').' ч</span>',
                '<span class="tabular-nums">'.number_format($row['share'], 1, ',', ' ').' %</span>',
            );
        }

        Component::begin('card', array(
            'flush'    => TRUE,
            'title'    => 'Простои станков',
            'icon'     => 'fas fa-triangle-exclamation',
            'subtitle' => 'Парето за период — с какой причины начинать разбор.',
        ));
        ?>
            <?php echo Component::render('table', array(
                'caption'     => 'Причины простоев по времени',
                'columns'     => array(
                    array('label' => 'Причина'),
                    array('label' => 'Время', 'align' => 'right', 'nowrap' => TRUE),
                    array('label' => 'Доля', 'align' => 'right', 'nowrap' => TRUE, 'priority' => 3),
                ),
                'rows'        => $pareto_rows,
                'row_header'  => TRUE,
                'empty_title' => 'Простоев не было',
                'empty_hint'  => 'Станки работали без остановок — журнал цеха пуст по простоям.',
            )); ?>
        <?php
        Component::end();
        ?>

        <?php
        $plan_fact = isset($shop['plan_fact']) ? (array) $shop['plan_fact'] : array();

        Component::begin('card', array(
            'title'    => 'Печатня в цифрах',
            'icon'     => 'fas fa-chart-simple',
            'subtitle' => 'Что показал период: выпуск, станки, простои.',
        ));
        ?>
            <dl class="space-y-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-gray-500 dark:text-gray-400">Работа станков</dt>
                    <dd class="font-medium tabular-nums text-gray-900 dark:text-white">
                        <?php echo $shop_uptime === NULL ? '—' : number_format($shop_uptime, 1, ',', ' ').' %'; ?>
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-gray-500 dark:text-gray-400">Производительность</dt>
                    <dd class="font-medium tabular-nums text-gray-900 dark:text-white">
                        <?php echo $shop_perf === NULL ? '—' : number_format($shop_perf, 1, ',', ' ').' %'; ?>
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-gray-500 dark:text-gray-400">Годных деталей</dt>
                    <dd class="font-medium tabular-nums text-gray-900 dark:text-white">
                        <?php echo number_format(isset($plan_fact['qty_ok']) ? (float) $plan_fact['qty_ok'] : 0, 0, ',', ' ').' шт.'; ?>
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-gray-500 dark:text-gray-400">Брак при печати</dt>
                    <dd class="font-medium tabular-nums text-gray-900 dark:text-white">
                        <?php echo number_format(isset($plan_fact['qty_scrap']) ? (float) $plan_fact['qty_scrap'] : 0, 0, ',', ' ').' шт.'; ?>
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-gray-500 dark:text-gray-400">Операций закрыто</dt>
                    <dd class="font-medium tabular-nums text-gray-900 dark:text-white">
                        <?php echo number_format(isset($plan_fact['done']) ? (int) $plan_fact['done'] : 0, 0, ',', ' ')
                            .' из '.number_format((isset($plan_fact['done']) ? (int) $plan_fact['done'] : 0) + count((array) (isset($shop['operations']) ? $shop['operations'] : array())), 0, ',', ' '); ?>
                    </dd>
                </div>
            </dl>

            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                OEE <?php echo $shop_oee === NULL ? '—' : number_format($shop_oee, 1, ',', ' ').' %'; ?>
                при цели <?php echo $shop_target === NULL ? '—' : number_format($shop_target, 0, ',', ' ').' %'; ?>.
                Доступность × производительность × качество — формула витрины производства, а не этой страницы.
            </p>
        <?php
        Component::end();
        ?>
    </div>

</div>
