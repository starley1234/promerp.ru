<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Восстановление пароля.
 *
 * Оформление — как у остальных страниц: шапка `page_header`, панель `card`,
 * поле — компонентом `form_field`. Прежняя версия версталась сама, задавала
 * свои отступы поверх отступов `<main>` и не давала странице заголовка
 * первого уровня.
 *
 * Переменные: `errors` — список сообщений, `success` — письмо отправлено.
 */
$errors  = isset($errors) ? (array) $errors : array();
$success = ! empty($success);

$button_primary = 'inline-flex min-h-10 w-full items-center justify-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500';
?>

<div class="mx-auto max-w-md space-y-6">

    <?php echo Component::render('page_header', array(
        'title'  => 'Восстановление пароля',
        'lead'   => 'Укажите адрес, на который создавали учётную запись: отправим ссылку для нового пароля.',
        'crumbs' => array(array('Главная', '/'), array('Вход', '/auth/login'), array('Восстановление пароля', NULL)),
    )); ?>

    <?php if ($success): ?>

        <?php Component::begin('card', array('title' => 'Письмо отправлено', 'icon' => 'fas fa-envelope-circle-check', 'state' => 'ok', 'id' => 'restore-sent')); ?>
            <p class="text-sm text-gray-700 dark:text-gray-200">
                Инструкции по сбросу пароля отправлены на указанный адрес. Если письма нет —
                проверьте папку «Спам».
            </p>
            <a href="/auth/login" class="mt-4 inline-flex min-h-10 items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-arrow-left text-xs" aria-hidden="true"></i> Вернуться ко входу
            </a>
        <?php Component::end(); ?>

    <?php else: ?>

        <?php if ($errors): ?>
            <?php Component::begin('card', array('title' => 'Проверьте адрес', 'icon' => 'fas fa-triangle-exclamation', 'state' => 'bad', 'id' => 'restore-errors')); ?>
                <ul class="space-y-1 text-sm text-gray-700 dark:text-gray-200">
                    <?php foreach ($errors as $error): ?>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-circle-exclamation mt-0.5 text-rose-600" aria-hidden="true"></i>
                            <span><?php echo HTML::chars(is_array($error) ? (string) reset($error) : (string) $error); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php Component::end(); ?>
        <?php endif; ?>

        <?php Component::begin('card', array('title' => 'Ваш адрес', 'icon' => 'fas fa-key', 'id' => 'restore-form')); ?>
            <form method="post" action="/auth/forgot_password" class="space-y-4">

                <?php echo Component::render('form_field', array(
                    'id'           => 'email',
                    'name'         => 'email',
                    'label'        => 'Email',
                    'type'         => 'email',
                    'autocomplete' => 'email',
                    'required'     => TRUE,
                )); ?>

                <div class="space-y-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                    <button type="submit" class="<?php echo $button_primary; ?>">
                        <i class="fas fa-paper-plane text-xs" aria-hidden="true"></i> Отправить ссылку
                    </button>

                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Вспомнили пароль? <a href="/auth/login" class="text-blue-600 hover:underline dark:text-blue-400">Вернуться ко входу</a>
                    </p>
                </div>
            </form>
        <?php Component::end(); ?>

    <?php endif; ?>

</div>
