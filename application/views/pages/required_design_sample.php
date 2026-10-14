<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Страница-эталон интерфейса PromERP.
 *
 * Зачем. Правила интерфейса живут в `AGENTS.md` (§10), `docs/FRONTEND_STATUS.md`
 * и `application/views/components/README.md`, а живая разметка — в сотнях
 * представлений модулей. Здесь правило и сразу под ним — живой пример на общем
 * компоненте: агент видит не пересказ, а собранный интерфейс.
 *
 * Как устроена страница:
 *
 *  * сначала — правила одной компактной панелью;
 *  * потом — четыре собранные страницы: ввод, карточка, перечень, сводка;
 *  * затем — каталог компонентов, каждый с коротким правилом и примером;
 *  * отдельно — операторский экран: крупные действия, сканер, статус связи.
 *
 * Показ. Одни и те же данные выглядят по-разному в двух режимах интерфейса,
 * поэтому режим переключается запросом `?ui=simple|pro` (решает контроллер:
 * представление о режиме не спрашивает никогда). По умолчанию —
 * профессиональный вид: в нём видны таблицы, а не только плитки.
 *
 * Страница открыта гостю и закрыта от индексации (`noindex` в разметке и в
 * заголовке ответа). Сама она соблюдает правила, которые показывает: только
 * общие компоненты, никаких ручных таблиц, inline-обработчиков и inline-стилей.
 */

// --- Классы, одинаковые во всех примерах ------------------------------------

$primary   = 'inline-flex min-h-10 items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500';
$secondary = 'inline-flex min-h-10 items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500';
$quiet     = 'inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-600 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500';
$danger    = 'inline-flex min-h-10 items-center gap-1.5 px-4 py-2 bg-rose-600 text-white text-sm font-medium rounded-md hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500';
$icon_only = 'inline-flex items-center justify-center rounded-lg border border-gray-300 p-2 text-gray-500 hover:text-blue-600 dark:border-gray-600 dark:text-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500';
$input     = 'mt-1 block min-h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 dark:border-gray-600 dark:bg-gray-800 dark:text-white';
$panel     = 'rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4';
$frame     = 'rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 dark:border-gray-600 dark:bg-gray-900/40';
$pre       = 'overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs leading-relaxed text-gray-100';

/**
 * Витрина примера: подпись, короткое правило и живой пример под ним.
 *
 * @param   string       $title       подпись блока (экранируется)
 * @param   string       $note_html   правило одной строкой (готовый HTML)
 * @param   string       $body_html   пример — готовая разметка
 * @param   string|NULL  $code        вызов компонента, который это нарисовал
 * @return  string
 */
$showcase = function ($title, $note_html, $body_html, $code = NULL) use ($pre) {
    $html = '<div class="space-y-3">'
        .'<div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">'
        .'<h3 class="text-sm font-semibold text-gray-900 dark:text-white">'.HTML::chars($title).'</h3>'
        .'<p class="text-xs text-gray-500 dark:text-gray-400">'.$note_html.'</p>'
        .'</div>'
        .$body_html;

    if ($code !== NULL)
    {
        $html .= '<pre class="'.$pre.'" tabindex="0"><code>'.HTML::chars($code).'</code></pre>';
    }

    return $html.'</div>';
};

/**
 * Рамка примера: пример — это границы «страницы внутри страницы», а не часть
 * интерфейса. `$narrow` — узкая колонка, как у настоящей формы ввода.
 *
 * @param   string   $body_html
 * @param   boolean  $narrow
 * @return  string
 */
$sample = function ($body_html, $narrow = FALSE) use ($frame) {
    return '<div class="'.$frame.'">'
        .($narrow ? '<div class="mx-auto max-w-3xl">'.$body_html.'</div>' : $body_html)
        .'</div>';
};

/**
 * Мелкая подпись-ссылка на файл или команду.
 *
 * @param   string  $text
 * @return  string
 */
$code_inline = function ($text) {
    return '<code class="text-xs text-gray-600 dark:text-gray-300">'.HTML::chars($text).'</code>';
};

// --- Данные примеров --------------------------------------------------------

$rows = array();

foreach (array(
    array('ЛТ-2026-00418', 'КРН-50.01', 'Корпус насоса НШ-50', 'available',  'ok',      '120 шт', '07.10.2026'),
    array('ЛТ-2026-00419', 'КРН-50.02', 'Крышка насоса НШ-50', 'quarantine', 'warn',    '48 шт',  '06.10.2026'),
    array('ЛТ-2026-00420', 'ВЛ-32.10',  'Вал ведущий 32 мм',   'available',  'ok',      '310 шт', '05.10.2026'),
    array('ЛТ-2026-00421', 'ПД-16.03',  'Подшипник 16-03',     'recalled',   'bad',     '90 шт',  '28.09.2026'),
) as $item)
{
    $rows[] = array(
        '<a class="font-medium text-blue-700 hover:underline dark:text-blue-400" href="#page-item">'.HTML::chars($item[0]).'</a>'
            .'<span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($item[1]).'</span>',
        HTML::chars($item[2]),
        Component::render('badge', array('label' => $item[3], 'state' => $item[4])),
        HTML::chars($item[5]),
        HTML::chars($item[6]),
        '<div class="flex flex-wrap items-center justify-end gap-2">'
            .'<a href="#page-item" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-blue-700 hover:bg-gray-50 dark:border-gray-600 dark:text-blue-400 dark:hover:bg-gray-800">Открыть</a>'
            .'<a href="#page-item" class="'.$icon_only.'" aria-label="Этикетка партии '.HTML::chars($item[0]).'">'
            .'<i class="fas fa-qrcode text-xs" aria-hidden="true"></i></a>'
            .'</div>',
    );
}

