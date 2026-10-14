<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Отказ в операции входа или регистрации (ссылка недействительна).
 *
 * Оформление — как у остальных страниц: шапка `page_header`, панель `card`
 * с состоянием. Текст причины приходит из контроллера готовой строкой.
 *
 * Переменная: `message` — что именно произошло.
 */
$config  = Kohana::$config->load('site_config');
$mail    = (string) $config->get('admin_mail');
$message = isset($message) ? (string) $message : '';

$button_primary = 'inline-flex min-h-10 items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500';
?>

<div class="mx-auto max-w-md space-y-6">

    <?php echo Component::render('page_header', array(
        'title'  => 'Ссылка не сработала',
        'lead'   => 'Так бывает, когда ссылкой уже воспользовались или она старше суток.',
        'crumbs' => array(array('Главная', '/'), array('Вход', '/auth/login')),
    )); ?>

    <?php Component::begin('card', array('title' => 'Что произошло', 'icon' => 'fas fa-triangle-exclamation', 'state' => 'bad', 'id' => 'auth-error')); ?>
        <p class="text-sm text-gray-700 dark:text-gray-200"><?php echo HTML::chars($message); ?></p>

        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
            Запросите новую ссылку — это занимает минуту.
        </p>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <a href="/auth/forgot_password" class="<?php echo $button_primary; ?>">
                <i class="fas fa-rotate-right text-xs" aria-hidden="true"></i> Запросить ссылку
            </a>
            <a href="/auth/login" class="text-sm text-blue-600 hover:underline dark:text-blue-400">Вернуться ко входу</a>
        </div>
    <?php Component::end(); ?>

    <?php if ($mail): ?>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Если вы считаете, что это ошибка, напишите нам:
            <a href="mailto:<?php echo HTML::chars($mail); ?>" class="text-blue-600 hover:underline dark:text-blue-400"><?php echo HTML::chars($mail); ?></a>
        </p>
    <?php endif; ?>

</div>
