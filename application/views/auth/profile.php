<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Профиль пользователя.
 *
 * Тот же каркас, что у остальных страниц: шапка `page_header`, панели `card`,
 * плоские плитки одного вида, синий акцент. Прежняя версия была единственной
 * страницей с градиентной «обложкой», `rounded-2xl`, `text-3xl` и палитрами
 * `indigo`/`purple` — она выпадала из системы, хотя именно её человек видит
 * чаще всего после входа.
 *
 * Переменная: `user` — модель пользователя.
 */
$initial = HTML::chars(mb_strtoupper(mb_substr((string) $user->username, 0, 1, 'UTF-8'), 'UTF-8'));

$button_secondary = 'inline-flex min-h-10 items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500';

// Дата регистрации: поле может быть пустым у старых записей.
$registered = ! empty($user->created_at)
    ? date('d.m.Y', (int) $user->created_at)
    : '—';
?>

<div class="mx-auto max-w-3xl space-y-6">

    <?php echo Component::render('page_header', array(
        'title'        => 'Профиль',
        'lead'         => 'Учётная запись, состояние почты и вид интерфейса.',
        'crumbs'       => array(array('Главная', '/'), array('Профиль', NULL)),
        'actions_html' => '<a href="/auth/logout" class="'.$button_secondary.'">'
            .'<i class="fas fa-right-from-bracket text-xs" aria-hidden="true"></i> Выйти</a>',
    )); ?>

    <?php Component::begin('card', array('title' => 'Учётная запись', 'icon' => 'fas fa-user-circle', 'id' => 'profile-account')); ?>
        <div class="flex items-start gap-4">
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-xl font-bold text-white">
                <?php echo $initial; ?>
            </span>
            <div class="min-w-0">
                <p class="font-semibold text-gray-900 dark:text-white"><?php echo HTML::chars($user->username); ?></p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300"><?php echo HTML::chars($user->email); ?></p>
                <p class="mt-2">
                    <?php if ($user->email_confirmed): ?>
                        <?php echo Component::render('badge', array('label' => 'Почта подтверждена', 'state' => 'ok')); ?>
                    <?php else: ?>
                        <?php echo Component::render('badge', array('label' => 'Почта ожидает подтверждения', 'state' => 'warn')); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-2 border-t border-gray-200 pt-4 text-sm dark:border-gray-700 sm:grid-cols-2">
            <div class="flex flex-wrap gap-x-2">
                <dt class="text-gray-500 dark:text-gray-400">Дата регистрации</dt>
                <dd class="font-medium text-gray-900 dark:text-white"><?php echo HTML::chars($registered); ?></dd>
            </div>
            <div class="flex flex-wrap gap-x-2">
                <dt class="text-gray-500 dark:text-gray-400">Последний вход</dt>
                <dd class="font-medium text-gray-900 dark:text-white">
                    <?php echo HTML::chars($user->last_login ? date('d.m.Y H:i', (int) $user->last_login) : 'Первый вход'); ?>
                </dd>
            </div>
        </dl>
    <?php Component::end(); ?>

    <?php Component::begin('card', array('title' => 'Безопасность', 'icon' => 'fas fa-shield-halved', 'id' => 'profile-security')); ?>
        <p class="text-sm text-gray-600 dark:text-gray-300">
            Пароль хранится в виде хеша и не восстанавливается: система пришлёт ссылку для нового
            пароля на вашу почту.
        </p>
        <a href="/auth/forgot_password" class="<?php echo $button_secondary; ?> mt-4">
            <i class="fas fa-key text-xs" aria-hidden="true"></i> Сменить пароль
        </a>
    <?php Component::end(); ?>

    <?php Component::begin('card', array('title' => 'Режим интерфейса', 'icon' => 'fas fa-sliders', 'id' => 'profile-mode')); ?>
        <p class="text-sm text-gray-600 dark:text-gray-300">
            Простой — крупные плитки и только главное. Профессиональный — таблицы, все поля и все
            разделы. Выбор запоминается для вашей учётной записи.
        </p>
        <div class="mt-4 max-w-md">
            <?php echo Component::render('mode_switch'); ?>
        </div>
    <?php Component::end(); ?>

</div>
