<?php defined('SYSPATH') or die('No direct script access.');
$role_labels = array(
    'admin' => 'Администратор', 'manager' => 'Руководитель', 'director' => 'Директор',
    'production' => 'Производство', 'master' => 'Мастер', 'operator' => 'Оператор',
    'warehouse' => 'Склад', 'storekeeper' => 'Кладовщик', 'quality' => 'Качество',
    'sales' => 'Продажи', 'crm' => 'CRM', 'procurement' => 'Закупки', 'buyer' => 'Закупки',
    'accountant' => 'Бухгалтерия', 'analyst' => 'Аналитик', 'login' => 'Пользователь',
);
$labels = array();
foreach ((array) $roles as $role) if (isset($role_labels[$role]) && !in_array($role_labels[$role], $labels, TRUE)) $labels[] = $role_labels[$role];
$first_name = isset($user->username) ? $user->username : 'коллега';
?>

<?php
$button_secondary = 'inline-flex min-h-10 items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500';
?>
<div class="space-y-6">
    <?php
    /*
     * Шапка рабочего места — та же, что на остальных страницах.
     *
     * Раньше здесь был градиентный баннер во всю ширину: он выпадал из
     * системы (gradient, rounded-2xl, палитра slate/indigo), а по нему же
     * человек судит обо всём сервисе — это первое, что он видит после входа.
     * Приветствие осталось, но собрано общим компонентом.
     */
    echo Component::render('page_header', array(
        'eyebrow'      => 'Ваше рабочее место',
        'title'        => 'Добрый день, '.$first_name.'!',
        'lead'         => 'Здесь собраны доступные вам разделы. Выберите модуль, чтобы продолжить работу.',
        'actions_html' => '<a href="/demo/workspace" class="'.$button_secondary.'"><i class="fas fa-compass text-xs" aria-hidden="true"></i>Рабочее место цеха</a>',
    ));
    ?>

    <?php if ($labels): ?>
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">Ваши роли:</span>
            <?php foreach ($labels as $label): ?>
                <?php echo Component::render('badge', array('label' => $label, 'state' => 'info')); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Разделы системы</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Показаны только модули, доступные по вашей роли.</p>
    </div>

    <?php if ($modules): ?>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <?php foreach ($modules as $module): ?>
        <a href="<?php echo HTML::chars($module['href']); ?>" class="group flex min-h-[160px] flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-blue-500">
            <div class="flex items-start justify-between"><span class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400"><i class="<?php echo HTML::chars($module['icon']); ?> text-lg" aria-hidden="true"></i></span><i class="fas fa-arrow-up-right-from-square text-gray-300 transition group-hover:text-blue-500" aria-hidden="true"></i></div>
            <h3 class="mt-4 font-semibold text-gray-900 dark:text-white"><?php echo HTML::chars($module['title']); ?></h3>
            <p class="mt-1 text-sm leading-5 text-gray-500 dark:text-gray-400"><?php echo HTML::chars($module['description']); ?></p>
        </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <?php echo Component::render('empty', array('title' => 'Модули пока не назначены', 'hint' => 'Обратитесь к администратору, чтобы получить доступ к рабочим разделам.')); ?>
    <?php endif; ?>

    <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800 sm:p-6">
        <div class="flex items-start gap-4"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400"><i class="fas fa-lightbulb" aria-hidden="true"></i></span><div><h2 class="font-semibold text-gray-900 dark:text-white">Не знаете, с чего начать?</h2><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Откройте демонстрационное рабочее место: там показатели дня, внимание, цех, заказы, склад и качество на одном экране.</p><a href="/demo/workspace" class="mt-3 inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400">Открыть обзор <i class="fas fa-arrow-right ml-2" aria-hidden="true"></i></a></div></div>
    </section>
</div>
