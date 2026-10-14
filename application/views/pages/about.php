<?php defined('SYSPATH') or die('No direct script access.');

/**
 * О сервисе: что такое PromERP, из каких частей состоит и чего в нём ещё нет.
 *
 * Страница открыта гостю, поэтому говорит только о том, что реально есть в
 * коде: каждый блок ниже соответствует подключённому модулю. Прежняя версия
 * показывала «дерево ИСУП» с бухгалтерией, зарплатой и ТОиР — этих модулей в
 * системе нет, и посетитель получал неверное представление о продукте.
 *
 * Полная и всегда свежая карта модулей строится из кода в разделе /docs
 * (`docs/MODULE_MAP.md`), поэтому здесь нет счётчиков функций и инструментов:
 * они устаревают быстрее, чем правится страница.
 */

// Сквозной путь заказа: так читается система сверху вниз.
$flow = array(
    array('icon' => 'fas fa-bullseye',        'title' => 'Спрос',        'text' => 'Сделка, заказ клиента или программа выпуска'),
    array('icon' => 'fas fa-calendar-days',   'title' => 'План',         'text' => 'Дефицит материалов и даты заказа'),
    array('icon' => 'fas fa-truck-ramp-box',  'title' => 'Закупка',      'text' => 'Заявка, заказ поставщику, приёмка'),
    array('icon' => 'fas fa-boxes-stacked',   'title' => 'Склад',        'text' => 'Остатки, резервы, движения'),
    array('icon' => 'fas fa-gears',           'title' => 'Производство', 'text' => 'Заказ на выпуск, операции в цехе, факт'),
    array('icon' => 'fas fa-clipboard-check', 'title' => 'Качество',     'text' => 'Контроль, брак, карантин, решение'),
    array('icon' => 'fas fa-truck-fast',      'title' => 'Отгрузка',     'text' => 'Резерв, отгрузка, счёт, оплата'),
    array('icon' => 'fas fa-calculator',      'title' => 'Результат',    'text' => 'Себестоимость и аналитика по факту'),
);

// Что внутри: области и модули. Тексты — по README модулей.
$areas = array(
    array(
        'title' => 'Производство и цех',
        'icon'  => 'fas fa-industry',
        'items' => array(
            array('Заказы на выпуск',        'Состав изделия из техкарты, резерв и списание материалов, выпуск готовой продукции на склад.'),
            array('Цех и смены',             'Рабочие центры, смены и календарь, маршрут заказа по операциям, терминал: годные, брак, простои.'),
            array('Технологические карты',   'Техпроцесс с нормами времени и стоимости; генерация по описанию изделия — моделью или по типовому шаблону без неё.'),
            array('Составы EBOM / MBOM',     'Версионируемые составы изделия: черновик, проверка, утверждение; утверждённый состав не правится, а получает новую ревизию.'),
            array('Планирование (MRP)',      'Программа выпуска, разузлование, покрытие складом и незавершёнкой, предложения на закупку или выпуск.'),
            array('Себестоимость',           'План и факт заказа с формулой каждой строки и ссылкой на первичный документ. Это не бухгалтерская книга.'),
        ),
    ),
    array(
        'title' => 'Склад, качество и партии',
        'icon'  => 'fas fa-boxes-stacked',
        'items' => array(
            array('Склад',                   'Склады, остатки, неизменяемый журнал движений, резервы и неснижаемые минимумы с уведомлением.'),
            array('Контроль качества',       'Входной и операционный контроль, коды дефектов, изоляция партии на карантин, решение и его исполнение складом.'),
            array('Партии и прослеживаемость','Партии и серийные номера, происхождение выпуска «из каких партий» и обратный вопрос «кого затронул брак поставщика».'),
            array('QR-маркировка',           'Наклейка с QR на изделии, партии, заказе или оборудовании; мобильный терминал для сканирования и инвентаризации.'),
        ),
    ),
    array(
        'title' => 'Снабжение и продажи',
        'icon'  => 'fas fa-handshake',
        'items' => array(
            array('Закупки',                 'Заявки, согласование, заказы поставщикам, приёмка с входным контролем и история цен.'),
            array('Продажи и отгрузка',      'Заказ клиента, проверка кредитного лимита, резерв, отгрузка со склада, счёт и оплата.'),
            array('CRM',                     'Воронка, сделки и действия менеджера; клиент остаётся общим контрагентом системы.'),
            array('Контрагенты',             'Единый справочник организаций, контактов, договоров и реквизитов: поставщики, покупатели, перевозчики.'),
            array('Документы и печать',      'Шаблоны печатных форм, выпуск документа со снимком, журнал печати; выпущенный документ не переписывается.'),
        ),
    ),
    array(
        'title' => 'Аналитика, интеграции и AI',
        'icon'  => 'fas fa-chart-line',
        'items' => array(
            array('Аналитика',               'Оборачиваемость и покрытие запасов, ABC/XYZ, OEE и Парето простоев, Парето брака, просрочка закупок.'),
            array('Интеграции',              'Приём CSV/JSON-пакетов с журналом обмена и сопоставлением внешних идентификаторов, первым — номенклатуры.'),
            array('MCP для AI-агентов',      'Инструменты чтения, расчёта и черновиков для LLM-агентов; опасные действия — только через подтверждение человека.'),
        ),
    ),
    array(
        'title' => 'Основа системы',
        'icon'  => 'fas fa-user-shield',
        'items' => array(
            array('Пользователи и доступ',   'Роли и области доступа, вход, блокировка и архив учётных записей.'),
            array('Сотрудники и структура',  'Подразделения, должности, штатные единицы и назначения — отдельно от учётной записи.'),
            array('Журнал действий',         'Кто, когда и что изменил: партия, норма, заказ имеют историю, которую нельзя стереть.'),
            array('Файлы, события, справочники', 'Файловое хранилище, шина событий, центр уведомлений, реестр номенклатуры и миграции базы.'),
        ),
    ),
);