$columns = array(
    array('label' => 'Партия', 'priority' => 1),
    array('label' => 'Номенклатура', 'priority' => 2),
    array('label' => 'Статус', 'priority' => 1),
    array('label' => 'Количество', 'priority' => 2, 'align' => 'right'),
    array('label' => 'Годен до', 'priority' => 3, 'nowrap' => TRUE),
    array('label' => 'Действия', 'priority' => 1, 'align' => 'right'),
);

$table_html = Component::render('table', array(
    'caption'     => 'Партии и серийные номера',
    'columns'     => $columns,
    'rows'        => $rows,
    'row_header'  => TRUE,
    'sticky'      => TRUE,
    'empty_title' => 'Партии не найдены',
    'empty_hint'  => 'Измените фильтры или создайте первую партию.',
    'aria_label'  => 'Партии и серийные номера',
));

$pagination_html = Component::render('pagination', array(
    'page'  => 1,
    'pages' => 12,
    'total' => 468,
    'url'   => function ($page) {
        return '/pages/required_design_sample?page='.$page;
    },
));

// Панель отбора: своя копия на каждый показ — иначе подписи второй панели
// ссылаются на поля первой (дубли `id` ломают `label for`).
$filters_body = function ($suffix) use ($input) {
    return Component::render('form_field', array(
            'id'          => 'filter_q_'.$suffix,
            'name'        => 'q',
            'label'       => 'Поиск',
            'placeholder' => 'Номер, код или название',
        ))
        .'<div><label for="filter_status_'.$suffix.'" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Статус</label>'
        .'<select id="filter_status_'.$suffix.'" name="status" class="'.$input.'">'
        .'<option value="">Все статусы</option>'
        .'<option value="available">available</option>'
        .'<option value="quarantine">quarantine</option>'
        .'</select></div>';
};

// Журнал движений: строка журнала объясняет, откуда взялось число.
$movements = array(
    array('07.10.2026 09:12', 'Выпуск',           'Заказ ПР-2026-0112', '+120 шт', 'Склад готовой продукции'),
    array('06.10.2026 14:40', 'Списание',         'Заказ ПР-2026-0112', '−118 шт', 'Склад материалов'),
    array('06.10.2026 10:05', 'Приёмка',          'Поставка ПС-00451',  '+120 шт', 'Склад материалов'),
);

$movement_rows = array();

foreach ($movements as $movement)
{
    $movement_rows[] = array(
        HTML::chars($movement[0]),
        HTML::chars($movement[1]),
        HTML::chars($movement[2]),
        HTML::chars($movement[3]),
        HTML::chars($movement[4]),
    );
}

// Дефицит: у цифры подпись и единица, иначе «30» читается как угодно.
$deficit_rows = array(
    array('КРН-50.02 · Крышка насоса НШ-50', '48 шт', Component::render('badge', array('label' => 'через 2 дня', 'state' => 'bad'))),
    array('ВЛ-32.10 · Вал ведущий 32 мм',    '96 шт', Component::render('badge', array('label' => 'через 6 дней', 'state' => 'warn'))),
    array('ПД-16.03 · Подшипник 16-03',      '30 шт', Component::render('badge', array('label' => 'есть запас', 'state' => 'ok'))),
);

// --- Примеры вызовов --------------------------------------------------------

$code_page = <<<'CODE'
<?php echo Component::render('page_header', array(
    'title'        => 'Партии и серийные номера',
    'lead'         => 'О чём страница: один абзац.',
    'crumbs'       => array(array('Склад', '/warehouse'), array('Партии', NULL)),
    'actions_html' => '<a href="/traceability/create" class="…">Новая партия</a>',
)); ?>

<?php Component::begin('card', array('title' => 'Результаты отбора', 'icon' => 'fas fa-code-branch', 'flush' => TRUE)); ?>
    <?php echo Component::render('table', array('caption' => 'Партии', 'columns' => $columns, 'rows' => $rows)); ?>
<?php Component::end(); ?>
CODE;

$code_table = <<<'CODE'
echo Component::render('table', array(
    'caption' => 'Партии и серийные номера',        // подпись читает скринридер
    'columns' => array(
        array('label' => 'Партия',       'priority' => 1),   // видна всегда
        array('label' => 'Номенклатура', 'priority' => 2),   // скрывается до sm
        array('label' => 'Статус',       'priority' => 1),
        array('label' => 'Количество',   'priority' => 2, 'align' => 'right'),
        array('label' => 'Годен до',     'priority' => 3, 'nowrap' => TRUE),  // до md
        array('label' => 'Действия',     'priority' => 1),
    ),
    'rows'        => $rows,          // ячейка — готовый HTML, данные экранируем сами
    'row_header'  => TRUE,           // первая ячейка — <th scope="row">
    'sticky'      => TRUE,
    'empty_title' => 'Партии не найдены',
    'empty_hint'  => 'Измените фильтры или создайте первую партию.',
));
CODE;

$code_kpi = <<<'CODE'
echo Component::render('kpi', array(
    'label'  => 'Выпуск за смену',
    'value'  => '412',
    'unit'   => 'шт',                                  // без единицы цифру читают как угодно
    'target' => '450',
    'state'  => 'warn',                                // ok | warn | bad | info | neutral
    'trend'  => array(320, 366, 298, 401, 388, 412),   // спарклайн без JavaScript
    'url'    => '/shopfloor',                          // переход в разрез
));
CODE;

