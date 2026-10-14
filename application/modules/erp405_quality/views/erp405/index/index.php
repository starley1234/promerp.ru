<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Качество — доска дня.
 *
 * Страница переведена на компоненты интерфейса
 * (`application/views/components/README.md`). Что изменилось по существу:
 *
 *  * у цифр появились подписи и единицы измерения — раньше «25» и «8» стояли
 *    без единицы, а доля брака печаталась как «16.7 %» без знака после точки;
 *  * состояние (норма/внимание/проблема) считается один раз по порогам и
 *    приходит из одного словаря, а не подбирается цветом в вёрстке;
 *  * у каждой таблицы есть подпись для скринридера, заголовки колонок со
 *    `scope` и прокрутка на узком экране;
 *  * пустые списки объясняют, что произошло, вместо пустого прямоугольника.
 */
$share = (float) $summary['defect_share'];

// Порог доли брака: до 5 % — в пределах нормы, до 15 % — внимание, выше — проблема.
$share_state = $share > 15 ? 'bad' : ($share > 5 ? 'warn' : 'ok');

$pending_state  = ((int) $summary['pending'] > 0) ? 'warn' : 'ok';
$quarantine_qty = (float) $summary['quarantine_qty'];
$quarantine_state = $quarantine_qty > 0 ? 'info' : 'neutral';
?>