// Принципы, которые одинаково действуют во всех разделах.
$principles = array(
    array('fas fa-sliders',        'Простой и профессиональный режим', 'По умолчанию — простой: меньше колонок и кнопок, понятные названия. Профессиональный включается переключателем в шапке и запоминается для вашей учётной записи.'),
    array('fas fa-link',           'Каждый факт — у одного владельца', 'Склад хранит остатки, реестр — номенклатуру, контрагенты — организации. Остальные модули обращаются к ним через сервисы и не копируют данные.'),
    array('fas fa-clock-rotate-left', 'История и неизменяемость',      'Движения склада, генеалогия партий и выпущенные документы только добавляются. Исправление — новой записью, а не правкой старой.'),
    array('fas fa-robot',          'AI помогает, но не решает',        'Агент объясняет расчёт и готовит черновик, источником истины остаётся учёт. Утверждение и отзыв партии подтверждает человек.'),
);

// Границы продукта: честно о том, чего нет.
$limits = array(
    'бухгалтерский учёт, главная книга и финансовая отчётность — себестоимость считается для управления, а не для проводок;',
    'расчёт зарплаты и кадровое делопроизводство — есть только структура и назначения сотрудников;',
    'обслуживание и ремонт оборудования (ППР, запчасти);',
    'полноценный PLM: сравнение ревизий состава и ECR/ECO пока в проработке.',
);

// Кнопки и плитки — те же классы, что на /demo и в модулях
$button_primary   = 'inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500';
$button_secondary = 'inline-flex items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500';
$tile             = 'rounded-lg border bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 p-4';
?>

<div class="space-y-6">

    <?php echo Component::render('page_header', array(
        'title'        => 'PromERP — ERP для производственного предприятия',
        'lead'         => 'Заказы, техпроцесс, склад, закупки, качество, отгрузка и себестоимость в одной системе. '
            .'Каждому на экране — то, что требует решения именно от него: снабженцу дефицит, мастеру сменное задание, '
            .'кладовщику приёмка, директору отклонения и деньги.',
        'actions_html' => '<a href="/docs" class="'.$button_secondary.'">Документация</a>'
            .'<a href="/demo" class="'.$button_primary.'"><i class="fas fa-play" aria-hidden="true"></i> Посмотреть демонстрацию</a>',
    )); ?>

    <section aria-labelledby="flow-title">
        <h2 id="flow-title" class="font-semibold text-gray-900 dark:text-white">Путь заказа сквозь систему</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Шаги связаны между собой: результат одного становится входом следующего, без повторного ввода.
        </p>

        <ol class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <?php foreach ($flow as $n => $step): ?>
                <li class="<?php echo $tile; ?>">
                    <span class="block text-xs font-semibold text-blue-600 dark:text-blue-400">Шаг <?php echo ($n + 1); ?></span>
                    <span class="mt-1 flex items-center gap-2 font-medium text-gray-900 dark:text-white">
                        <i class="<?php echo HTML::chars($step['icon']); ?> text-blue-600 dark:text-blue-400" aria-hidden="true"></i>
                        <?php echo HTML::chars($step['title']); ?>
                    </span>
                    <span class="mt-2 block text-sm text-gray-600 dark:text-gray-300"><?php echo HTML::chars($step['text']); ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <?php foreach ($areas as $n => $area): ?>
        <?php Component::begin('card', array(
            'title' => $area['title'],
            'icon'  => $area['icon'],
            'id'    => 'area-'.$n,
        )); ?>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($area['items'] as $item): ?>
                    <div class="<?php echo $tile; ?>">
                        <p class="font-medium text-gray-900 dark:text-white"><?php echo HTML::chars($item[0]); ?></p>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300"><?php echo HTML::chars($item[1]); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php Component::end(); ?>
    <?php endforeach; ?>

    <?php Component::begin('card', array('title' => 'Как это устроено', 'icon' => 'fas fa-compass-drafting', 'id' => 'principles')); ?>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <?php foreach ($principles as $principle): ?>
                <div class="<?php echo $tile; ?> flex items-start gap-3">
                    <i class="<?php echo HTML::chars($principle[0]); ?> mt-0.5 text-blue-600 dark:text-blue-400" aria-hidden="true"></i>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white"><?php echo HTML::chars($principle[1]); ?></p>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300"><?php echo HTML::chars($principle[2]); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php Component::end(); ?>

    <?php Component::begin('card', array('title' => 'Чего в системе пока нет', 'icon' => 'fas fa-circle-info', 'state' => 'warn', 'id' => 'limits')); ?>
        <ul class="list-disc space-y-1 pl-5 text-sm text-gray-700 dark:text-gray-200">
            <?php foreach ($limits as $limit): ?>
                <li><?php echo HTML::chars($limit); ?></li>
            <?php endforeach; ?>
        </ul>
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            Полная карта модулей, таблиц и бизнес-процессов строится из кода и всегда актуальна —
            в <a href="/docs" class="text-blue-600 dark:text-blue-400 hover:underline">документации</a>.
        </p>
    <?php Component::end(); ?>

</div>