$code_form = <<<'CODE'
echo Component::render('form_field', array(
    'id'    => 'qty',
    'name'  => 'qty',
    'label' => 'Количество',
    'type'  => 'number',
    'value' => $value,        // при отказе форма не пустая
    'error' => $error,        // ошибка встаёт под полем и связывается через ARIA
    'hint'  => 'В штуках',
));

// Сущность выбирают поиском, а не вводом внутреннего id:
echo Component::render('smart_reference', array(
    'id'   => 'item', 'name' => 'item_id', 'label' => 'Номенклатура',
    'url'  => URL::site('traceability/api/reference'),  // {items:[{id,label,context}]}
));
CODE;

// --- Показ: профессиональный вид (таблицы) или простой (плитки) -------------

$is_pro = ($ui_preview === 'pro');

$preview_switch = '<nav aria-label="Показ эталона" class="flex rounded-lg bg-gray-100 p-1 text-xs dark:bg-gray-700">'
    .'<a href="?ui=pro" title="Профессиональный вид: таблицы, все поля"'
    .($is_pro ? ' aria-current="true"' : '')
    .' class="rounded-md px-3 py-1.5 '.($is_pro
        ? 'bg-white font-semibold text-blue-700 shadow-sm dark:bg-gray-800 dark:text-blue-300'
        : 'text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white').'">Таблицами</a>'
    .'<a href="?ui=simple" title="Простой вид: крупные плитки"'
    .($is_pro ? '' : ' aria-current="true"')
    .' class="rounded-md px-3 py-1.5 '.($is_pro
        ? 'text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white'
        : 'bg-white font-semibold text-blue-700 shadow-sm dark:bg-gray-800 dark:text-blue-300').'">Плитками</a>'
    .'</nav>';
?>

