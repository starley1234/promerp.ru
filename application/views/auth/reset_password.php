<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Новый пароль по ссылке из письма.
 *
 * Оформление — как у остальных страниц: шапка `page_header`, панель `card`,
 * поля — компонентом `form_field`. Прежняя версия версталась сама и задавала
 * свои отступы поверх отступов `<main>`.
 *
 * Переменная: `errors` — список сообщений.
 */
$errors = isset($errors) ? (array) $errors : array();

$button_primary = 'inline-flex min-h-10 w-full items-center justify-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500';
?>

<div class="mx-auto max-w-md space-y-6">

    <?php echo Component::render('page_header', array(
        'title'  => 'Новый пароль',
        'lead'   => 'Придумайте пароль, которого нет ни в одной другой системе: к учётной записи привязаны данные предприятия.',
        'crumbs' => array(array('Главная', '/'), array('Вход', '/auth/login'), array('Новый пароль', NULL)),
    )); ?>

    <?php if ($errors): ?>
        <?php Component::begin('card', array('title' => 'Проверьте пароль', 'icon' => 'fas fa-triangle-exclamation', 'state' => 'bad', 'id' => 'reset-errors')); ?>
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

    <?php Component::begin('card', array('title' => 'Придумайте пароль', 'icon' => 'fas fa-shield-halved', 'id' => 'reset-form')); ?>
        <form method="post" action="" class="space-y-4">

            <?php echo Component::render('form_field', array(
                'id'           => 'password',
                'name'         => 'password',
                'label'        => 'Новый пароль',
                'type'         => 'password',
                'autocomplete' => 'new-password',
                'required'     => TRUE,
                'hint'         => 'Не меньше 8 символов',
            )); ?>

            <?php echo Component::render('form_field', array(
                'id'           => 'password_confirm',
                'name'         => 'password_confirm',
                'label'        => 'Пароль ещё раз',
                'type'         => 'password',
                'autocomplete' => 'new-password',
                'required'     => TRUE,
            )); ?>

            <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
                <button type="submit" class="<?php echo $button_primary; ?>">
                    <i class="fas fa-check text-xs" aria-hidden="true"></i> Обновить пароль
                </button>
            </div>
        </form>
    <?php Component::end(); ?>

</div>
