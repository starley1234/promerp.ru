<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Главная для неавторизованного посетителя.
 *
 * Отвечает на три вопроса: что это, что меняется в работе и как попробовать
 * систему. Вход в демонстрацию — на первом экране, чтобы начать просмотр можно
 * было сразу, а не после прокрутки: раньше главный экран занимал почти всю
 * высоту (`pt-16` поверх `py-24`, плюс отступы `<main>`), и блок выбора роли — то
 * единственное, ради чего сюда приходят без регистрации, — оказывался за
 * краем экрана.
 *
 * Роли предлагаются сразу: витрины без роли показывают отчёты, но не смысл
 * системы — «что делать этому человеку прямо сейчас». Выбор роли на входе
 * приводит гостя в подходящий раздел, а не в общий дашборд.
 *
 * Вместо стены текста — короткие результаты с раскрывающимися пояснениями,
 * выбор роли в демо и компактная карта возможностей. Оформление остаётся в
 * общем стиле сервиса: знакомые компоненты, спокойные серые тона и синий
 * акцент. Непроверенных обещаний и выдуманных показателей нет.
 */

$demo     = isset($demo) ? $demo : NULL;
$landing  = ($demo AND isset($demo['landing']))  ? (array) $demo['landing']  : array();
$company  = ($demo AND isset($demo['company']))  ? (array) $demo['company']  : array();
$roleplay = ($demo AND isset($demo['roleplay'])) ? (array) $demo['roleplay'] : array();

// Что меняется в работе: не «преимущества», а измеримые последствия —
// каждое соответствует существующему разделу системы.
$outcomes = array(
    array('icon' => 'fas fa-triangle-exclamation', 'title' => 'Дефицит виден до запуска',
        'text' => 'Планирование разузловывает программу выпуска и показывает, чего не хватит, пока заказ ещё не в цеху.'),
    array('icon' => 'fas fa-magnifying-glass', 'title' => 'Партия находится в один клик',
        'text' => 'Партии и серийные номера с происхождением: и прямой вопрос «из чего сделано», и обратный «кого затронул брак поставщика».'),
    array('icon' => 'fas fa-calculator', 'title' => 'Себестоимость — по формуле',
        'text' => 'План и факт заказа с разложением каждой строки и ссылкой на первичный документ, а не «итого за месяц».'),
    array('icon' => 'fas fa-clock-rotate-left', 'title' => 'Журнал не переписывается',
        'text' => 'Движения склада, генеалогия партий и выпущенные документы только добавляются: исправление — новой записью.'),
);

// Что закрывает система — коротко и предметно. Список общий для всех
// посетителей и не зависит от того, собран ли демо-стенд.
$capabilities = array(
    array('icon' => 'fas fa-gears',           'title' => 'Производство',  'text' => 'Заказы, техкарты и задания смены.'),
    array('icon' => 'fas fa-boxes-stacked',   'title' => 'Склад',         'text' => 'Остатки, резервы, приёмка и движения.'),
    array('icon' => 'fas fa-cart-shopping',   'title' => 'Закупки',       'text' => 'Потребность, заявки и сроки поставок.'),
    array('icon' => 'fas fa-clipboard-check', 'title' => 'Качество',      'text' => 'Контроль, несоответствия и решения ОТК.'),
    array('icon' => 'fas fa-calendar-days',   'title' => 'Планирование',  'text' => 'План выпуска и дефицит до запуска.'),
    array('icon' => 'fas fa-calculator',      'title' => 'Себестоимость', 'text' => 'Материалы, работа и накладные по заказу.'),
);

// Кнопки и плитки — те же классы, что на /demo и в модулях
$button_primary   = 'inline-flex min-h-10 items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500';
$button_secondary = 'inline-flex min-h-10 items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500';
$tile             = 'rounded-lg border bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 p-4';

// Главное действие первого экрана зависит от того, собран ли демо-стенд
$actions = '';

if ($demo AND ! $roleplay)
{
    $actions = '<form method="post" action="/demo/enter"><button type="submit" class="'.$button_primary.'">'
        .'<i class="fas fa-play text-xs" aria-hidden="true"></i> Открыть демонстрацию</button></form>';
}
elseif ( ! $demo)
{
    $actions = '<a href="/auth/login" class="'.$button_primary.'">Войти в систему</a>';
}