<div class="space-y-6">

    <?php echo Component::render('page_header', array(
        'title'   => 'Эталон интерфейса',
        'lead'    => 'Живые примеры на общих компонентах: четыре типа страниц (ввод, карточка, перечень, сводка), '
            .'каталог компонентов и операторский экран.',
        'crumbs'  => array(array('Главная', '/'), array('Документация', '/docs'), array('Эталон интерфейса', NULL)),
        'meta'    => 'Показ: '.HTML::chars($ui_mode_label).' режим'
            .($is_pro ? ' — таблицы' : ' — крупные плитки')
            .'. Переключатель показывает вид только на этой странице, ваш выбор не меняется.',
        'actions_html' => $preview_switch,
    )); ?>

    <?php Component::begin('card', array('title' => 'Правила', 'icon' => 'fas fa-list-check', 'id' => 'rules')); ?>
        <?php
        $rules = array(
            array('Один набор компонентов', 'Разметку дают '.$code_inline('application/views/components').': компонент — только вид, данные готовит контроллер.'),
            array('Поля страницы задаёт шаблон', 'Отступы от меню и шапки даёт '.$code_inline('&lt;main&gt;').'. Корень представления — простой '.$code_inline('&lt;div&gt;').' с отступами '.$code_inline('space-y-6').'.'),
            array('Шапка одна — page_header', 'Заголовок, подпись, крошки и главное действие. Градиентов и палитр slate/purple нет: акцент — синий, нейтраль — gray.'),
            array('Таблица — только компонентом table', 'Подпись, '.$code_inline('scope').', прокрутка, приоритет колонок, пустое состояние и листание.'),
            array('У цифры есть подпись и единица', '«7 020 шт» вместо «7020»; у показателя — период, цель и переход в разрез. Кг и шт не складывать.'),
            array('Форма — label, подсказка, ошибка рядом', 'Сущность выбирается поиском, а не вводом внутреннего id; значения при отказе сохраняются.'),
            array('Доступность по умолчанию', 'Доступное имя у кнопок-иконок, декоративные иконки скрыты, сообщения — через живую область.'),
            array('Режим выбирает компонент', 'Представление не спрашивает простой или профессиональный режим — компоненты перестраиваются сами.'),
            array('Интерактив — data-action', 'Обработчик один на документ; runtime CDN, inline-обработчиков и inline-стилей нет.'),
            array('Одно главное действие', 'Остальное — вторичное или в «Ещё». Экран без ссылки из меню и Ctrl+K не готов.'),
        );

        echo '<dl class="grid grid-cols-1 gap-x-6 gap-y-2 md:grid-cols-2">';

        foreach ($rules as $rule)
        {
            echo '<div class="flex gap-2 text-sm">'
                .'<i class="fas fa-check mt-1 text-xs text-blue-600 dark:text-blue-400" aria-hidden="true"></i>'
                .'<div><dt class="font-medium text-gray-900 dark:text-white">'.HTML::chars($rule[0]).'</dt>'
                .'<dd class="text-gray-600 dark:text-gray-300">'.$rule[1].'</dd></div>'
                .'</div>';
        }

        echo '</dl>';
        ?>
    <?php Component::end(); ?>

    <section aria-labelledby="pages-title" class="space-y-5">
        <div class="flex flex-wrap items-baseline justify-between gap-3">
            <h2 id="pages-title" class="text-xl font-semibold text-gray-900 dark:text-white">Четыре типа страниц</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Пример обведён пунктиром: рамка — граница примера, а не часть интерфейса.</p>
        </div>

        <?php echo Component::render('tabs', array(
            'label' => 'Примеры страниц',
            'tabs'  => array(
                array('href' => '#page-form',    'label' => 'Ввод'),
                array('href' => '#page-item',    'label' => 'Карточка'),
                array('href' => '#page-list',    'label' => 'Перечень'),
                array('href' => '#page-summary', 'label' => 'Сводка'),
            ),
        )); ?>

        <?php
        // 1. Страница ввода итема.
        ob_start();
        echo Component::render('page_header', array(
            'title'   => 'Новая партия',
            'level'   => 2,
            'lead'    => 'Номер партии сгенерируется автоматически; номенклатуру ищем по коду или названию.',
            'crumbs'  => array(array('Склад', '/warehouse'), array('Партии', '/traceability'), array('Новая партия', NULL)),
        ));
        ?>
        <?php Component::begin('card', array('title' => 'Данные партии', 'icon' => 'fas fa-boxes-stacked', 'id' => 'sample-form')); ?>
            <form method="post" action="/pages/required_design_sample" autocomplete="off" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <?php echo Component::render('smart_reference', array(
                            'id'       => 'form_item',
                            'name'     => 'item_id',
                            'label'    => 'Номенклатура',
                            'url'      => URL::site('pages/design_sample_reference'),
                            'required' => TRUE,
                            'hint'     => 'Код или название: внутренний id вводить не нужно',
                        )); ?>
                    </div>
                    <?php echo Component::render('form_field', array(
                        'id'    => 'form_lot',
                        'name'  => 'lot_number',
                        'label' => 'Номер партии',
                        'hint'  => 'Пусто — сгенерируется автоматически',
                    )); ?>
                    <?php echo Component::render('form_field', array(
                        'id'       => 'form_qty',
                        'name'     => 'qty',
                        'label'    => 'Количество',
                        'type'     => 'number',
                        'value'    => '120',
                        'required' => TRUE,
                    )); ?>
                    <?php echo Component::render('form_field', array(
                        'id'    => 'form_date',
                        'name'  => 'manufactured_at',
                        'label' => 'Изготовлено',
                        'type'  => 'date',
                        'value' => '2026-10-07',
                    )); ?>
                    <div>
                        <label for="form_mode" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Режим учёта</label>
                        <select id="form_mode" name="tracking_mode" class="<?php echo $input; ?>">
                            <option value="lot">Партия</option>
                            <option value="serial">Серийный учёт</option>
                        </select>
                    </div>
                </div>

                <?php Component::begin('more', array('summary' => 'Дополнительные поля')); ?>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <?php echo Component::render('form_field', array(
                            'id'    => 'form_expires',
                            'name'  => 'expires_at',
                            'label' => 'Годен до',
                            'type'  => 'date',
                        )); ?>
                        <div>
                            <label for="form_warehouse" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Склад приёмки</label>
                            <select id="form_warehouse" name="warehouse_id" class="<?php echo $input; ?>">
                                <option value="1">Склад материалов</option>
                                <option value="2">Склад готовой продукции</option>
                            </select>
                        </div>
                    </div>
                <?php Component::end(); ?>

                <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                    <button type="submit" class="<?php echo $primary; ?>"
                            data-confirm="Это эталон: сохранение не выполнится, страница обновится.">
                        <i class="fas fa-check text-xs" aria-hidden="true"></i> Создать партию
                    </button>
                    <a href="#page-form" class="<?php echo $secondary; ?>">Отмена</a>
                </div>
            </form>
        <?php Component::end(); ?>
        <?php
        echo '<div id="page-form">'
            .$showcase('1. Страница ввода',
                'Одно главное действие · второстепенное — в «Ещё» · сущность ищем поиском · значения при отказе сохраняются',
                $sample(ob_get_clean(), TRUE),
                $code_form)
            .'</div>';
        ?>

        <?php
        // 2. Страница айтема.
        ob_start();
        ?>
        <div class="space-y-6">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <a href="#page-list" class="text-sm text-blue-700 hover:underline dark:text-blue-400">← Все партии</a>
                    <h2 class="mt-1 text-xl font-bold text-gray-900 dark:text-white">ЛТ-2026-00418</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">КРН-50.01 · Корпус насоса НШ-50</p>
                </div>
                <div class="text-right">
                    <?php echo Component::render('badge', array('label' => 'available', 'state' => 'ok')); ?>
                    <div class="mt-1 font-mono text-xs text-gray-500 dark:text-gray-400">3f1c…a92b</div>
                    <div class="mt-2 flex flex-wrap items-center justify-end gap-2">
                        <a href="#page-item" class="<?php echo $secondary; ?>"><i class="fas fa-qrcode text-xs" aria-hidden="true"></i> QR-этикетка</a>
                        <a href="#page-item" class="<?php echo $primary; ?>">Записать выпуск</a>
                    </div>
                </div>
            </header>

            <div class="grid gap-3 sm:grid-cols-3">
                <?php echo Component::render('kpi', array('label' => 'Количество по складу', 'value' => '120', 'unit' => 'шт', 'hint' => 'Количественная истина — в WMS')); ?>
                <?php echo Component::render('kpi', array('label' => 'Годен до', 'value' => '07.04.2027', 'unit' => '')); ?>
                <?php echo Component::render('kpi', array('label' => 'Связей происхождения', 'value' => '4', 'unit' => 'шт', 'url' => '#page-item')); ?>
            </div>

            <?php Component::begin('card', array(
                'title'    => 'Движения партии',
                'icon'     => 'fas fa-right-left',
                'subtitle' => 'Журнал только дополняется: исправление — новой записью, а не правкой старой.',
                'flush'    => TRUE,
            )); ?>
                <?php echo Component::render('table', array(
                    'caption'  => 'Движения партии',
                    'columns'  => array(
                        array('label' => 'Когда', 'priority' => 1, 'nowrap' => TRUE),
                        array('label' => 'Операция', 'priority' => 1),
                        array('label' => 'Основание', 'priority' => 2),
                        array('label' => 'Количество', 'priority' => 1, 'align' => 'right'),
                        array('label' => 'Склад', 'priority' => 3),
                    ),
                    'rows'        => $movement_rows,
                    'row_header'  => TRUE,
                    'empty_title' => 'Движений пока нет',
                    'empty_hint'  => 'Они появятся после приёмки или выпуска.',
                )); ?>
            <?php Component::end(); ?>

            <?php Component::begin('card', array('title' => 'Изменить состояние', 'icon' => 'fas fa-shield-halved', 'id' => 'sample-item-action')); ?>
                <form method="post" action="/pages/required_design_sample" autocomplete="off" class="flex flex-wrap items-start gap-3">
                    <div class="min-w-48">
                        <label for="item_status" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Новый статус</label>
                        <select id="item_status" name="status" class="<?php echo $input; ?>">
                            <option value="quarantine">Карантин</option>
                            <option value="available">Разрешить</option>
                            <option value="scrapped">Утиль</option>
                        </select>
                    </div>
                    <div class="min-w-64 flex-1">
                        <?php echo Component::render('form_field', array(
                            'id'       => 'item_reason',
                            'name'     => 'reason',
                            'label'    => 'Причина',
                            'required' => TRUE,
                            'hint'     => 'Попадёт в неизменяемый журнал: её увидит следующий сменщик',
                        )); ?>
                    </div>
                    <div class="pt-6">
                        <button type="submit" class="<?php echo $danger; ?>"
                                data-confirm="Это эталон: статус не изменится. Подтвердите, чтобы увидеть диалог.">
                            Изменить статус
                        </button>
                    </div>
                </form>
            <?php Component::end(); ?>
        </div>
        <?php
        echo '<div id="page-item">'
            .$showcase('2. Страница айтема',
                'Шапка: имя, состояние, номер · цифры сверху · журнал движений · опасное действие — с причиной и подтверждением',
                $sample(ob_get_clean()))
            .'</div>';
        ?>

        <?php
        // 3. Страница перечня айтемов.
        ob_start();
        echo Component::render('page_header', array(
            'title'        => 'Партии и серийные номера',
            'level'        => 2,
            'lead'         => 'Партии и serial, складские распределения и происхождение выпуска.',
            'crumbs'       => array(array('Склад', '/warehouse'), array('Партии', NULL)),
            'actions_html' => '<a href="#page-form" class="'.$primary.'"><i class="fas fa-plus text-xs" aria-hidden="true"></i> Новая партия</a>',
        ));
        echo Component::render('filters', array(
            'action'       => '/pages/required_design_sample',
            'method'       => 'get',
            'body'         => $filters_body('list'),
            'submit_label' => 'Найти',
            'reset_url'    => '/pages/required_design_sample',
        ));
        ?>
        <?php Component::begin('card', array(
            'title'    => 'Результаты отбора',
            'icon'     => 'fas fa-code-branch',
            'subtitle' => 'Найдено 468 позиций · показаны 1–4',
            'flush'    => TRUE,
        )); ?>
            <?php echo $table_html; ?>
            <div class="border-t border-gray-200 p-4 dark:border-gray-700"><?php echo $pagination_html; ?></div>
        <?php Component::end(); ?>
        <?php
        echo '<div id="page-list">'
            .$showcase('3. Страница перечня',
                'Отбор сверху · первая колонка — имя строки · приоритет колонок · листание · пустой результат объясняет причину',
                $sample(ob_get_clean()),
                $code_page)
            .'</div>';
        ?>

        <?php
        // 4. Страница сводной информации.
        ob_start();
        echo Component::render('page_header', array(
            'title'        => 'Сводка по производству',
            'level'        => 2,
            'lead'         => 'План и факт смены, качество, обеспеченность материалами и простои.',
            'crumbs'       => array(array('Аналитика', '/analytics'), array('Сводка', NULL)),
            'meta'         => 'Период: 02.10.2026 — 07.10.2026 · источник: цех, склад, качество',
            'actions_html' => '<a href="#page-summary" class="'.$secondary.'"><i class="fas fa-file-csv text-xs" aria-hidden="true"></i> Выгрузить CSV</a>',
        ));
        ?>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <?php echo Component::render('kpi', array('label' => 'Выполнение плана', 'value' => '91,6', 'unit' => '%', 'target' => '100', 'state' => 'warn', 'trend' => array(78, 84, 88, 86, 90, 92), 'url' => '/production/orders', 'hint' => '412 из 450 шт')); ?>
            <?php echo Component::render('kpi', array('label' => 'OEE', 'value' => '72', 'unit' => '%', 'target' => '75', 'state' => 'warn', 'trend' => array(64, 68, 70, 69, 71, 72), 'url' => '/analytics')); ?>
            <?php echo Component::render('kpi', array('label' => 'Годных с первого предъявления', 'value' => '96,4', 'unit' => '%', 'target' => '95', 'state' => 'ok', 'trend' => array(91, 93, 94, 95, 96, 96), 'url' => '/quality')); ?>
            <?php echo Component::render('kpi', array('label' => 'Дефицит материалов', 'value' => '4', 'unit' => 'позиции', 'state' => 'bad', 'trend' => array(1, 2, 2, 3, 4, 4), 'url' => '/planning', 'hint' => 'Критичных — 1')); ?>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
            <?php Component::begin('card', array('title' => 'Что закончится первым', 'icon' => 'fas fa-triangle-exclamation', 'subtitle' => 'Остаток ÷ средний расход в день.', 'flush' => TRUE)); ?>
                <?php echo Component::render('table', array(
                    'caption'  => 'Позиции с риском дефицита',
                    'columns'  => array(
                        array('label' => 'Номенклатура', 'priority' => 1),
                        array('label' => 'Остаток', 'priority' => 1, 'align' => 'right'),
                        array('label' => 'Оценка', 'priority' => 2),
                    ),
                    'rows'        => $deficit_rows,
                    'row_header'  => TRUE,
                    'empty_title' => 'Дефицитов нет',
                    'empty_hint'  => 'Все позиции покрыты запасом на горизонте планирования.',
                )); ?>
            <?php Component::end(); ?>

            <?php Component::begin('card', array('title' => 'Простои за период', 'icon' => 'fas fa-hourglass-half', 'subtitle' => 'Топ причин: с чего начинать разбор.', 'id' => 'sample-downtime')); ?>
                <ul class="space-y-3 text-sm">
                    <?php
                    foreach (array(
                        array('Переналадка', '4,5 ч', 45),
                        array('Ожидание материала', '2,8 ч', 28),
                        array('Отказ оснастки', '1,7 ч', 17),
                    ) as $row)
                    {
                        echo '<li><div class="flex items-baseline justify-between gap-3">'
                            .'<span class="font-medium text-gray-900 dark:text-white">'.HTML::chars($row[0]).'</span>'
                            .'<span class="tabular-nums text-gray-700 dark:text-gray-200">'.HTML::chars($row[1]).'</span>'
                            .'</div>'
                            .'<div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">'
                            .'<div class="h-full rounded-full bg-blue-600" role="img"'
                            .' aria-label="'.HTML::chars($row[0]).': '.HTML::chars($row[1]).' из 10 ч"'
                            .' data-progress="'.(int) $row[2].'"></div>'
                            .'</div></li>';
                    }
                    ?>
                </ul>
            <?php Component::end(); ?>
        </div>
        <?php
        echo '<div id="page-summary">'
            .$showcase('4. Страница сводной информации',
                'Цифры, требующие решения · у каждой — цель и переход в разрез · единицы не смешивать · ниже — детализация',
                $sample(ob_get_clean()))
            .'</div>';
        ?>
    </section>

    <section aria-labelledby="catalog-title" class="space-y-5">
        <div class="flex flex-wrap items-baseline justify-between gap-3">
            <h2 id="catalog-title" class="text-xl font-semibold text-gray-900 dark:text-white">Каталог компонентов</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Правило — одной строкой, под ним — живой пример.</p>
        </div>

        <?php
        echo $showcase('Состояния', 'ok · warn · bad · info · neutral — цвет и слово из одного словаря',
            '<div class="'.$panel.' flex flex-wrap items-center gap-2">'
            .Component::render('badge', array('label' => 'ok — в норме', 'state' => 'ok'))
            .Component::render('badge', array('label' => 'warn — внимание', 'state' => 'warn'))
            .Component::render('badge', array('label' => 'bad — проблема', 'state' => 'bad'))
            .Component::render('badge', array('label' => 'info — к сведению', 'state' => 'info'))
            .Component::render('badge', array('label' => 'neutral — без оценки', 'state' => 'neutral'))
            .'</div>');

        echo $showcase('Кнопки и действия', 'Одно главное действие · опасное — с подтверждением · у кнопки-иконки есть доступное имя',
            '<div class="'.$panel.' flex flex-wrap items-center gap-3">'
            .'<button type="button" class="'.$primary.'"><i class="fas fa-plus text-xs" aria-hidden="true"></i> Создать партию</button>'
            .'<button type="button" class="'.$secondary.'"><i class="fas fa-file-arrow-down text-xs" aria-hidden="true"></i> Выгрузить</button>'
            .'<button type="button" class="'.$quiet.'">Отмена</button>'
            .'<button type="button" class="'.$danger.'" data-confirm="Это эталон: действие не выполнится. Подтвердите, чтобы увидеть диалог.">Отозвать партию</button>'
            .'<button type="button" class="'.$secondary.' opacity-60" disabled aria-disabled="true">Недоступно</button>'
            .'<a href="#catalog-title" class="'.$icon_only.'" aria-label="Обновить список"><i class="fas fa-rotate-right text-xs" aria-hidden="true"></i></a>'
            .'</div>');

        // Поля формы и выбор сущности — одним блоком: так они и встречаются.
        ob_start();
        ?>
        <div class="<?php echo $panel; ?> grid grid-cols-1 gap-4 md:grid-cols-2">
            <?php echo Component::render('form_field', array(
                'id'    => 'field_lot',
                'name'  => 'lot_number',
                'label' => 'Номер партии',
                'value' => 'ЛТ-2026-00423',
                'hint'  => 'Пусто — сгенерируется автоматически',
            )); ?>
            <?php echo Component::render('form_field', array(
                'id'    => 'field_qty_error',
                'name'  => 'qty',
                'label' => 'Количество',
                'type'  => 'number',
                'value' => '0',
                'error' => 'Количество должно быть больше нуля',
            )); ?>
            <div>
                <label for="field_mode" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Режим учёта</label>
                <select id="field_mode" name="tracking_mode" class="<?php echo $input; ?>">
                    <option value="lot">Партия</option>
                    <option value="serial">Серийный учёт</option>
                </select>
            </div>
            <fieldset>
                <legend class="text-sm font-medium text-gray-700 dark:text-gray-200">Признаки партии</legend>
                <div class="mt-2 space-y-2">
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <input type="checkbox" name="needs_control" value="1" checked
                               class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800">
                        Требует входного контроля
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <input type="checkbox" name="is_sample" value="1"
                               class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800">
                        Образец для испытаний
                    </label>
                </div>
            </fieldset>
            <div class="md:col-span-2">
                <?php echo Component::render('smart_reference', array(
                    'id'    => 'field_item',
                    'name'  => 'item_id',
                    'label' => 'Номенклатура',
                    'url'   => URL::site('pages/design_sample_reference'),
                    'hint'  => 'Начните вводить: «корпус», «вал» или «КРН». Работает и с клавиатуры.',
                )); ?>
            </div>
        </div>
        <?php
        echo $showcase('Поля формы и выбор сущности',
            'Подпись у каждого поля · ошибка рядом с полем · сущность — поиском, а не вводом id',
            ob_get_clean(),
            $code_form);
        ?>

        <?php
        echo $showcase('Показатели', 'Подпись, единица, цель и переход в разрез · спарклайн без JavaScript',
            '<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">'
            .Component::render('kpi', array('label' => 'Выпуск за смену', 'value' => '412', 'unit' => 'шт', 'target' => '450', 'state' => 'warn', 'trend' => array(320, 366, 298, 401, 388, 412), 'url' => '/shopfloor', 'hint' => 'План смены 450 шт'))
            .Component::render('kpi', array('label' => 'Просроченных поставок', 'value' => '3', 'unit' => 'шт', 'state' => 'bad', 'trend' => array(0, 1, 1, 2, 2, 3), 'url' => '/procurement'))
            .Component::render('kpi', array('label' => 'Партий на карантине', 'value' => '1', 'unit' => 'шт', 'state' => 'info', 'compact' => TRUE, 'hint' => 'Мелкая плитка — в профессиональном режиме'))
            .Component::render('kpi', array('label' => 'Годных с первого предъявления', 'value' => '96,4', 'unit' => '%', 'target' => '95', 'state' => 'ok'))
            .'</div>',
            $code_kpi);
        ?>

        <?php
        ob_start();
        ?>
        <?php Component::begin('card', array('title' => 'Партии и серийные номера', 'icon' => 'fas fa-code-branch', 'flush' => TRUE)); ?>
            <?php echo Component::render('table', array(
                'caption'     => 'Партии и серийные номера',
                'columns'     => $columns,
                'rows'        => $rows,
                'row_header'  => TRUE,
                'empty_title' => 'Партии не найдены',
                'empty_hint'  => 'Измените фильтры или создайте первую партию.',
            )); ?>
        <?php Component::end(); ?>
        <?php echo $showcase('Таблица', 'Подпись, scope, приоритет колонок, прокрутка, пустое состояние, листание', ob_get_clean(), $code_table); ?>

        <?php
        ob_start();
        ?>
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <?php Component::begin('card', array('title' => 'Фильтры списка', 'icon' => 'fas fa-filter', 'id' => 'sample-filters')); ?>
                <?php echo Component::render('filters', array(
                    'action'       => '/pages/required_design_sample',
                    'method'       => 'get',
                    'body'         => $filters_body('catalog'),
                    'submit_label' => 'Применить',
                    'reset_url'    => '/pages/required_design_sample',
                )); ?>
            <?php Component::end(); ?>
            <?php Component::begin('card', array('title' => 'Панель инструментов', 'icon' => 'fas fa-sliders', 'id' => 'sample-toolbar')); ?>
                <?php echo Component::render('toolbar', array(
                    'title' => 'Отбор по складу',
                    'reset' => '/pages/required_design_sample',
                    'body'  => '<div><label for="toolbar_warehouse" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Склад</label>'
                        .'<select id="toolbar_warehouse" name="warehouse_id" class="'.$input.'">'
                        .'<option value="">Все склады</option><option value="1">Склад материалов</option>'
                        .'<option value="2">Склад готовой продукции</option></select></div>',
                )); ?>
            <?php Component::end(); ?>
            <?php Component::begin('card', array('title' => 'Пустое состояние и листание', 'icon' => 'fas fa-inbox', 'id' => 'sample-empty')); ?>
                <div class="rounded-lg border border-gray-200 dark:border-gray-700">
                    <?php echo Component::render('empty', array(
                        'title'       => 'Позиций по заданным условиям нет',
                        'hint'        => 'Пусто — тоже ответ: объясняем, почему его нечем заполнить.',
                        'action_html' => '<a href="#sample-empty" class="'.$secondary.'">Сбросить фильтры</a>',
                    )); ?>
                </div>
                <div class="mt-3"><?php echo $pagination_html; ?></div>
            <?php Component::end(); ?>
        </div>
        <?php
        echo $showcase('Фильтры, пустое состояние, листание',
            'Панель раскрывается сама, если отбор уже применён · адрес страницы сохраняет фильтры',
            ob_get_clean());
        ?>

        <?php
        echo $showcase('Сообщения', 'Результат — в живой области; причина отказа — рядом с действием',
            '<div class="space-y-3">'
            .Component::render('toast', array('message' => 'Партия создана: ЛТ-2026-00423', 'type' => 'success'))
            .Component::render('toast', array('message' => 'Черновик сохранён, отправьте на согласование', 'type' => 'info'))
            .Component::render('toast', array('message' => 'Остаток ниже минимума: 48 шт при минимуме 120 шт', 'type' => 'warning'))
            .Component::render('toast', array('message' => 'Не удалось сохранить: партия уже отгружена', 'type' => 'error'))
            .'<div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200">Количество должно быть больше нуля</div>'
            .'</div>');

        ob_start();
        ?>
        <div class="<?php echo $panel; ?> space-y-4">
            <?php echo Component::render('tabs', array(
                'label' => 'Разделы карточки партии',
                'tabs'  => array(
                    array('href' => '#page-item', 'label' => 'Партия', 'active' => TRUE),
                    array('href' => '#page-item', 'label' => 'Происхождение', 'count' => 4),
                    array('href' => '#page-item', 'label' => 'Контроль', 'count' => 2),
                ),
            )); ?>
            <?php Component::begin('more', array('summary' => 'Дополнительные настройки')); ?>
                <?php echo Component::render('form_field', array(
                    'id'    => 'more_expires',
                    'name'  => 'expires_at',
                    'label' => 'Годен до',
                    'type'  => 'date',
                    'hint'  => 'В профессиональном режиме поле видно сразу, в простом — здесь',
                )); ?>
            <?php Component::end(); ?>
            <div class="max-w-xs"><?php echo Component::render('mode_switch'); ?></div>
        </div>
        <?php
        echo $showcase('Вкладки, «Ещё» и режим интерфейса',
            'Вкладки — разделы страницы · «Ещё» — второстепенное · переключатель меняет вид, но не права',
            ob_get_clean());
        ?>
    </section>

    <?php
    // Операторский экран: крупные действия, минимум текста, сканер, статус связи.
    ob_start();
    ?>
    <div class="space-y-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <button type="button" class="flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-4 text-base font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-play" aria-hidden="true"></i> Начать
            </button>
            <button type="button" class="flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-4 text-base font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-pause" aria-hidden="true"></i> Пауза
            </button>
            <button type="button" class="flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-4 text-base font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <i class="fas fa-check" aria-hidden="true"></i> Выпуск
            </button>
        </div>

        <div>
            <label for="operator_scan" class="text-sm font-medium text-gray-700 dark:text-gray-200">Скан</label>
            <input id="operator_scan" name="scan" type="search" inputmode="none" autocomplete="off"
                   placeholder="Отсканируйте этикетку или введите код"
                   class="mt-1 block w-full rounded-lg border-2 border-gray-300 bg-white px-4 py-3 text-lg text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Крупное поле, подстановка браузера отключена: терминал не угадывает ввод. Курсор стоит здесь сразу.
            </p>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
            <span class="text-sm text-gray-700 dark:text-gray-200">Синхронизация: 12:04 · отправлено 48 из 48 записей</span>
            <?php echo Component::render('badge', array('label' => 'ok — связь есть', 'state' => 'ok')); ?>
        </div>
    </div>
    <?php
    echo $showcase('Операторский экран',
        'Крупные действия, минимум текста, сканер и статус связи. AI-чат не заменяет кнопку «Выпуск».',
        $sample(ob_get_clean(), TRUE));
    ?>

    <?php Component::begin('card', array('title' => 'Проверки и источники правил', 'icon' => 'fas fa-list-check', 'id' => 'checks')); ?>
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Что проверяется автоматически</h3>
                <?php
                $checks = array(
                    'bin/frontend-audit.py' => 'подпись и scope у таблиц, нет CDN, inline-обработчиков и inline-стилей',
                    'bin/console dev:audit' => 'вызовы компонентов, их обещания, flash-сообщения, строка фильтров',
                    'tests/Unit/ComponentsTest.php' => 'контракт компонентов: экранирование, единицы, состояния',
                    'bin/console dev:smoke' => 'маршрут отвечает 200, а не 500',
                );

                echo '<dl class="mt-2 space-y-1 text-sm">';

                foreach ($checks as $command => $meaning)
                {
                    echo '<div class="flex flex-wrap gap-x-2">'
                        .'<dt class="font-mono text-xs text-blue-700 dark:text-blue-400">'.HTML::chars($command).'</dt>'
                        .'<dd class="text-gray-600 dark:text-gray-300">— '.HTML::chars($meaning).'</dd>'
                        .'</div>';
                }

                echo '</dl>';
                ?>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Где читать правила целиком</h3>
                <?php
                $sources = array(
                    'application/views/components/README.md' => 'каталог компонентов, переменные, приоритет колонок',
                    'AGENTS.md §10 «Фронтенд»' => 'непереговорные правила интерфейса',
                    'docs/FRONTEND_STATUS.md' => 'измеряемый UI-долг и единый каркас полей',
                    'docs/MODULE_GUIDE.md' => 'каркас модуля: контроллер, сервис, модель, представление',
                );

                echo '<dl class="mt-2 space-y-1 text-sm">';

                foreach ($sources as $path => $meaning)
                {
                    echo '<div class="flex flex-wrap gap-x-2">'
                        .'<dt class="font-mono text-xs text-blue-700 dark:text-blue-400">'.HTML::chars($path).'</dt>'
                        .'<dd class="text-gray-600 dark:text-gray-300">— '.HTML::chars($meaning).'</dd>'
                        .'</div>';
                }

                echo '</dl>';
                ?>
            </div>
        </div>
        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            Эталон — витрина, а не истина: если пример расходится с компонентом, правильным считается код компонента,
            а страницу и README правят в том же коммите.
        </p>
    <?php Component::end(); ?>

</div>
