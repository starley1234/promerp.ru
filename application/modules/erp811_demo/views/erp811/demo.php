<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Лендинг демонстрационного стенда.
 *
 * Собран из общих компонентов интерфейса (`application/views/components`) в том
 * же виде, что и остальные страницы системы: контейнер, заголовок с подписью,
 * показатели с единицами (`kpi`), панели (`card`), таблица с подписью, `scope`
 * и прокруткой на узком экране (`table`). Своей вёрстки здесь остались только
 * вход по ролям и ссылки на шаги маршрута — для них готового компонента нет.
 *
 * Страница ничего не считает: цифры берутся из сценария и состояния стенда.
 */
$company = $scenario->company();
$landing = $scenario->landing();
$parts   = $scenario->parts();
$labels  = isset($labels) ? $labels : array();
$inside  = ! empty($inside);

$material_names = array();

foreach ($scenario->materials() as $material)
{
    $material_names[$material['code']] = $material['name'];
}

// Кнопки страницы: те же классы, что на рабочем месте и в модулях
$button_primary   = 'inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500';
$button_secondary = 'inline-flex items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500';

$storage = HTML::chars($status['database'].' / '.$status['schema']);
?>

<div class="space-y-6">

    <?php if ($inside): ?>
        <div role="status">
            <?php Component::begin('card', array('state' => 'warn')); ?>
                <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-gray-800 dark:text-gray-100">
                    <p>
                        <i class="fas fa-circle-info mr-1 text-amber-600" aria-hidden="true"></i>
                        <strong>Вы в демонстрационном режиме.</strong>
                        Хранилище стенда: <code class="font-mono"><?php echo $storage; ?></code>. Изменения изолированы.
                    </p>
                    <form method="post" action="<?php echo Route::url('erp811_demo_exit'); ?>">
                        <button type="submit" class="<?php echo $button_secondary; ?>">Выйти из демо</button>
                    </form>
                </div>
            <?php Component::end(); ?>
        </div>
    <?php endif; ?>

    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">
                <?php echo HTML::chars(isset($landing['title']) ? $landing['title'] : 'Демонстрационный стенд'); ?>
            </p>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-1"><?php echo HTML::chars($company['name']); ?></h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-3xl">
                <?php echo HTML::chars(isset($landing['lead']) ? $landing['lead'] : ''); ?>
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                <?php echo HTML::chars($scenario->headline()); ?>
                <?php if ( ! empty($landing['minutes'])): ?>
                    · <i class="far fa-clock" aria-hidden="true"></i> <?php echo HTML::chars($landing['minutes']); ?>
                <?php endif; ?>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 shrink-0">
            <a href="/demo/setup" class="<?php echo $button_secondary; ?>">
                <i class="fas fa-sliders" aria-hidden="true"></i> Управление и диагностика
            </a>
            <?php if ( ! empty($status['available'])): ?>
                <?php if ($inside): ?>
                    <a href="/analytics" class="<?php echo $button_primary; ?>">
                        <i class="fas fa-chart-pie" aria-hidden="true"></i> В аналитику
                    </a>
                <?php else: ?>
                    <form method="post" action="<?php echo Route::url('erp811_demo_enter'); ?>">
                        <button type="submit" class="<?php echo $button_primary; ?>">
                            <i class="fas fa-right-to-bracket" aria-hidden="true"></i> Войти в демо
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($status['available'])): ?>

        <?php
        Component::begin('card', array(
            'title' => 'Демонстрационный стенд ожидает инициализации',
            'icon'  => 'fas fa-hammer',
            'state' => 'warn',
            'id'    => 'stand-init',
        ));
        ?>
            <p class="text-sm text-gray-700 dark:text-gray-200">
                База данных <code class="font-mono"><?php echo HTML::chars($status['database']); ?></code> ещё не наполнена.
                Стенд можно собрать прямо сейчас из браузера одним нажатием.
            </p>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <form method="post" action="/demo/build">
                    <input type="hidden" name="reset" value="1">
                    <button type="submit" class="<?php echo $button_primary; ?>">
                        <i class="fas fa-play" aria-hidden="true"></i> Собрать стенд
                    </button>
                </form>
                <a href="/demo/setup" class="<?php echo $button_secondary; ?>">Панель диагностики <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
            </div>
        <?php Component::end(); ?>

    <?php else: ?>

        <?php // Состояние стенда: что выпускает, на чём, за какой срок, когда собран ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <?php echo Component::render('kpi', array(
                'label' => 'Выпускает деталей',
                'value' => count($parts),
                'unit'  => 'наименований',
                'hint'  => 'от клипсы 3,2 г до кронштейна 76 г',
            )); ?>
            <?php echo Component::render('kpi', array(
                'label' => 'Работает на',
                'value' => count($scenario->centers()),
                'unit'  => 'центрах',
                'hint'  => 'FDM, SLA, постобработка, ОТК',
            )); ?>
            <?php echo Component::render('kpi', array(
                'label' => 'История данных',
                'value' => (int) $scenario->history_days(),
                'unit'  => 'дней',
                'hint'  => (int) $scenario->order_count().' заказов, движения, ОТК',
            )); ?>
            <?php echo Component::render('kpi', array(
                'label' => 'Дата сборки',
                'value' => ! empty($status['built_at']) ? substr((string) $status['built_at'], 0, 10) : '—',
                'unit'  => '',
                'hint'  => (int) $status['tables'].' таблиц в базе '.$status['database'],
            )); ?>
        </div>

        <?php if ( ! empty($landing['promise'])): ?>
            <?php Component::begin('card', array('title' => 'Как устроен стенд', 'icon' => 'fas fa-shield-halved', 'id' => 'stand-how')); ?>
                <ul class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                    <?php foreach ((array) $landing['promise'] as $line): ?>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check mt-0.5 text-emerald-600" aria-hidden="true"></i>
                            <span><?php echo HTML::chars($line); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php Component::end(); ?>
        <?php endif; ?>

        <?php
        // Что выпускает печатня: таблица общего компонента — подпись, scope и
        // прокрутка на узком экране идут из него, а не из этой страницы
        $rows = array();

        foreach ($parts as $part)
        {
            $material = isset($material_names[$part['material']]) ? $material_names[$part['material']] : $part['material'];

            $rows[] = array(
                '<span class="font-medium text-gray-900 dark:text-white">'.HTML::chars($part['name']).'</span>'
                    .'<div class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($part['code']).'</div>',
                HTML::chars($material),
                number_format((int) $part['batch'], 0, ',', ' ').' шт.',
                number_format((float) $part['weight_g'], 1, ',', ' '),
                HTML::chars($part['tolerance']),
                number_format((float) $part['print_hours'], 1, ',', ' '),
            );
        }

        Component::begin('card', array(
            'title'    => 'Что выпускает печатня',
            'icon'     => 'fas fa-cubes',
            'subtitle' => 'Каждая деталь — со своей нормой времени, материалом и допуском: по этим данным цех планирует операции, а склад считает расход.',
            'id'       => 'stand-parts',
            'flush'    => TRUE,
        ));
        echo Component::render('table', array(
            'caption'    => 'Номенклатура деталей демонстрационного стенда: материал, партия, масса, допуск',
            'row_header' => TRUE,
            'columns'    => array(
                array('label' => 'Деталь'),
                array('label' => 'Материал'),
                array('label' => 'Партия', 'align' => 'right'),
                array('label' => 'Масса, г', 'align' => 'right'),
                array('label' => 'Допуск', 'priority' => 2),
                array('label' => 'Печать партии, ч', 'align' => 'right', 'priority' => 3),
            ),
            'rows'        => $rows,
            'empty_title' => 'Деталей в сценарии нет',
            'empty_hint'  => 'Добавьте детали в config/scenario/print3d.php и пересоберите стенд.',
        ));
        Component::end();
        ?>

        <?php
        Component::begin('card', array(
            'title'    => 'Смотреть систему',
            'icon'     => 'fas fa-eye',
            'subtitle' => isset($landing['guest_hint']) ? $landing['guest_hint'] : 'Вход гостем одной кнопкой.',
            'id'       => 'stand-enter',
            'actions_html' => '<a href="'.Route::url('erp811_demo_tour').'" class="'.$button_secondary.'">'
                .'<i class="fas fa-route" aria-hidden="true"></i> Маршрут осмотра</a>'
                .($inside
                    ? '<a href="/analytics" class="'.$button_primary.'"><i class="fas fa-chart-pie" aria-hidden="true"></i> Открыть аналитику</a>'
                    : ''),
        ));
        ?>
            <?php if ( ! $inside AND ! empty($roleplay)): ?>
                <?php
                /*
                 * Выбор роли и здесь, а не только на главной: посетитель приходит
                 * на /demo из меню и из поиска, минуя главную. Витрины без роли
                 * показывают отчёты, но не рабочие места, поэтому роль нужно
                 * предложить на обоих входах.
                 */
                ?>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <?php foreach ($roleplay as $entry): ?>
                        <form method="post" action="<?php echo Route::url('erp811_demo_enter'); ?>" class="h-full">
                            <input type="hidden" name="role" value="<?php echo HTML::chars($entry['role']); ?>">
                            <button type="submit"
                                    class="group flex h-full w-full items-center gap-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-3 text-left hover:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                    <i class="fas <?php echo HTML::chars($entry['icon']); ?>" aria-hidden="true"></i>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-gray-900 dark:text-white"><?php echo HTML::chars($entry['title']); ?></span>
                                    <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($entry['hint']); ?></span>
                                </span>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ( ! empty($guest['email'])): ?>
                <p class="text-xs text-gray-500 dark:text-gray-400 <?php echo ( ! $inside AND ! empty($roleplay)) ? 'mt-4' : ''; ?>">
                    Гостевой доступ: <code><?php echo HTML::chars($guest['email']); ?></code>,
                    пароль <code><?php echo HTML::chars(isset($guest['password']) ? $guest['password'] : ''); ?></code>
                    — учётная запись живёт только в базе стенда.
                </p>
            <?php endif; ?>
        <?php Component::end(); ?>

        <section aria-labelledby="stand-tour">
            <h2 id="stand-tour" class="font-semibold text-gray-900 dark:text-white mb-3">Куда смотреть в первую очередь</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach (array_slice($tour, 0, 4) as $step): ?>
                    <a href="<?php echo HTML::chars($step['url']); ?>"
                       class="block rounded-lg border bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 p-4 hover:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <span class="block text-xs font-semibold text-blue-600 dark:text-blue-400">Шаг <?php echo (int) $step['no']; ?></span>
                        <span class="block font-medium text-gray-900 dark:text-white mt-1"><?php echo HTML::chars($step['title']); ?></span>
                        <span class="block text-sm text-gray-600 dark:text-gray-300 mt-2"><?php echo HTML::chars($step['what']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <p class="mt-3 text-sm">
                <a href="<?php echo Route::url('erp811_demo_tour'); ?>" class="text-blue-600 dark:text-blue-400 hover:underline">
                    Все <?php echo count($tour); ?> шагов маршрута осмотра <i class="fas fa-arrow-right ml-1" aria-hidden="true"></i>
                </a>
            </p>
        </section>

        <?php if ( ! empty($status['counts'])): ?>
            <?php
            Component::begin('card', array(
                'title'    => 'Что уже есть на стенде',
                'icon'     => 'fas fa-database',
                'subtitle' => 'Цифры последней сборки: подпись и единица измерения у каждой — чтобы «7020» нельзя было прочитать как «7020 деталей».',
                'id'       => 'stand-data',
            ));
            ?>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <?php foreach ((array) $status['counts'] as $key => $value): ?>
                        <?php if ( ! is_scalar($value)) { continue; } ?>
                        <?php echo Component::render('kpi', array(
                            'label'   => isset($labels[$key]) ? $labels[$key] : $key,
                            'value'   => number_format((float) $value, is_float($value) ? 2 : 0, ',', ' '),
                            'unit'    => '',
                            'compact' => TRUE,
                        )); ?>
                    <?php endforeach; ?>
                </div>
                <?php if ( ! empty($status['built_at'])): ?>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-4">
                        Последняя сборка: <?php echo HTML::chars($status['built_at']); ?>
                        <?php if ( ! empty($status['build_user'])): ?>· <?php echo HTML::chars($status['build_user']); ?><?php endif; ?>
                    </p>
                <?php endif; ?>
            <?php Component::end(); ?>
        <?php endif; ?>

        <?php if ( ! empty($problems)): ?>
            <div role="alert">
                <?php Component::begin('card', array(
                    'title' => 'Замечания последней сборки',
                    'icon'  => 'fas fa-triangle-exclamation',
                    'state' => 'warn',
                    'id'    => 'stand-problems',
                )); ?>
                    <ul class="space-y-1 text-sm text-gray-800 dark:text-gray-100">
                        <?php foreach ($problems as $problem): ?>
                            <li>
                                <?php echo HTML::chars(isset($problem['layer']) ? $problem['layer'] : ''); ?>:
                                <?php echo HTML::chars(isset($problem['message']) ? $problem['message'] : (string) $problem); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php Component::end(); ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <p class="text-xs text-gray-400 dark:text-gray-500 pt-4 border-t border-gray-200 dark:border-gray-700">
        Данные вымышленные. Стенд изолирован в базе данных <code><?php echo $storage; ?></code>;
        рабочая схема <code>public</code> при показе не используется.
    </p>
</div>
