<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Регистрация.
 *
 * Тот же каркас, что у остальных страниц: шапка `page_header`, панель `card`,
 * поля — компонентом `form_field` (label, подсказка и связь ошибки через
 * ARIA). Прежняя версия версталась сама и дублировала атрибут `autocomplete`,
 * задавала свои отступы и не давала странице заголовка первого уровня.
 *
 * Переменные: `errors` — список сообщений, `post` — что уже ввёл человек.
 */
$errors = isset($errors) ? (array) $errors : array();
$post   = isset($post) ? (array) $post : array();

$button_primary = 'inline-flex min-h-10 w-full items-center justify-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500';
?>

<div class="mx-auto max-w-md space-y-6">

    <?php echo Component::render('page_header', array(
        'title'  => 'Создать аккаунт',
        'lead'   => 'Учётная запись нужна, чтобы работать с данными предприятия. '
            .'После регистрации подтвердите адрес — ссылка придёт на почту.',
        'crumbs' => array(array('Главная', '/'), array('Вход', '/auth/login'), array('Регистрация', NULL)),
    )); ?>

    <?php if ($errors): ?>
        <?php Component::begin('card', array('title' => 'Проверьте данные', 'icon' => 'fas fa-triangle-exclamation', 'state' => 'bad', 'id' => 'register-errors')); ?>
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

    <?php Component::begin('card', array('title' => 'Ваши данные', 'icon' => 'fas fa-user-plus', 'id' => 'register-form')); ?>
        <form method="post" action="/auth/register" class="space-y-4">

            <?php echo Component::render('form_field', array(
                'id'           => 'username',
                'name'         => 'username',
                'label'        => 'Имя пользователя',
                'value'        => Arr::get($post, 'username'),
                'autocomplete' => 'username',
                'required'     => TRUE,
                'hint'         => 'Латиница, цифры, точка и подчёркивание: так имя видно в журнале действий',
            )); ?>

            <?php echo Component::render('form_field', array(
                'id'           => 'email',
                'name'         => 'email',
                'label'        => 'Email',
                'type'         => 'email',
                'value'        => Arr::get($post, 'email'),
                'autocomplete' => 'email',
                'required'     => TRUE,
                'hint'         => 'На этот адрес придёт ссылка для подтверждения',
            )); ?>

            <?php echo Component::render('form_field', array(
                'id'           => 'password',
                'name'         => 'password',
                'label'        => 'Пароль',
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

            <div class="flex items-start gap-2">
                <input id="terms" name="terms" type="checkbox" required
                       class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700">
                <label for="terms" class="text-sm text-gray-700 dark:text-gray-200">
                    Я согласен с <a href="/terms" class="text-blue-600 hover:underline dark:text-blue-400">условиями использования</a>
                    и <a href="/privacy" class="text-blue-600 hover:underline dark:text-blue-400">политикой конфиденциальности</a>
                </label>
            </div>

            <div class="space-y-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                <button type="submit" class="<?php echo $button_primary; ?>">
                    <i class="fas fa-user-check text-xs" aria-hidden="true"></i> Зарегистрироваться
                </button>

                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Уже есть профиль? <a href="/auth/login" class="text-blue-600 hover:underline dark:text-blue-400">Войти</a>
                </p>
            </div>
        </form>
    <?php Component::end(); ?>

</div>