$actions .= '<a href="/docs" class="'.$button_secondary.'">Документация</a>'
    .'<a href="/about" class="'.$button_secondary.'">О системе</a>';
?>

<div class="space-y-6">

    <?php // Первый экран: заголовок, суть, действие. ?>
    <?php echo Component::render('page_header', array(
        'title'        => 'PromERP — производство под контролем',
        'lead'         => 'От плана до себестоимости: цех, склад, закупки и качество в одном рабочем пространстве.',
        'actions_html' => $actions,
    )); ?>

    <?php if ($demo AND $roleplay): ?>

        <?php
        /*
         * Вход в демо — главное действие страницы, поэтому идёт сразу за
         * шапкой. Вход — POST: это смена состояния, а не переход по ссылке.
         * Витрины без роли показывают отчёты, но не смысл системы, поэтому
         * роль предлагается сразу.
         */
        $stand = ! empty($company['name'])
            ? $company['name'].( ! empty($company['city']) ? ', '.$company['city'] : '')
            : 'Демонстрационный стенд';

        if ( ! empty($landing['minutes']))
        {
            $stand .= ' · '.$landing['minutes'];
        }

        Component::begin('card', array(
            'title'    => 'Посмотреть без регистрации — выберите, чьими глазами',
            'icon'     => 'fas fa-flask',
            'subtitle' => $stand,
            'state'    => 'info',
            'id'       => 'demo-roles',
        ));
        ?>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($roleplay as $entry): ?>
                    <form method="post" action="/demo/enter" class="h-full">
                        <input type="hidden" name="role" value="<?php echo HTML::chars($entry['role']); ?>">
                        <button type="submit"
                                class="group flex h-full w-full items-center gap-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-3 text-left transition-colors hover:border-blue-400 hover:bg-blue-50/60 dark:hover:bg-blue-900/20 focus:outline-none focus:ring-2 focus:ring-blue-500">
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

            <p class="mt-4 text-xs text-gray-600 dark:text-gray-300">
                Нажмите на роль — откроется рабочее место с готовыми примерами. Данные вымышленные, запись отключена.
                <a href="#demo-details" class="font-medium text-blue-700 hover:underline dark:text-blue-300">Как устроен стенд</a>
            </p>
        <?php Component::end(); ?>

    <?php endif; ?>

    <?php // Короткие результаты — пояснение открывается по выбору. ?>
    <section aria-labelledby="outcomes-title">
        <h2 id="outcomes-title" class="font-semibold text-gray-900 dark:text-white">Что меняется в работе</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Выберите карточку, чтобы посмотреть подробнее.</p>

        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <?php foreach ($outcomes as $outcome): ?>
                <details class="group rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                    <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 rounded-lg p-3 text-sm font-semibold text-gray-900 transition-colors hover:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:text-white">
                        <span class="flex items-center gap-2">
                            <i class="<?php echo HTML::chars($outcome['icon']); ?> text-blue-600 dark:text-blue-400" aria-hidden="true"></i>
                            <?php echo HTML::chars($outcome['title']); ?>
                        </span>
                        <i class="fas fa-chevron-down shrink-0 text-xs text-gray-400 transition-transform group-open:rotate-180" aria-hidden="true"></i>
                    </summary>
                    <p class="px-3 pb-4 text-sm text-gray-600 dark:text-gray-300"><?php echo HTML::chars($outcome['text']); ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </section>

    <?php // Ключевые участки производства — кратко, без каталога экранов. ?>
    <section aria-labelledby="capabilities-title">
        <h2 id="capabilities-title" class="font-semibold text-gray-900 dark:text-white">Весь производственный контур</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ключевые участки связаны в одной системе — от заказа до выпуска.</p>

        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($capabilities as $capability): ?>
                <div class="<?php echo $tile; ?>">
                    <p class="flex items-center gap-2 font-medium text-gray-900 dark:text-white">
                        <i class="<?php echo HTML::chars($capability['icon']); ?> text-blue-600 dark:text-blue-400" aria-hidden="true"></i>
                        <?php echo HTML::chars($capability['title']); ?>
                    </p>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300"><?php echo HTML::chars($capability['text']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="mt-3 text-sm">
            <a href="/about" class="font-medium text-blue-700 hover:underline dark:text-blue-300">Состав продукта и границы системы <span aria-hidden="true">→</span></a>
        </p>
    </section>

    <?php if ($demo): ?>
        <?php // Подробности стенда — ниже: они нужны после решения «посмотрю». ?>
        <?php
        Component::begin('card', array(
            'title'    => Arr::get($landing, 'title', 'О демонстрационном стенде'),
            'icon'     => 'fas fa-shield-halved',
            'subtitle' => Arr::get($landing, 'lead'),
            'id'       => 'demo-details',
        ));
        ?>
            <?php if ( ! empty($landing['promise'])): ?>
                <ul class="grid grid-cols-1 gap-2 text-sm text-gray-700 dark:text-gray-200 md:grid-cols-2">
                    <?php foreach ((array) $landing['promise'] as $promise): ?>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check mt-0.5 text-emerald-600" aria-hidden="true"></i>
                            <span><?php echo HTML::chars($promise); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php Component::end(); ?>
    <?php endif; ?>

    <?php
    /*
     * Частые вопросы закрыты по умолчанию: ответы остаются доступны по
     * раскрытию, но не превращают главную в документацию. Тот же список
     * вопросов и ответов уходит поисковику в структурированные данные FAQPage.
     *
     * Список ведёт контроллер: он же печатает те же вопросы и ответы в
     * структурированные данные, поэтому текст не может разойтись.
     */
    ?>
    <?php if ( ! empty($faq)): ?>
        <?php Component::begin('card', array('title' => 'Частые вопросы', 'icon' => 'fas fa-circle-question', 'id' => 'faq')); ?>
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($faq as $pair): ?>
                    <details class="group py-1">
                        <summary class="flex min-h-10 cursor-pointer list-none items-center justify-between gap-3 rounded-md py-2 text-sm font-semibold text-gray-900 transition-colors hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:text-white dark:hover:text-blue-300">
                            <span><?php echo HTML::chars($pair[0]); ?></span>
                            <i class="fas fa-chevron-down shrink-0 text-xs text-gray-400 transition-transform group-open:rotate-180" aria-hidden="true"></i>
                        </summary>
                        <p class="pb-3 pt-1 text-sm text-gray-600 dark:text-gray-300"><?php echo HTML::chars($pair[1]); ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        <?php Component::end(); ?>
    <?php endif; ?>

    <?php // Последний экран: что сделать прямо сейчас. ?>
    <?php Component::begin('card', array(
        'title'    => $demo ? 'Посмотрите на живых данных' : 'Покажите систему своим людям',
        'icon'     => 'fas fa-rocket',
        'subtitle' => $demo
            ? 'Демонстрационный стенд собран на сквозном примере: от заказа клиента до себестоимости партии.'
            : 'Демонстрационный стенд поднимается на сквозном примере: от заказа клиента до себестоимости партии.',
        'id'       => 'start',
    )); ?>
        <div class="flex flex-wrap items-center gap-3">
            <?php if ($demo): ?>
                <a href="/demo" class="<?php echo $button_primary; ?>">
                    <i class="fas fa-play text-xs" aria-hidden="true"></i> Открыть демонстрацию
                </a>
            <?php else: ?>
                <a href="/contact" class="<?php echo $button_primary; ?>">
                    <i class="fas fa-envelope text-xs" aria-hidden="true"></i> Запросить доступ к стенду
                </a>
            <?php endif; ?>
            <a href="/docs" class="<?php echo $button_secondary; ?>">
                <i class="fas fa-book-open text-xs" aria-hidden="true"></i> Документация
            </a>
            <a href="/about" class="<?php echo $button_secondary; ?>">
                <i class="fas fa-circle-info text-xs" aria-hidden="true"></i> Что внутри
            </a>
        </div>
    <?php Component::end(); ?>

</div>