<div>

    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-clipboard-check mr-2 text-green-600" aria-hidden="true"></i> Качество
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Контроль партий: что проверили, где брак, что ждёт решения и что стоит на карантине.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="/quality/inspections/edit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                <i class="fas fa-plus-circle mr-1" aria-hidden="true"></i> Новый контроль
            </a>
            <a href="/quality/quarantine" class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm rounded-md hover:bg-gray-50 dark:hover:bg-gray-700">
                <i class="fas fa-box-open mr-1" aria-hidden="true"></i> Карантин
            </a>
        </div>
    </div>

    <?php echo View::factory('erp405/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <?php echo Component::render('kpi', array(
            'label' => 'Контролей всего',
            'value' => number_format((int) $summary['total'], 0, ',', ' '),
            'unit'  => 'пров.',
            'hint'  => 'в этом месяце: '.(int) $summary['this_month'].' пров.',
            'state' => 'neutral',
            'url'   => '/quality/inspections',
        )); ?>

        <?php echo Component::render('kpi', array(
            'label'  => 'Партий с браком',
            'value'  => number_format((int) $summary['failed'], 0, ',', ' '),
            'unit'   => 'партий',
            'state'  => $share_state,
            'target' => '≤ 5 %',
            'hint'   => 'доля брака '.number_format($share, 1, ',', ' ').' % от всех контролей',
            'url'    => '/quality/inspections?result=failed',
        )); ?>

        <?php echo Component::render('kpi', array(
            'label' => 'Ждут решения',
            'value' => number_format((int) $summary['pending'], 0, ',', ' '),
            'unit'  => 'партий',
            'state' => $pending_state,
            'hint'  => $pending_state === 'warn' ? 'по браку ещё не принято решение: списать, переделать или уступить' : 'очередь разобрана',
            'url'   => '/quality/inspections?pending=1',
        )); ?>

        <?php echo Component::render('kpi', array(
            'label' => 'На карантине',
            'value' => number_format($quarantine_qty, 0, ',', ' '),
            'unit'  => 'ед.',
            'state' => $quarantine_state,
            'hint'  => 'брак всего: '.number_format((float) $summary['defect_qty'], 0, ',', ' ').' ед.',
            'url'   => '/quality/quarantine',
        )); ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">

        <?php
        $pending_columns = array(
            array('label' => 'Контроль'),
            array('label' => 'Брак', 'align' => 'right', 'nowrap' => TRUE),
            array('label' => 'Источник', 'align' => 'right', 'priority' => 3),
        );

        $pending_rows = array();

        foreach ($pending as $inspection)
        {
            $pending_rows[] = array(
                '<a href="/quality/inspections/view/'.(int) $inspection->id.'" class="font-medium text-blue-600 hover:text-blue-800">'
                    .HTML::chars($inspection->number).'</a>'
                    .'<div class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($inspection->item_name).'</div>',
                '<span class="font-medium '.Component::state_class('bad', 'text').'">'
                    .number_format((float) $inspection->defect_qty, 3, '.', ' ').'</span>'
                    .' '.Component::render('badge', array('label' => 'из '.number_format((float) $inspection->qty, 0, ',', ' ').' шт.', 'state' => 'neutral')),
                HTML::chars($inspection->source_label()),
            );
        }

        Component::begin('card', array(
            'flush'  => TRUE,
            'title'  => 'Ждут решения',
            'icon'   => 'fas fa-hourglass-half',
            'state'  => $pending_state === 'warn' ? 'warn' : NULL,
            'actions_html' => '<a href="/quality/inspections?pending=1" class="text-xs text-blue-600 hover:text-blue-800">все</a>',
        ));
        ?>
            <?php echo Component::render('table', array(
                'caption'     => 'Контроли, ожидающие решения по браку',
                'columns'     => $pending_columns,
                'rows'        => $pending_rows,
                'row_header'  => TRUE,
                'empty_title' => 'Очередь пуста',
                'empty_hint'  => 'Брак либо разобран, либо его нет. Хороший день.',
            )); ?>
        <?php
        Component::end();
        ?>

        <?php
        $cause_columns = array(
            array('label' => 'Где теряем качество'),
            array('label' => 'Брак, ед.', 'align' => 'right', 'nowrap' => TRUE),
            array('label' => 'Контролей', 'align' => 'right', 'nowrap' => TRUE, 'priority' => 3),
        );

        $cause_rows = array();

        foreach ($by_cause as $row)
        {
            $cause_rows[] = array(
                HTML::chars(isset(Model_Erp405_Defect::$causes[$row['cause']]) ? Model_Erp405_Defect::$causes[$row['cause']] : $row['cause']),
                number_format((float) $row['qty'], 3, '.', ' '),
                number_format((int) $row['inspections'], 0, ',', ' '),
            );
        }

        Component::begin('card', array(
            'flush'    => TRUE,
            'title'    => 'Где теряем качество',
            'subtitle' => 'Количество брака по причинам — с кем разговаривать: с поставщиком, с цехом или со складом.',
        ));
        ?>
            <?php echo Component::render('table', array(
                'caption'     => 'Брак по причинам',
                'columns'     => $cause_columns,
                'rows'        => $cause_rows,
                'row_header'  => TRUE,
                'empty_title' => 'Дефектов пока нет',
                'empty_hint'  => 'Как только появится забракованный контроль, причины соберутся здесь.',
            )); ?>
        <?php
        Component::end();
        ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        <?php
        $code_rows = array();

        foreach ($by_code as $row)
        {
            $code_rows[] = array(
                '<span class="font-medium">'.HTML::chars($row['code'] ? $row['code'] : 'без кода').'</span>'
                    .'<span class="block text-xs text-gray-500 dark:text-gray-400">'.HTML::chars(isset($row['name']) ? $row['name'] : '').'</span>',
                number_format((float) $row['qty'], 3, '.', ' '),
            );
        }

        Component::begin('card', array('flush' => TRUE, 'title' => 'Частые дефекты'));
        ?>
            <?php echo Component::render('table', array(
                'caption'     => 'Самые частые коды дефектов',
                'columns'     => array(
                    array('label' => 'Дефект'),
                    array('label' => 'Количество, ед.', 'align' => 'right', 'nowrap' => TRUE),
                ),
                'rows'        => $code_rows,
                'empty_title' => 'Нет данных о дефектах',
                'empty_hint'  => 'Коды дефектов заполняются при регистрации брака.',
            )); ?>
        <?php
        Component::end();
        ?>

        <?php
        $supplier_rows = array();

        foreach ($by_supplier as $row)
        {
            $supplier_rows[] = array(
                HTML::chars($row['name'] ? $row['name'] : 'Поставщик #'.(int) $row['supplier_id']),
                '<span class="font-medium '.Component::state_class('bad', 'text').'">'.number_format((float) $row['qty'], 3, '.', ' ').'</span>',
            );
        }

        Component::begin('card', array(
            'flush'    => TRUE,
            'title'    => 'Поставщики и брак',
            'subtitle' => 'Кому предъявлять претензию по качеству поставки.',
        ));
        ?>
            <?php echo Component::render('table', array(
                'caption'     => 'Брак по поставщикам',
                'columns'     => array(
                    array('label' => 'Поставщик'),
                    array('label' => 'Брак, ед.', 'align' => 'right', 'nowrap' => TRUE),
                ),
                'rows'        => $supplier_rows,
                'empty_title' => 'Претензий к поставщикам нет',
                'empty_hint'  => 'Брак по входному контролю с привязкой к поставщику сюда и попадёт.',
            )); ?>
        <?php
        Component::end();
        ?>

        <?php
        $month_rows = array();

        foreach ($by_month as $row)
        {
            $failed = (int) $row['failed'];

            $month_rows[] = array(
                HTML::chars($row['period']),
                number_format((int) $row['inspections'], 0, ',', ' '),
                '<span class="font-medium '.Component::state_class($failed > 0 ? 'warn' : 'ok', 'text').'">'.number_format($failed, 0, ',', ' ').'</span>',
            );
        }

        Component::begin('card', array(
            'flush'    => TRUE,
            'title'    => 'Динамика по месяцам',
            'subtitle' => 'Проверки и забракованные партии за последние полгода.',
        ));
        ?>
            <?php echo Component::render('table', array(
                'caption'     => 'Контроли и брак по месяцам',
                'columns'     => array(
                    array('label' => 'Месяц'),
                    array('label' => 'Проверок', 'align' => 'right', 'nowrap' => TRUE),
                    array('label' => 'Брак, партий', 'align' => 'right', 'nowrap' => TRUE),
                ),
                'rows'        => $month_rows,
                'empty_title' => 'Данных по месяцам нет',
                'empty_hint'  => 'График появится после первых зарегистрированных контролей.',
            )); ?>
        <?php
        Component::end();
        ?>
    </div>

    <?php
    $quarantine_rows = array();

    foreach ($quarantine as $row)
    {
        $quarantine_rows[] = array(
            '<a href="/quality/inspections/view/'.(int) $row['id'].'" class="font-medium text-blue-600 hover:text-blue-800">'
                .HTML::chars($row['number']).'</a>',
            HTML::chars($row['item_name']),
            '<span class="font-medium '.Component::state_class('info', 'text').'">'.number_format((float) $row['quarantine_qty'], 3, '.', ' ').'</span>',
        );
    }
    ?>

    <div class="mt-6">
        <?php Component::begin('card', array(
            'flush'  => TRUE,
            'title'  => 'Сейчас на карантине',
            'icon'   => 'fas fa-box-open',
            'state'  => 'info',
            'subtitle' => 'Партии изолированы: пока решение не принято, в производство они не уйдут.',
            'actions_html' => '<a href="/quality/quarantine" class="text-xs text-blue-600 hover:text-blue-800">подробнее</a>',
        )); ?>
            <?php echo Component::render('table', array(
                'caption'     => 'Партии на карантине',
                'columns'     => array(
                    array('label' => 'Контроль'),
                    array('label' => 'Позиция'),
                    array('label' => 'На карантине, ед.', 'align' => 'right', 'nowrap' => TRUE),
                ),
                'rows'        => $quarantine_rows,
                'row_header'  => TRUE,
                'empty_title' => 'Карантин пуст',
                'empty_hint'  => 'Изолированные партии появятся здесь после решения «на карантин».',
            )); ?>
        <?php Component::end(); ?>
    </div>

</div>
