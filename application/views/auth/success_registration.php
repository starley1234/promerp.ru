<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Регистрация принята: ждём подтверждения адреса.
 *
 * Оформление — как у остальных страниц: шапка `page_header`, панель `card`.
 * Прежняя версия версталась сама и уводила человека на сторонний адрес
 * поддержки из шаблона; теперь адрес берётся из паспорта инстанса
 * (`site_config.admin_mail`) — как на остальных страницах.
 *
 * Переменная: `email` — адрес, на который ушло письмо.
 */
$config = Kohana::$config->load('site_config');
$email  = isset($email) ? (string) $email : '';
$mail   = (string) $config->get('admin_mail');

$button_primary = 'inline-flex min-h-10 items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500';
?>

<div class="mx-auto max-w-md space-y-6">

    <?php echo Component::render('page_header', array(
        'title'  => 'Почти готово',
        'lead'   => 'Осталось подтвердить адрес: ссылка уже в письме.',
        'crumbs' => array(array('Главная', '/'), array('Регистрация', NULL)),
    )); ?>

    <?php Component::begin('card', array('title' => 'Проверьте почту', 'icon' => 'fas fa-envelope-circle-check', 'state' => 'ok', 'id' => 'registration-mail')); ?>
        <p class="text-sm text-gray-700 dark:text-gray-200">
            Мы отправили письмо для подтверждения регистрации на адрес
            <span class="font-semibold text-gray-900 dark:text-white"><?php echo HTML::chars($email); ?></span>.
            Перейдите по ссылке в письме — учётная запись станет активной.
        </p>
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            Письма нет? Проверьте папку «Спам» или
            <a href="/contact" class="text-blue-600 hover:underline dark:text-blue-400">напишите нам</a>.
        </p>

        <a href="/auth/login" class="<?php echo $button_primary; ?> mt-4">
            <i class="fas fa-arrow-right text-xs" aria-hidden="true"></i> Перейти к входу
        </a>
    <?php Component::end(); ?>

    <?php if ($mail): ?>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Возникли проблемы? Напишите нам:
            <a href="mailto:<?php echo HTML::chars($mail); ?>" class="text-blue-600 hover:underline dark:text-blue-400"><?php echo HTML::chars($mail); ?></a>
        </p>
    <?php endif; ?>

</div>
