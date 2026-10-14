<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Сценарий демонстрации: «Печатня 3D» — контрактная 3D-печать.
 *
 * Зачем вымышленное производство, а не «пример из документации»: показать
 * работающую систему можно только на связных данных. Здесь описан мир целиком —
 * клиенты, материалы, детали, принтеры, техкарты, причины простоев и дефектов, —
 * а `Service_Erp811_Seeder` превращает это описание в записи всех модулей
 * контура. Одно описание = одна правда: те же данные видит лендинг `/demo`,
 * тот же мир показывает тур по системе, и они же попадают в базу.
 *
 * Почему именно 3D-печать: тема смежная с text-to-3d.ru (параметрические детали
 * из текстового описания) и при этом честный производственный контур — заказы,
 * техпроцесс, оборудование, контроль размеров, карантин брака. Такой стенд
 * узнаёт любой, кто печатает на заказ: клипсы и заглушки для авто, формы для
 * печенья, корпуса электроники, шестерни и редукторы, таблички с надписями.
 *
 * Единицы: длина — мм, масса — г, время — ч, деньги — ₽.
 */

return array(

    // -------------------------------------------------------------------------
    //  Компания
    // -------------------------------------------------------------------------

    'company' => array(
        'name'      => 'Печатня 3D',
        'tagline'   => 'Контрактная 3D-печать по параметрическим моделям',
        'city'      => 'Казань',
        'site'      => 'print3d.example',
        'about'     => 'Четыре FDM-принтера и один фотополимерный, участок постобработки и стол контроля размеров. '
                     . 'Заказы приходят из параметрического каталога: клиент описывает деталь, система строит модель, '
                     . 'производство печатает партию и передаёт её с паспортом качества.',
        'shift'     => 'Две смены, 5/2, дневная 08:00–17:00, вечерняя 17:00–22:00',
    ),

    // -------------------------------------------------------------------------
    //  Клиенты: у каждого свой характер заказов
    // -------------------------------------------------------------------------

    'clients' => array(
        array('code' => 'CL-4101', 'name' => 'ООО «АвтоПласт-Сервис»', 'inn' => '1659123401', 'kind' => 'company',
              'city' => 'Казань', 'role' => 'дилер автозапчастей', 'rating' => 5,
              'about' => 'Клипсы, пистоны и заглушки для салонов: партии по 200–600 шт., повторяются каждый месяц.'),
        array('code' => 'CL-4102', 'name' => 'ООО «Кондитер-Про»', 'inn' => '1658234502', 'kind' => 'company',
              'city' => 'Москва', 'role' => 'производство форм для печенья', 'rating' => 5,
              'about' => 'Формы для печенья с гравировкой надписи: тираж сезонный, перед праздниками — пик.'),
        array('code' => 'CL-4103', 'name' => 'ООО «МедТех-Лаб»', 'inn' => '1660345603', 'kind' => 'company',
              'city' => 'Казань', 'role' => 'разработка электроники', 'rating' => 4,
              'about' => 'Корпуса датчиков и панелей: важны допуски, каждая партия сопровождается паспортом качества.'),
        array('code' => 'CL-4104', 'name' => 'ИП Кузнецов А.В.', 'inn' => '165745670405', 'kind' => 'entrepreneur',
              'city' => 'Набережные Челны', 'role' => 'ремонт бытовой техники', 'rating' => 4,
              'about' => 'Шестерни и втулки под восстановление техники: единичные заказы, часто правки по месту.'),
        array('code' => 'CL-4105', 'name' => 'ООО «Ремонт-Быт»', 'inn' => '1653567805', 'kind' => 'company',
              'city' => 'Казань', 'role' => 'сервисный центр', 'rating' => 3,
              'about' => 'Органайзеры и крепления для мастерской: заказывают редко, но требуют срочности.'),
        array('code' => 'CL-4106', 'name' => 'Студия «Точка Дизайна»', 'inn' => '1654678906', 'kind' => 'company',
              'city' => 'Зеленодольск', 'role' => 'дизайн-студия', 'rating' => 4,
              'about' => 'Таблички, брелоки и объёмные логотипы: малые партии, важна внешняя поверхность.'),
    ),

    // -------------------------------------------------------------------------
    //  Поставщики материалов и расходников
    // -------------------------------------------------------------------------

    'suppliers' => array(
        array('code' => 'SP-4201', 'name' => 'ООО «Полимер-Склад»', 'inn' => '7701234501', 'kind' => 'company',
              'city' => 'Москва', 'rating' => 5, 'lead_days' => 5,
              'profile' => array('payment_terms' => 'отсрочка 14 дней', 'delivery' => 'СДЭК до склада', 'min_order' => 15000)),
        array('code' => 'SP-4202', 'name' => 'ООО «Филамент-Юг»', 'inn' => '2310345602', 'kind' => 'company',
              'city' => 'Краснодар', 'rating' => 4, 'lead_days' => 7,
              'profile' => array('payment_terms' => 'отсрочка 30 дней', 'delivery' => 'транспортная компания', 'min_order' => 20000)),
        array('code' => 'SP-4203', 'name' => 'ИП Сафин Р.Р.', 'inn' => '1656789003', 'kind' => 'entrepreneur',
              'city' => 'Казань', 'rating' => 4, 'lead_days' => 2,
              'profile' => array('payment_terms' => 'предоплата 50 %', 'delivery' => 'самовывоз', 'min_order' => 5000)),
        array('code' => 'SP-4204', 'name' => 'ООО «Сопла и Расходники»', 'inn' => '6601234004', 'kind' => 'company',
              'city' => 'Екатеринбург', 'rating' => 3, 'lead_days' => 10,
              'profile' => array('payment_terms' => 'постоплата', 'delivery' => 'Почта России', 'min_order' => 3000)),
    ),

    // -------------------------------------------------------------------------
    //  Материалы: то, что покупаем и расходуем
    // -------------------------------------------------------------------------

    // `print_hours` — часы печати на типовую партию (`batch`), а не на одну
    // деталь: цех планирует время операции на всю партию, а техкарта
    // пересчитывает норму на единицу — так план и выпуск сходятся.

    // Позиции, остаток по которым будет ниже минимума: стенд показывает
    // дефицит и заявку на закупку — рабочую ситуацию, а не идеальную витрину.
    'short_materials' => array('MAT-TPU-FLX'),

    // Страховой запас, который держит склад: в днях расхода (цель витрины
    // запасов — 21 день, поэтому 30 дней запаса выглядят как «норма»).
    'cover_days_target' => 30,

    'materials' => array(
        array('code' => 'MAT-PLA-BLK', 'name' => 'Пластик PLA, чёрный, 1,75 мм, 1 кг', 'polymer' => 'PLA',
              'color' => 'чёрный', 'diameter_mm' => 1.75, 'spool_kg' => 1.0, 'price' => 1850, 'min_qty' => 2,
              'supplier' => 'SP-4201', 'attrs' => array('nozzle_c' => 210, 'bed_c' => 60)),
        array('code' => 'MAT-PETG-GRY', 'name' => 'Пластик PETG, серый, 1,75 мм, 1 кг', 'polymer' => 'PETG',
              'color' => 'серый', 'diameter_mm' => 1.75, 'spool_kg' => 1.0, 'price' => 2100, 'min_qty' => 1.5,
              'supplier' => 'SP-4201', 'attrs' => array('nozzle_c' => 240, 'bed_c' => 80)),
        array('code' => 'MAT-PETG-WHT', 'name' => 'Пластик PETG, белый, 1,75 мм, 1 кг', 'polymer' => 'PETG',
              'color' => 'белый', 'diameter_mm' => 1.75, 'spool_kg' => 1.0, 'price' => 2150, 'min_qty' => 1.5,
              'supplier' => 'SP-4202', 'attrs' => array('nozzle_c' => 240, 'bed_c' => 80)),
        array('code' => 'MAT-ABS-BLK', 'name' => 'Пластик ABS, чёрный, 1,75 мм, 1 кг', 'polymer' => 'ABS',
              'color' => 'чёрный', 'diameter_mm' => 1.75, 'spool_kg' => 1.0, 'price' => 2400, 'min_qty' => 1.5,
              'supplier' => 'SP-4202', 'attrs' => array('nozzle_c' => 255, 'bed_c' => 100)),
        array('code' => 'MAT-TPU-FLX', 'name' => 'Пластик TPU-95A, гибкий, 1,75 мм, 0,5 кг', 'polymer' => 'TPU',
              'color' => 'натуральный', 'diameter_mm' => 1.75, 'spool_kg' => 0.5, 'price' => 3200, 'min_qty' => 0.5,
              'supplier' => 'SP-4203', 'attrs' => array('nozzle_c' => 230, 'bed_c' => 50)),
        array('code' => 'MAT-RES-GRY', 'name' => 'Фотополимер, серый, 1 кг (SLA)', 'polymer' => 'Resin',
              'color' => 'серый', 'diameter_mm' => 0, 'spool_kg' => 1.0, 'price' => 5400, 'min_qty' => 0.5,
              'supplier' => 'SP-4203', 'attrs' => array('layer_mm' => 0.05)),
        array('code' => 'CON-NOZ-04', 'name' => 'Сопло латунное 0,4 мм (M6)', 'polymer' => 'Расходник',
              'color' => '', 'diameter_mm' => 0.4, 'spool_kg' => 0, 'price' => 190, 'min_qty' => 10,
              'supplier' => 'SP-4204', 'attrs' => array()),
    ),

    // -------------------------------------------------------------------------
    //  Детали: то, что продаём
    // -------------------------------------------------------------------------

    'parts' => array(
        array('code' => 'PRT-CLIP-01', 'name' => 'Клипса-пистон 8×18 мм, PA-аналог', 'client' => 'CL-4101',
              'material' => 'MAT-PLA-BLK', 'weight_g' => 3.2, 'layer_mm' => 0.16, 'infill' => 30,
              'print_hours' => 18.0, 'batch' => 1200, 'price' => 9.5, 'tolerance' => '±0,15 мм',
              'note' => 'Ходовая позиция: заказ повторяется каждые 3–4 недели'),
        array('code' => 'PRT-PLUG-02', 'name' => 'Заглушка прямоугольная 40×18 мм', 'client' => 'CL-4101',
              'material' => 'MAT-ABS-BLK', 'weight_g' => 6.5, 'layer_mm' => 0.2, 'infill' => 25,
              'print_hours' => 16.0, 'batch' => 800, 'price' => 17.0, 'tolerance' => '±0,2 мм',
              'note' => 'ABS требует закрытой камеры — печатаем на №2'),
        array('code' => 'PRT-COOKIE-03', 'name' => 'Форма для печенья «С Новым годом»', 'client' => 'CL-4102',
              'material' => 'MAT-PETG-WHT', 'weight_g' => 28.0, 'layer_mm' => 0.2, 'infill' => 20,
              'print_hours' => 14.0, 'batch' => 320, 'price' => 145.0, 'tolerance' => '±0,3 мм',
              'note' => 'Пищевой PETG, обязательна проверка кромки'),
        array('code' => 'PRT-CASE-04', 'name' => 'Корпус датчика температуры, 62×34×21 мм', 'client' => 'CL-4103',
              'material' => 'MAT-PETG-GRY', 'weight_g' => 24.0, 'layer_mm' => 0.12, 'infill' => 35,
              'print_hours' => 12.0, 'batch' => 240, 'price' => 320.0, 'tolerance' => '±0,1 мм',
              'note' => 'Допуск по посадочным местам — паспорт качества обязателен'),
        array('code' => 'PRT-GEAR-05', 'name' => 'Шестерня прямозубая m1,5 z24', 'client' => 'CL-4104',
              'material' => 'MAT-RES-GRY', 'weight_g' => 18.5, 'layer_mm' => 0.05, 'infill' => 100,
              'print_hours' => 10.0, 'batch' => 80, 'price' => 410.0, 'tolerance' => '±0,08 мм',
              'note' => 'Смола: точность зуба держим только на SLA, партии небольшие'),
        array('code' => 'PRT-ORG-06', 'name' => 'Органайзер настенный 120×60 мм', 'client' => 'CL-4105',
              'material' => 'MAT-TPU-FLX', 'weight_g' => 42.0, 'layer_mm' => 0.24, 'infill' => 15,
              'print_hours' => 12.0, 'batch' => 100, 'price' => 520.0, 'tolerance' => '±0,4 мм',
              'note' => 'Гибкий TPU: скорость печати снижена, риск обрыва филамента'),
        array('code' => 'PRT-PLATE-07', 'name' => 'Табличка с гравировкой 90×30 мм', 'client' => 'CL-4106',
              'material' => 'MAT-PETG-WHT', 'weight_g' => 14.0, 'layer_mm' => 0.16, 'infill' => 20,
              'print_hours' => 8.0, 'batch' => 160, 'price' => 260.0, 'tolerance' => '±0,2 мм',
              'note' => 'Внешняя поверхность без следов поддержек'),
        array('code' => 'PRT-BRKT-08', 'name' => 'Кронштейн усиленный 150×150 мм', 'client' => 'CL-4105',
              'material' => 'MAT-ABS-BLK', 'weight_g' => 76.0, 'layer_mm' => 0.24, 'infill' => 40,
              'print_hours' => 22.0, 'batch' => 48, 'price' => 1180.0, 'tolerance' => '±0,5 мм',
              'note' => 'Долгая печать, занимает принтер целиком'),
    ),

    // -------------------------------------------------------------------------
    //  Оборудование и участки
    // -------------------------------------------------------------------------

    'centers' => array(
        array('code' => 'WC-PRNT-01', 'name' => 'Принтер FDM №1 (Bambu X1C)', 'kind' => 'center', 'workshop' => 'Печатный участок',
              'capacity_per_hour' => 1.0, 'cost_per_hour' => 180, 'note' => 'Основная нагрузка, сопло 0,4 мм'),
        array('code' => 'WC-PRNT-02', 'name' => 'Принтер FDM №2 (закрытая камера)', 'kind' => 'center', 'workshop' => 'Печатный участок',
              'capacity_per_hour' => 1.0, 'cost_per_hour' => 220, 'note' => 'ABS/ASA, высокая температура камеры'),
        array('code' => 'WC-PRNT-03', 'name' => 'Принтер FDM №3 (Prusa MK4)', 'kind' => 'center', 'workshop' => 'Печатный участок',
              'capacity_per_hour' => 1.0, 'cost_per_hour' => 150, 'note' => 'Мелкие партии и образцы'),
        array('code' => 'WC-PRNT-04', 'name' => 'Принтер FDM №4 (большое поле)', 'kind' => 'center', 'workshop' => 'Печатный участок',
              'capacity_per_hour' => 1.0, 'cost_per_hour' => 170, 'note' => 'Крупные детали до 350 мм'),
        array('code' => 'WC-RESIN-05', 'name' => 'Принтер SLA (фотополимер)', 'kind' => 'center', 'workshop' => 'Печатный участок',
              'capacity_per_hour' => 0.6, 'cost_per_hour' => 260, 'note' => 'Мелкие точные детали, слой 0,05 мм'),
        array('code' => 'WC-POST-06', 'name' => 'Постобработка и сборка', 'kind' => 'area', 'workshop' => 'Участок отделки',
              'capacity_per_hour' => 4.0, 'cost_per_hour' => 350, 'note' => 'Снятие поддержек, шлифовка, мойка SLA'),
        array('code' => 'WC-QC-07', 'name' => 'Стол контроля размеров', 'kind' => 'area', 'workshop' => 'ОТК',
              'capacity_per_hour' => 8.0, 'cost_per_hour' => 280, 'note' => 'Штангенциркуль, микрометр, калибры'),
    ),

    'shifts' => array(
        array('code' => 'SH-DAY', 'name' => 'Дневная смена', 'start_time' => '08:00', 'end_time' => '17:00', 'duration_hours' => 8, 'sort_order' => 10, 'note' => 'Основная загрузка парков'),
        array('code' => 'SH-EVE', 'name' => 'Вечерняя смена', 'start_time' => '17:00', 'end_time' => '22:00', 'duration_hours' => 5, 'sort_order' => 20, 'note' => 'Печать длинных заданий без оператора'),
    ),

    // -------------------------------------------------------------------------
    //  Техпроцесс: маршруты по деталям (шаги техкарты)
    // -------------------------------------------------------------------------

    'routes' => array(
        // Ключ — материал детали: маршрут один на технологию.
        'FDM' => array(
            array('op_name' => 'Подготовка модели и слайсинг', 'duration_sec' => 900, 'cost_amount' => 250,
                  'params' => array('check' => 'толщина стенки ≥ 1,2 мм, поддержки только там, где нужны')),
            array('op_name' => 'Печать партии', 'duration_sec' => 0, 'cost_amount' => 0,
                  'params' => array('control' => 'первый слой визуально, далее по камере')),
            array('op_name' => 'Снятие поддержек и шлифовка', 'duration_sec' => 1200, 'cost_amount' => 300,
                  'params' => array('tool' => 'бокорезы, надфиль, шкурка 400')),
            array('op_name' => 'Контроль размеров', 'duration_sec' => 600, 'cost_amount' => 200,
                  'params' => array('tool' => 'штангенциркуль 0,01 мм, калибр')),
        ),
        'SLA' => array(
            array('op_name' => 'Подготовка модели и ориентация', 'duration_sec' => 1200, 'cost_amount' => 300,
                  'params' => array('check' => 'уклон поддержек 45°, линия отрыва')),
            array('op_name' => 'Печать на SLA', 'duration_sec' => 0, 'cost_amount' => 0, 'params' => array()),
            array('op_name' => 'Мойка и постотверждение', 'duration_sec' => 1500, 'cost_amount' => 350,
                  'params' => array('tool' => 'изопропанол, УФ-камера 15 мин')),
            array('op_name' => 'Контроль размеров', 'duration_sec' => 900, 'cost_amount' => 250,
                  'params' => array('tool' => 'микрометр')),
        ),
    ),

    // -------------------------------------------------------------------------
    //  Цех: почему принтер стоит и откуда берётся брак
    // -------------------------------------------------------------------------

    // Ключи `reason` — из справочника модуля цеха (Model_Erp408_Operation::$downtime_reasons):
    // произвольную причину сервис не примет. Названия — язык производства.
    'downtime_reasons' => array(
        array('reason' => 'equipment',   'label' => 'Засор сопла, чистка головы',            'weight' => 3),
        array('reason' => 'no_material', 'label' => 'Кончился филамент, замена катушки',      'weight' => 4),
        array('reason' => 'setup',       'label' => 'Калибровка стола и первого слоя',        'weight' => 3),
        array('reason' => 'no_tool',     'label' => 'Нет сменного сопла нужного диаметра',    'weight' => 1),
        array('reason' => 'quality',     'label' => 'Разбор брака с контролёром',             'weight' => 2),
        array('reason' => 'no_staff',    'label' => 'Ожидание оператора следующей смены',     'weight' => 1),
    ),

    'defects' => array(
        array('code' => 'DEF-UNDER',  'name' => 'Недоэкструзия, пропуски слоя', 'category' => 'other',    'weight' => 4,
              'note' => 'Пониженная подача или частичный засор сопла'),
        array('code' => 'DEF-DELAM',  'name' => 'Расслоение по слоям',          'category' => 'material', 'weight' => 3,
              'note' => 'Низкая температура сопла или сквозняк'),
        array('code' => 'DEF-WARP',   'name' => 'Коробление и отрыв от стола',   'category' => 'size',     'weight' => 3,
              'note' => 'ABS в открытой камере, плохая адгезия'),
        array('code' => 'DEF-SIZE',   'name' => 'Размер вне допуска',            'category' => 'size',     'weight' => 2,
              'note' => 'Усадка материала, ошибка калибровки осей'),
        array('code' => 'DEF-STRING', 'name' => 'Сопли и паутина на поверхности','category' => 'surface',  'weight' => 2,
              'note' => 'Влажный филамент, высокая температура'),
        array('code' => 'DEF-SUPP',   'name' => 'Следы поддержек на лицевой стороне', 'category' => 'surface', 'weight' => 1,
              'note' => 'Ориентация модели выбрана неудачно'),
    ),

    // -------------------------------------------------------------------------
    //  Сотрудники и оргструктура (erp310_workforce)
    //
    //  Подразделения и должности из миграции модуля (COMPANY, PRODUCTION,
    //  WAREHOUSE; PROD-MANAGER, OPERATOR, STOREKEEPER) не пересоздаются:
    //  `Service_Erp811_Contour` находит их по коду и дополняет. Даты заданы в
    //  днях «назад от сегодня», чтобы стенд не старел.
    // -------------------------------------------------------------------------

    'workforce' => array(
        // code => [name, kind, parent, sort]
        'departments' => array(
            array('code' => 'COMPANY',    'name' => 'Печатня 3D',            'kind' => 'company',    'parent' => '',           'sort' => 10),
            array('code' => 'PRODUCTION', 'name' => 'Производство',          'kind' => 'workshop',   'parent' => 'COMPANY',    'sort' => 20),
            array('code' => 'WAREHOUSE',  'name' => 'Склад',                 'kind' => 'department', 'parent' => 'COMPANY',    'sort' => 30),
            array('code' => 'SALES',      'name' => 'Отдел продаж',          'kind' => 'department', 'parent' => 'COMPANY',    'sort' => 40),
            array('code' => 'PLAN',       'name' => 'Планирование и закупки', 'kind' => 'department', 'parent' => 'COMPANY',    'sort' => 50),
            array('code' => 'QC',         'name' => 'ОТК',                   'kind' => 'department', 'parent' => 'COMPANY',    'sort' => 60),
            array('code' => 'PRINT',      'name' => 'Печатный участок',      'kind' => 'section',    'parent' => 'PRODUCTION', 'sort' => 21),
            array('code' => 'FINISH',     'name' => 'Участок отделки',       'kind' => 'section',    'parent' => 'PRODUCTION', 'sort' => 22),
        ),

        'positions' => array(
            array('code' => 'DIRECTOR',   'name' => 'Генеральный директор',        'department' => 'COMPANY', 'category' => 'director'),
            array('code' => 'SALES-HEAD', 'name' => 'Руководитель отдела продаж',  'department' => 'SALES',   'category' => 'manager'),
            array('code' => 'SALES-MGR',  'name' => 'Менеджер по продажам',        'department' => 'SALES',   'category' => 'specialist'),
            array('code' => 'PLANNER',    'name' => 'Планировщик производства',    'department' => 'PLAN',    'category' => 'specialist'),
            array('code' => 'BUYER',      'name' => 'Специалист по закупкам',      'department' => 'PLAN',    'category' => 'specialist'),
            array('code' => 'PRINT-OP',   'name' => 'Оператор 3D-печати',          'department' => 'PRINT',   'category' => 'worker'),
            array('code' => 'FINISH-OP',  'name' => 'Слесарь-отделочник',          'department' => 'FINISH',  'category' => 'worker'),
            array('code' => 'QC-HEAD',    'name' => 'Начальник ОТК',               'department' => 'QC',       'category' => 'manager'),
            array('code' => 'QC-INSP',    'name' => 'Контролёр ОТК',               'department' => 'QC',       'category' => 'specialist'),
        ),

        // Штатное расписание: planned_fte больше числа работающих — это вакансии
        'staff_units' => array(
            array('department' => 'COMPANY',    'position' => 'DIRECTOR',     'fte' => 1,   'note' => ''),
            array('department' => 'PRODUCTION', 'position' => 'PROD-MANAGER', 'fte' => 1,   'note' => ''),
            array('department' => 'PRINT',      'position' => 'PRINT-OP',     'fte' => 5,   'note' => 'Две вакансии: третья смена на печать и замена уволенного оператора'),
            array('department' => 'FINISH',     'position' => 'FINISH-OP',    'fte' => 2,   'note' => 'Вторая ставка открыта под рост постобработки'),
            array('department' => 'QC',         'position' => 'QC-HEAD',      'fte' => 1,   'note' => ''),
            array('department' => 'QC',         'position' => 'QC-INSP',      'fte' => 2,   'note' => 'Нужен второй контролёр на смену'),
            array('department' => 'SALES',      'position' => 'SALES-HEAD',   'fte' => 1,   'note' => ''),
            array('department' => 'SALES',      'position' => 'SALES-MGR',    'fte' => 2,   'note' => 'Вакансия менеджера по корпоративным клиентам'),
            array('department' => 'PLAN',       'position' => 'PLANNER',      'fte' => 1,   'note' => ''),
            array('department' => 'PLAN',       'position' => 'BUYER',        'fte' => 1,   'note' => ''),
            array('department' => 'WAREHOUSE',  'position' => 'STOREKEEPER',  'fte' => 1,   'note' => ''),
        ),

        // Порядок важен: руководитель должен быть заведён раньше подчинённого.
        // user — ключ демо-пользователя (admin/manager/worker/quality) или пусто.
        // prior — прошлое назначение: сотрудник перешёл между участками.
        // dismissed_days — уволен столько дней назад; status leave — в отпуске.
        'employees' => array(
            array('no' => 'T-0001', 'last' => 'Орлов', 'first' => 'Дмитрий', 'middle' => 'Сергеевич',
                  'dept' => 'COMPANY', 'pos' => 'DIRECTOR', 'manager' => '', 'user' => 'admin',
                  'hired_days' => 1500, 'phone' => '+7 (843) 555-01-01', 'email' => 'o.orlov@print3d.example',
                  'note' => 'Основатель; ведёт ключевых клиентов'),
            array('no' => 'T-0002', 'last' => 'Смирнова', 'first' => 'Анна', 'middle' => 'Викторовна',
                  'dept' => 'SALES', 'pos' => 'SALES-HEAD', 'manager' => 'T-0001', 'user' => 'manager',
                  'hired_days' => 1100, 'phone' => '+7 (843) 555-01-02', 'email' => 'a.smirnova@print3d.example'),
            array('no' => 'T-0003', 'last' => 'Гареев', 'first' => 'Рустам', 'middle' => 'Ильдарович',
                  'dept' => 'PRODUCTION', 'pos' => 'PROD-MANAGER', 'manager' => 'T-0001', 'user' => 'worker',
                  'hired_days' => 1300, 'phone' => '+7 (843) 555-01-03', 'email' => 'r.gareev@print3d.example'),
            array('no' => 'T-0004', 'last' => 'Литвинова', 'first' => 'Ольга', 'middle' => 'Павловна',
                  'dept' => 'QC', 'pos' => 'QC-HEAD', 'manager' => 'T-0001', 'user' => 'quality',
                  'hired_days' => 950, 'phone' => '+7 (843) 555-01-04', 'email' => 'o.litvinova@print3d.example'),
            array('no' => 'T-0005', 'last' => 'Хабибуллин', 'first' => 'Тимур', 'middle' => 'Расимович',
                  'dept' => 'PRINT', 'pos' => 'PRINT-OP', 'manager' => 'T-0003', 'user' => '',
                  'hired_days' => 900, 'phone' => '+7 (843) 555-01-05', 'email' => 't.habibullin@print3d.example'),
            array('no' => 'T-0006', 'last' => 'Зайцева', 'first' => 'Алина', 'middle' => 'Андреевна',
                  'dept' => 'PRINT', 'pos' => 'PRINT-OP', 'manager' => 'T-0003', 'user' => '',
                  'hired_days' => 700, 'phone' => '+7 (843) 555-01-06', 'email' => 'a.zaytseva@print3d.example'),
            array('no' => 'T-0007', 'last' => 'Белов', 'first' => 'Игорь', 'middle' => 'Николаевич',
                  'dept' => 'PRINT', 'pos' => 'PRINT-OP', 'manager' => 'T-0003', 'user' => '',
                  'hired_days' => 400, 'phone' => '+7 (843) 555-01-07', 'email' => 'i.belov@print3d.example',
                  'status' => 'leave', 'note' => 'Ежегодный отпуск'),
            array('no' => 'T-0008', 'last' => 'Фаизов', 'first' => 'Артур', 'middle' => 'Маратович',
                  'dept' => 'PRINT', 'pos' => 'PRINT-OP', 'manager' => 'T-0003', 'user' => '',
                  'hired_days' => 800, 'phone' => '+7 (843) 555-01-08', 'email' => 'a.faizov@print3d.example',
                  'dismissed_days' => 60, 'note' => 'Уволен по собственному желанию'),
            array('no' => 'T-0009', 'last' => 'Сабиров', 'first' => 'Эдуард', 'middle' => 'Ленарович',
                  'dept' => 'FINISH', 'pos' => 'FINISH-OP', 'manager' => 'T-0003', 'user' => '',
                  'hired_days' => 600, 'phone' => '+7 (843) 555-01-09', 'email' => 'e.sabirov@print3d.example',
                  'prior' => array('dept' => 'PRINT', 'pos' => 'PRINT-OP', 'moved_days' => 200),
                  'note' => 'Начинал оператором печати, перешёл на отделку'),
            array('no' => 'T-0010', 'last' => 'Никитина', 'first' => 'Елена', 'middle' => 'Олеговна',
                  'dept' => 'QC', 'pos' => 'QC-INSP', 'manager' => 'T-0004', 'user' => '',
                  'hired_days' => 500, 'phone' => '+7 (843) 555-01-10', 'email' => 'e.nikitina@print3d.example'),
            array('no' => 'T-0011', 'last' => 'Гимадеев', 'first' => 'Руслан', 'middle' => 'Рамилевич',
                  'dept' => 'SALES', 'pos' => 'SALES-MGR', 'manager' => 'T-0002', 'user' => '',
                  'hired_days' => 350, 'phone' => '+7 (843) 555-01-11', 'email' => 'r.gimadeev@print3d.example'),
            array('no' => 'T-0012', 'last' => 'Крылова', 'first' => 'Мария', 'middle' => 'Андреевна',
                  'dept' => 'PLAN', 'pos' => 'PLANNER', 'manager' => 'T-0001', 'user' => '',
                  'hired_days' => 750, 'phone' => '+7 (843) 555-01-12', 'email' => 'm.krylova@print3d.example'),
            array('no' => 'T-0013', 'last' => 'Валиев', 'first' => 'Ильяс', 'middle' => 'Фанисович',
                  'dept' => 'PLAN', 'pos' => 'BUYER', 'manager' => 'T-0012', 'user' => '',
                  'hired_days' => 420, 'phone' => '+7 (843) 555-01-13', 'email' => 'i.valiev@print3d.example'),
            array('no' => 'T-0014', 'last' => 'Тагирова', 'first' => 'Гузель', 'middle' => 'Рафиковна',
                  'dept' => 'WAREHOUSE', 'pos' => 'STOREKEEPER', 'manager' => 'T-0003', 'user' => '',
                  'hired_days' => 650, 'phone' => '+7 (843) 555-01-14', 'email' => 'g.tagirova@print3d.example'),
        ),
    ),

    // -------------------------------------------------------------------------
    //  CRM (erp221_crm): профили клиентов, контакты, сделки, база знаний
    //
    //  Клиенты — те же CL-41xx, что и в `clients`. Суммы сделок посчитаны из
    //  цен и партий деталей, поэтому сходятся с каталогом. Дни — «назад от
    //  сегодня» (отрицательные — в будущем).
    // -------------------------------------------------------------------------

    'crm' => array(
        'profiles' => array(
            'CL-4101' => array('segment' => 'Дилеры', 'industry' => 'Автозапчасти', 'annual_revenue' => 84000000,
                'source' => 'Рекомендация', 'owner' => 'manager',
                'notes' => 'Берёт мелкие пластиковые детали партиями каждые 3–4 недели. Чувствителен к срокам, цену сравнивает с литьём.'),
            'CL-4102' => array('segment' => 'Производители', 'industry' => 'Кондитерское производство', 'annual_revenue' => 36000000,
                'source' => 'Выставка', 'owner' => 'manager',
                'notes' => 'Сезонный спрос: пик в ноябре–декабре. Важны пищевой материал и аккуратная кромка форм.'),
            'CL-4103' => array('segment' => 'Производители', 'industry' => 'Электроника', 'annual_revenue' => 52000000,
                'source' => 'Входящий звонок', 'owner' => 'manager',
                'notes' => 'Требует паспорт качества на каждую партию и допуски по посадочным местам.'),
            'CL-4104' => array('segment' => 'Малый бизнес', 'industry' => 'Ремонт техники', 'annual_revenue' => 4800000,
                'source' => 'Сайт', 'owner' => 'manager',
                'notes' => 'Небольшие партии на замену изношенных деталей, часто по образцу. Решения принимает сам.'),
            'CL-4105' => array('segment' => 'Сервисные компании', 'industry' => 'Ремонт и сервис', 'annual_revenue' => 21000000,
                'source' => 'Рекомендация', 'owner' => 'manager',
                'notes' => 'Заказывает органайзеры и кронштейны; торгуется по цене и просит отсрочку платежа.'),
            'CL-4106' => array('segment' => 'Малый бизнес', 'industry' => 'Дизайн и реклама', 'annual_revenue' => 7500000,
                'source' => 'Соцсети', 'owner' => 'manager',
                'notes' => 'Разовые заказы под выставки и презентации, внешний вид важнее допуска.'),
        ),

        // level: lpr — лицо, принимающее решение; lvpr — влияющее; user — пользователь
        'contacts' => array(
            'CL-4101' => array(
                array('name' => 'Ахметзянов Ильнар Рустэмович', 'position' => 'Руководитель закупок', 'phone' => '+7 (843) 555-02-01',
                      'email' => 'i.akhmetzyanov@autoplast.example', 'level' => 'lpr', 'channel' => 'telegram', 'primary' => TRUE),
                array('name' => 'Васильева Светлана', 'position' => 'Логист', 'phone' => '+7 (843) 555-02-02',
                      'email' => 'logist@autoplast.example', 'level' => 'user', 'channel' => 'phone', 'primary' => FALSE),
            ),
            'CL-4102' => array(
                array('name' => 'Мельникова Ирина Павловна', 'position' => 'Коммерческий директор', 'phone' => '+7 (495) 555-03-01',
                      'email' => 'melnikova@konditer-pro.example', 'level' => 'lpr', 'channel' => 'email', 'primary' => TRUE),
            ),
            'CL-4103' => array(
                array('name' => 'Фролов Максим Андреевич', 'position' => 'Ведущий инженер', 'phone' => '+7 (843) 555-04-01',
                      'email' => 'frolov@medtech-lab.example', 'level' => 'lvpr', 'channel' => 'email', 'primary' => TRUE),
                array('name' => 'Кузнецова Дарья', 'position' => 'Менеджер по закупкам', 'phone' => '+7 (843) 555-04-02',
                      'email' => 'zakupki@medtech-lab.example', 'level' => 'lpr', 'channel' => 'phone', 'primary' => FALSE),
            ),
            'CL-4104' => array(
                array('name' => 'Кузнецов Андрей Владимирович', 'position' => 'Индивидуальный предприниматель', 'phone' => '+7 (8552) 55-05-01',
                      'email' => 'kuznetsov-remont@example.org', 'level' => 'lpr', 'channel' => 'whatsapp', 'primary' => TRUE),
            ),
            'CL-4105' => array(
                array('name' => 'Сафин Радик Миннуллович', 'position' => 'Директор сервисного центра', 'phone' => '+7 (843) 555-06-01',
                      'email' => 'safin@remont-byt.example', 'level' => 'lpr', 'channel' => 'phone', 'primary' => TRUE),
                array('name' => 'Орехова Наталья', 'position' => 'Менеджер по снабжению', 'phone' => '+7 (843) 555-06-02',
                      'email' => 'snab@remont-byt.example', 'level' => 'lvpr', 'channel' => 'email', 'primary' => FALSE),
            ),
            'CL-4106' => array(
                array('name' => 'Громова Полина Игоревна', 'position' => 'Арт-директор', 'phone' => '+7 (84371) 5-07-01',
                      'email' => 'polina@tochka-dizayna.example', 'level' => 'lpr', 'channel' => 'telegram', 'primary' => TRUE),
            ),
        ),

        // path — стадии по порядку (последняя — текущая). amount считается
        // «цена × партия × число партий»; loss — код причины отказа.
        // acts — события: type, subject, content, days (назад), status.
        'deals' => array(
            array('client' => 'CL-4101', 'title' => 'Рамочный договор на 12 партий клипс-пистонов', 'part' => 'PRT-CLIP-01', 'lots' => 12,
                  'path' => array('new', 'qualify', 'proposal', 'negotiation', 'won'), 'created_days' => 120, 'last_days' => 95,
                  'priority' => 'high', 'source' => 'Рекомендация', 'contact' => 0,
                  'description' => 'Годовой график поставок: одна партия 1200 шт. каждые 3–4 недели.',
                  'acts' => array(
                      array('type' => 'call', 'subject' => 'Первичный звонок', 'content' => 'Обсудили объёмы и график, клиент ушёл считать потребность', 'days' => 118, 'status' => 'completed'),
                      array('type' => 'meeting', 'subject' => 'Встреча на производстве', 'content' => 'Показали образцы, согласовали допуск ±0,15 мм', 'days' => 110, 'status' => 'completed'),
                      array('type' => 'email', 'subject' => 'Коммерческое предложение', 'content' => 'Отправили КП с ценой 9,5 ₽ за штуку при партии 1200', 'days' => 104, 'status' => 'completed'),
                  )),
            array('client' => 'CL-4101', 'title' => 'Заглушки ABS для салона: пробная партия', 'part' => 'PRT-PLUG-02', 'lots' => 1,
                  'path' => array('new', 'qualify', 'proposal', 'negotiation'), 'created_days' => 14, 'last_days' => 2,
                  'deadline_days' => -9, 'priority' => 'high', 'source' => 'Повторный заказ', 'contact' => 0,
                  'description' => 'Пробная партия 800 шт. Клиент сравнивает с литьём, торгуется по цене.',
                  'acts' => array(
                      array('type' => 'call', 'subject' => 'Уточнение по материалу', 'content' => 'Нужен ABS в чёрном цвете, закрытая камера', 'days' => 12, 'status' => 'completed'),
                      array('type' => 'email', 'subject' => 'Расчёт цены', 'content' => 'Отправили расчёт: 17 ₽ за штуку', 'days' => 6, 'status' => 'completed'),
                      array('type' => 'task', 'subject' => 'Согласовать скидку на объём', 'content' => 'Позвонить клиенту и предложить цену при заказе двух партий', 'days' => -2, 'status' => 'pending'),
                  )),
            array('client' => 'CL-4102', 'title' => 'Новогодняя серия форм для печенья', 'part' => 'PRT-COOKIE-03', 'lots' => 2,
                  'path' => array('new', 'qualify', 'proposal'), 'created_days' => 6, 'last_days' => 1,
                  'priority' => 'high', 'source' => 'Выставка', 'contact' => 0,
                  'description' => 'Две партии по 320 форм к новогоднему сезону, нужна проверка кромки.',
                  'acts' => array(
                      array('type' => 'meeting', 'subject' => 'Согласование дизайна', 'content' => 'Утвердили рисунок формы, пищевой PETG', 'days' => 5, 'status' => 'completed'),
                      array('type' => 'task', 'subject' => 'Подтвердить срок готовности', 'content' => 'Уточнить с производством дату выпуска первой партии', 'days' => -3, 'status' => 'pending'),
                  )),
            array('client' => 'CL-4102', 'title' => 'Формы с логотипом клиента, малая серия', 'amount' => 210000,
                  'path' => array('new', 'qualify', 'proposal', 'lost'), 'loss' => 'price', 'created_days' => 70, 'last_days' => 48,
                  'priority' => 'medium', 'source' => 'Выставка', 'contact' => 0,
                  'description' => 'Индивидуальная оснастка под брендирование.',
                  'acts' => array(
                      array('type' => 'meeting', 'subject' => 'Обсуждение макета', 'content' => 'Клиент хочет логотип по всему периметру формы', 'days' => 66, 'status' => 'completed'),
                      array('type' => 'call', 'subject' => 'Отказ по цене', 'content' => 'Клиент нашёл подрядчика дешевле, цену не поменяем', 'days' => 49, 'status' => 'completed'),
                  )),
            array('client' => 'CL-4103', 'title' => 'Корпуса датчиков температуры, серия 240 шт.', 'part' => 'PRT-CASE-04', 'lots' => 1,
                  'path' => array('new', 'qualify', 'proposal', 'negotiation', 'won'), 'created_days' => 100, 'last_days' => 80,
                  'priority' => 'high', 'source' => 'Входящий звонок', 'contact' => 0,
                  'description' => 'Серия с паспортом качества, допуск ±0,1 мм по посадочным местам.',
                  'acts' => array(
                      array('type' => 'call', 'subject' => 'Входящий запрос', 'content' => 'Инженер прислал чертёж корпуса и описал требования', 'days' => 99, 'status' => 'completed'),
                      array('type' => 'meeting', 'subject' => 'Проверка пробного образца', 'content' => 'Образец подошёл по посадке, нужен паспорт качества на партию', 'days' => 90, 'status' => 'completed'),
                  )),
            array('client' => 'CL-4103', 'title' => 'Корпуса панели управления: новый проект', 'amount' => 150000,
                  'path' => array('new', 'qualify'), 'created_days' => 3, 'last_days' => 1,
                  'priority' => 'medium', 'source' => 'Повторный заказ', 'contact' => 1,
                  'description' => 'Новый корпус, чертежей пока нет — ждём ТЗ от инженеров клиента.',
                  'acts' => array(
                      array('type' => 'email', 'subject' => 'Запрос технического задания', 'content' => 'Попросили прислать модель и требования к материалу', 'days' => 2, 'status' => 'completed'),
                  )),
            array('client' => 'CL-4104', 'title' => 'Шестерни по образцу для ремонта редукторов', 'part' => 'PRT-GEAR-05', 'lots' => 1,
                  'path' => array('new', 'qualify', 'proposal', 'negotiation'), 'created_days' => 40, 'last_days' => 28,
                  'deadline_days' => 5, 'priority' => 'medium', 'source' => 'Сайт', 'contact' => 0,
                  'description' => 'Сделка зависла: клиент не отвечает уже четыре недели. Повод вернуться с ценой.',
                  'acts' => array(
                      array('type' => 'call', 'subject' => 'Уточнение размеров', 'content' => 'Клиент пришлёт образец шестерни почтой', 'days' => 38, 'status' => 'completed'),
                      array('type' => 'email', 'subject' => 'КП на 80 шестерён', 'content' => 'Отправили предложение, ответа нет', 'days' => 28, 'status' => 'completed'),
                      array('type' => 'task', 'subject' => 'Напомнить клиенту о предложении', 'content' => 'Написать в WhatsApp: предложение действует до конца месяца', 'days' => -1, 'status' => 'pending'),
                  )),
            array('client' => 'CL-4104', 'title' => 'Одна шестерня по образцу', 'amount' => 3500,
                  'path' => array('new', 'won'), 'created_days' => 25, 'last_days' => 20,
                  'priority' => 'low', 'source' => 'Сайт', 'contact' => 0,
                  'description' => 'Единичный заказ, напечатан на SLA и отправлен курьером.',
                  'acts' => array(
                      array('type' => 'call', 'subject' => 'Подтверждение заказа', 'content' => 'Оплата по счёту, срок три рабочих дня', 'days' => 24, 'status' => 'completed'),
                  )),
            array('client' => 'CL-4105', 'title' => 'Органайзеры настенные из TPU, 100 шт.', 'part' => 'PRT-ORG-06', 'lots' => 1,
                  'path' => array('new', 'qualify', 'proposal', 'negotiation', 'won'), 'created_days' => 60, 'last_days' => 40,
                  'priority' => 'medium', 'source' => 'Рекомендация', 'contact' => 0,
                  'description' => 'Отсрочка платежа 14 дней согласована директором.',
                  'acts' => array(
                      array('type' => 'meeting', 'subject' => 'Показ образца', 'content' => 'Директору понравилась гибкость, попросил цвет серый', 'days' => 55, 'status' => 'completed'),
                      array('type' => 'call', 'subject' => 'Условия оплаты', 'content' => 'Согласовали отсрочку 14 дней', 'days' => 44, 'status' => 'completed'),
                  )),
            array('client' => 'CL-4105', 'title' => 'Усиленные кронштейны, срочная партия', 'part' => 'PRT-BRKT-08', 'lots' => 1,
                  'path' => array('new', 'qualify', 'proposal', 'lost'), 'loss' => 'timing', 'created_days' => 35, 'last_days' => 28,
                  'priority' => 'critical', 'source' => 'Повторный заказ', 'contact' => 1,
                  'description' => 'Клиенту нужно за 5 дней, а печать одной партии занимает принтер почти неделю.',
                  'acts' => array(
                      array('type' => 'call', 'subject' => 'Срочный запрос', 'content' => 'Нужно за пять дней — не успеваем по загрузке принтеров', 'days' => 33, 'status' => 'completed'),
                  )),
            array('client' => 'CL-4106', 'title' => 'Таблички с гравировкой для выставки', 'part' => 'PRT-PLATE-07', 'lots' => 1,
                  'path' => array('new', 'qualify', 'proposal'), 'created_days' => 9, 'last_days' => 2,
                  'deadline_days' => -12, 'priority' => 'critical', 'source' => 'Соцсети', 'contact' => 0,
                  'description' => 'Выставка через две недели, макет надписей согласован.',
                  'acts' => array(
                      array('type' => 'email', 'subject' => 'Макет надписей', 'content' => 'Клиент прислал макет, поправили шрифт', 'days' => 7, 'status' => 'completed'),
                      array('type' => 'task', 'subject' => 'Отправить фото готовой таблички', 'content' => 'Показать пробный оттиск до запуска партии', 'days' => -2, 'status' => 'pending'),
                  )),
            array('client' => 'CL-4106', 'title' => 'Объёмные логотипы, пробная серия', 'amount' => 18000,
                  'path' => array('new'), 'created_days' => 1, 'last_days' => 1,
                  'priority' => 'low', 'source' => 'Соцсети', 'contact' => 0,
                  'description' => 'Новый запрос из соцсетей: пока уточняем размеры.',
                  'acts' => array()),
        ),

        'knowledge' => array(
            array('category' => 'Сроки', 'question' => 'Сколько занимает печать партии?',
                  'answer' => 'Партия клипс 1200 шт. печатается около 18 часов на одном принтере, корпуса датчиков — 12 часов, усиленные кронштейны — 22 часа. К сроку нужно прибавить постобработку и контроль ОТК: обычно 1–2 рабочих дня.',
                  'tags' => array('сроки', 'печать')),
            array('category' => 'Материалы', 'question' => 'Какой материал выбрать для детали?',
                  'answer' => 'PLA — недорогие детали без нагрузки; PETG — прочность и пищевой контакт; ABS — нагрев и ударные нагрузки; TPU — гибкие детали; фотополимер — высокая точность зуба и мелкие элементы.',
                  'tags' => array('материалы', 'консультация')),
            array('category' => 'Качество', 'question' => 'Какие допуски вы держите?',
                  'answer' => 'FDM — от ±0,1 до ±0,5 мм в зависимости от размера и материала, SLA — до ±0,08 мм. На партию выдаём паспорт качества с протоколом измерений.',
                  'tags' => array('допуски', 'контроль')),
            array('category' => 'Оплата', 'question' => 'Какие условия оплаты?',
                  'answer' => 'Для новых клиентов — предоплата 50%, для постоянных возможна отсрочка до 14 дней по решению директора.',
                  'tags' => array('оплата', 'договор')),
        ),
    ),

    // -------------------------------------------------------------------------
    //  Составы изделий (erp231_product_structure)
    //
    //  Для каждой детали каталога строится EBOM (материал + расходник) и
    //  согласуется, затем копируется в MBOM, привязанную к техкарте.
    //  `scrap` — норматив потерь на брак и поддержки, %.
    // -------------------------------------------------------------------------

    'structures' => array(
        'consumable'    => 'CON-NOZ-04',
        'consumable_qty'=> 0.0002,
        'scrap'         => array(
            'PRT-CLIP-01' => 3.0, 'PRT-PLUG-02' => 4.0, 'PRT-COOKIE-03' => 3.5, 'PRT-CASE-04' => 3.0,
            'PRT-GEAR-05' => 5.0, 'PRT-ORG-06' => 5.0, 'PRT-PLATE-07' => 3.0, 'PRT-BRKT-08' => 4.5,
        ),
        // Детали, у которых есть вторая ревизия в работе: код => статус (draft|review)
        'revisions'     => array('PRT-CASE-04' => 'review', 'PRT-ORG-06' => 'draft'),
        // Комплект из нескольких деталей: многоуровневый состав
        'kit' => array(
            'code'  => 'KIT-SHELF-01',
            'name'  => 'Набор мастерской: органайзер с кронштейнами',
            'lines' => array(
                array('part' => 'PRT-ORG-06',  'qty' => 1),
                array('part' => 'PRT-BRKT-08', 'qty' => 2),
            ),
        ),
    ),

    // -------------------------------------------------------------------------
    //  QR-метки (erp199_sqr): свободные объекты, наклейки, инвентаризации
    //
    //  Склад как вид цели в системе не предусмотрен, поэтому места хранения
    //  заведены свободными объектами типа `location`.
    // -------------------------------------------------------------------------

    'qr' => array(
        // Свободные объекты: type — тип erp199 (tool, container, document, location)
        'objects' => array(
            array('key' => 'rack-a1', 'type' => 'location', 'name' => 'Стеллаж A1: катушки PLA и PETG',
                  'description' => 'Основное место хранения филамента первого сорта', 'attributes' => array('zone' => 'A', 'shelf' => '1')),
            array('key' => 'rack-b2', 'type' => 'location', 'name' => 'Стеллаж B2: готовая продукция',
                  'description' => 'Упакованные партии, ожидающие отгрузки', 'attributes' => array('zone' => 'B', 'shelf' => '2')),
            array('key' => 'quarantine', 'type' => 'location', 'name' => 'Зона карантина ОТК',
                  'description' => 'Забракованные и спорные партии до решения комиссии', 'attributes' => array('zone' => 'Q')),
            array('key' => 'caliper', 'type' => 'tool', 'name' => 'Штангенциркуль цифровой 150 мм',
                  'brand' => 'Mitutoyo', 'model' => '500-196-30', 'serial' => 'MIT-0457',
                  'description' => 'Основной инструмент ОТК, поверка раз в год',
                  'attributes' => array('kind' => 'штангенциркуль', 'size' => '150 мм', 'condition' => 'исправен')),
            array('key' => 'micrometer', 'type' => 'tool', 'name' => 'Микрометр 0–25 мм',
                  'brand' => 'Mitutoyo', 'model' => '103-137', 'serial' => 'MIC-2231',
                  'description' => 'Для точных измерений корпусов датчиков',
                  'attributes' => array('kind' => 'микрометр', 'size' => '0–25 мм', 'condition' => 'исправен')),
            array('key' => 'dry-box', 'type' => 'container', 'name' => 'Бокс для катушек №3 с силикагелем',
                  'description' => 'Сухое хранение TPU и PETG',
                  'attributes' => array('kind' => 'бокс', 'size' => '40×30×30 см', 'condition' => 'исправен')),
            array('key' => 'qc-folder', 'type' => 'document', 'name' => 'Папка паспортов качества ОТК',
                  'description' => 'Бумажные паспорта качества, электронные копии лежат в системе',
                  'attributes' => array('kind' => 'паспорта качества', 'number' => 'ПК-2026', 'date' => '2026-01-01')),
            array('key' => 'dryer', 'type' => 'tool', 'name' => 'Сушилка филамента (нашли в подсобке)',
                  'brand' => 'Sunlu', 'model' => 'S2', 'description' => 'Заведена с терминала, ждёт подтверждения модератором',
                  'attributes' => array('kind' => 'сушилка', 'condition' => 'не проверена'), 'source' => 'scan', 'final' => 'draft'),
            array('key' => 'dup-box', 'type' => 'container', 'name' => 'Бокс для катушек №3 (дубль)',
                  'description' => 'Заведён повторно с терминала', 'attributes' => array('kind' => 'бокс'),
                  'source' => 'scan', 'final' => 'rejected', 'comment' => 'Дубль: такой бокс уже заведён'),
        ),

        // Наклейки на номенклатуру: материалы и детали
        'items' => array('MAT-PLA-BLK', 'MAT-PETG-GRY', 'MAT-ABS-BLK', 'MAT-TPU-FLX', 'PRT-CLIP-01', 'PRT-CASE-04'),
        // Сколько заказов и сотрудников получат наклейки
        'orders'    => 4,
        'employees' => array('T-0003', 'T-0005', 'T-0006', 'T-0010'),
        // Пустые наклейки в запасе: напечатаны, но ни к чему не привязаны
        'spare'     => 6,
    ),

    // -------------------------------------------------------------------------
    //  Тур по системе: что и в каком порядке смотреть
    // -------------------------------------------------------------------------

    'tour' => array(
        array('step' => 1, 'title' => 'Рабочее место: что делать прямо сейчас', 'url' => '/demo/workspace',
              'what' => 'Один экран вместо обхода шести разделов: показатели дня, что требует решения, что печатается в цехе, что с заказами и чего не хватает на складе. Собран из компонентов интерфейса — тех же, что переводим в модулях, — и берёт цифры из витрин, а не считает сам.',
              'ask' => 'Проверьте: понятно ли за 10 секунд, за что взяться первым, и сходятся ли эти цифры с витринами.'),
        array('step' => 2, 'title' => 'Что заказывают', 'url' => '/production/orders',
              'what' => 'Производственные заказы: от черновика до выпуска. Видно, что клиенты разные — от автоклипс до формы для печенья — и у каждого заказа свой состав из техкарты.',
              'ask' => 'Спросите себя: понятно ли за 10 секунд, что сейчас в работе и что просрочено?'),
        array('step' => 3, 'title' => 'Как считают потребность', 'url' => '/planning',
              'what' => 'Программа выпуска на месяц и расчёт потребности по материалам: сколько катушек филамента нужно и чего не хватает.',
              'ask' => 'Проверьте: цифры в потребности совпадают с составом заказов?'),
        array('step' => 4, 'title' => 'Как покупают материал', 'url' => '/procurement/orders',
              'what' => 'Заявки, заказы поставщикам, приёмка с входным контролем. Видно, какая катушка пришла, кто её принял и с каким качеством.',
              'ask' => 'Найдите заказ, который ещё в пути, и его согласованную сумму.'),
        array('step' => 5, 'title' => 'Что делает цех', 'url' => '/shopfloor',
              'what' => 'Операции по принтерам: печать, постобработка, контроль. Отдельно — простои с причиной (засор сопла, замена филамента) и брак по сменам.',
              'ask' => 'Посмотрите, у какого принтера больше простоев и почему.'),
        array('step' => 6, 'title' => 'Как проверяют качество', 'url' => '/quality',
              'what' => 'Контроль партий, дефекты с причинами, карантин и решения по браку. Парето причин: что чаще всего портит печать.',
              'ask' => 'Сравните долю брака с целью и скажите, укладывается ли производство.'),
        array('step' => 7, 'title' => 'Где лежит материал и детали', 'url' => '/warehouse/balances',
              'what' => 'Остатки филамента и готовых деталей, резервы под заказы, движения. Отдельно — карантин: брак не смешивается с годным.',
              'ask' => 'Найдите позицию, которой хватит меньше чем на неделю.'),
        array('step' => 8, 'title' => 'Что видит директор', 'url' => '/analytics',
              'what' => 'Витрины аналитики: покрытие запасов, ABC/XYZ, OEE печатного парка, Парето простоев, доля брака, просрочка поставок. Цифры считаются по реальным данным стенда.',
              'ask' => 'Откройте снимок витрины и сравните «было/стало» с предыдущим.'),
        array('step' => 9, 'title' => 'Кто работает на производстве', 'url' => '/workforce',
              'what' => 'Оргструктура «Печатни 3D»: участки, должности, штатное расписание с вакансиями, карточки сотрудников с историей назначений. Видно, кто отвечает за печать, отделку и ОТК, кто в отпуске и кто уволен.',
              'ask' => 'Откройте штатное расписание и найдите участок, где вакансий больше всего.'),
        array('step' => 10, 'title' => 'С кем работаем и что продаём', 'url' => '/crm',
              'what' => 'CRM поверх клиентов: воронка сделок по стадиям, контакты с ролью в принятии решений, лента звонков и встреч, напоминания и причины отказов. Суммы сделок сходятся с ценами деталей.',
              'ask' => 'Найдите сделку, которая зависла, и посмотрите, какое следующее действие по ней запланировано.'),
        array('step' => 11, 'title' => 'Из чего состоит изделие', 'url' => '/product-structures',
              'what' => 'Составы изделий: инженерная EBOM и производственная MBOM, ревизии с согласованием и связь с техкартой. У каждой детали указан материал с нормой потерь.',
              'ask' => 'Сравните состав детали в EBOM и MBOM и найдите ревизию, которая ещё на согласовании.'),
        array('step' => 12, 'title' => 'Метки на всём, что нужно найти', 'url' => '/qr',
              'what' => 'QR-наклейки на материалах, деталях, заказах, сотрудниках и инструменте, журнал сканирований и инвентаризации — закрытая и текущая. Склад как цель не предусмотрен, места хранения заведены свободными объектами.',
              'ask' => 'Откройте текущую инвентаризацию и посмотрите, какие метки ещё не отсканированы.'),
        array('step' => 13, 'title' => 'Что можно машине', 'url' => '/mcp',
              'what' => 'Агентский слой: 135+ инструментов MCP у девяти модулей. Опасные операции уходят под подтверждение человека и пишутся в журнал вызовов.',
              'ask' => 'Посмотрите журнал: кто, что и с каким результатом вызывал.'),
    ),

    // -------------------------------------------------------------------------
    //  Тексты для лендинга /demo
    // -------------------------------------------------------------------------

    'landing' => array(
        'title'     => 'Демонстрационный стенд PromERP',
        'lead'      => 'Готовое производство «Печатня 3D»: четыре FDM-принтера, фотополимер, участок постобработки и ОТК. '
                     . 'Заказы, техпроцесс, закупка филамента, контроль размеров и аналитика — на настоящих данных, '
                     . 'которые система ведёт с нуля.',
        'promise'   => array(
            'Изменения выключены: стенд показывает систему, а не принимает правки. Любая попытка записи вернёт вежливый отказ.',
            'Демо-база отдельная: боевые данные недостижимы по построению — гостевой запрос физически ходит в другую базу.',
            'Данные вымышленные и регулярно пересобираются: можно ломать, смотреть и возвращаться — стенд восстановят.',
            'Можно подключить ИИ-агента к витринам: ключ демо-стенда ограничен чтением.',
        ),
        'minutes'   => '5 минут на осмотр',
        'guest_hint'=> 'Вход без регистрации — одной кнопкой ниже.',
    ),
);
