<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Страница «Доступ запрещён» (403).
 *
 * Тот же каркас, что у остальных страниц: шапка `page_header`, панель `card`,
 * общие кнопки. Прежняя версия версталась сама: `text-3xl`, круглые иконки и
 * свои отступы поверх отступов `<main>`.
 *
 * Переменная: `message` — причина отказа (готовая строка из контроллера).
 */
$message    = isset($message) ? (string) $message : '';
$logged_in  = Auth::instance()->logged_in();

$button_primary   = 'inline-flex min-h-10 items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500';
$button_secondary = 'inline-flex min-h-10 items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500';
?>

<div class="mx-auto max-w-3xl space-y-6">

    <?php echo Component::render('page_header', array(
        'title'  => 'Доступ ограничен',
        'lead'   => $logged_in
            ? 'У вашей учётной записи нет прав на этот раздел. Разделы выдаются по роли: '
                .'кладовщику — склад, мастеру — цех, снабженцу — закупки.'
            : 'Этот раздел открыт только авторизованным пользователям: в нём рабочие данные предприятия.',
        'crumbs' => array(array('Главная', '/'), array('Доступ ограничен', NULL)),
    )); ?>

    <?php if ($message): ?>
        <?php Component::begin('card', array('title' => 'Причина', 'icon' => 'fas fa-circle-info', 'state' => 'warn', 'id' => 'error-reason')); ?>
            <p class="text-sm text-gray-700 dark:text-gray-200"><?php echo HTML::chars($message); ?></p>
        <?php Component::end(); ?>
    <?php endif; ?>

    <?php Component::begin('card', array('title' => 'Что с этим делать', 'icon' => 'fas fa-shield-halved', 'id' => 'error-actions')); ?>
        <ul class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
            <li class="flex items-start gap-2">
                <i class="fas fa-right-to-bracket mt-0.5 text-blue-600 dark:text-blue-400" aria-hidden="true"></i>
                <span>Войдите под своей учётной записью — права приходят вместе с ролью.</span>
            </li>
            <li class="flex items-start gap-2">
                <i class="fas fa-flask mt-0.5 text-blue-600 dark:text-blue-400" aria-hidden="true"></i>
                <span>Хотите просто посмотреть систему — откройте демонстрационный стенд, вход туда свободный.</span>
            </li>
            <li class="flex items-start gap-2">
                <i class="fas fa-user-shield mt-0.5 text-blue-600 dark:text-blue-400" aria-hidden="true"></i>
                <span>Если права должны быть, а их нет — обратитесь к администратору вашей установки.</span>
            </li>
        </ul>

        <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-gray-200 pt-4 dark:border-gray-700">
            <?php if ( ! $logged_in): ?>
                <a href="<?php echo HTML::chars(URL::site('auth/login')); ?>" class="<?php echo $button_primary; ?>">
                    <i class="fas fa-right-to-bracket text-xs" aria-hidden="true"></i> Войти в систему
                </a>
            <?php endif; ?>
            <a href="<?php echo HTML::chars(URL::base()); ?>" class="<?php echo $button_secondary; ?>">
                <i class="fas fa-house text-xs" aria-hidden="true"></i> На главную
            </a>
            <a href="/demo" class="<?php echo $button_secondary; ?>">
                <i class="fas fa-play text-xs" aria-hidden="true"></i> Демонстрация
            </a>
            <a href="/contact" class="<?php echo $button_secondary; ?>">
                <i class="fas fa-envelope text-xs" aria-hidden="true"></i> Поддержка
            </a>
        </div>
    <?php Component::end(); ?>

    <p class="text-xs text-gray-500 dark:text-gray-400">Код ответа: 403 Forbidden</p>

</div>
