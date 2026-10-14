<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Вход в систему.
 *
 * Тот же каркас, что у остальных страниц: шапка `page_header` (она даёт
 * единственный h1), панель `card`, поля — компонентом `form_field`, кнопка —
 * общей. Прежняя версия версталась сама: свои отступы поверх отступов
 * `<main>`, отрицательный отступ `-mt-24` и заголовок второго уровня вместо
 * первого — из-за этого страница выпадала из системы и из структуры
 * документа.
 *
 * Переменная: `errors` — список сообщений (уже готовые строки).
 */
$errors = isset($errors) ? (array) $errors : array();

$button_primary = 'inline-flex min-h-10 w-full items-center justify-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500';
?>

<div class="mx-auto max-w-md space-y-6">

    <?php echo Component::render('page_header', array(
        'title' => 'Вход в систему',
        'lead'  => 'Войдите, чтобы попасть в своё рабочее место. Нет учётной записи — можно '
            .'сначала посмотреть систему на демонстрационном стенде.',
        'crumbs' => array(array('Главная', '/'), array('Вход', NULL)),
    )); ?>

    <?php if ($errors): ?>
        <?php Component::begin('card', array('title' => 'Войти не удалось', 'icon' => 'fas fa-triangle-exclamation', 'state' => 'bad', 'id' => 'login-errors')); ?>
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

    <?php Component::begin('card', array('title' => 'Ваши данные', 'icon' => 'fas fa-right-to-bracket', 'id' => 'login-form')); ?>
        <form method="post" action="/auth/login" class="space-y-4">

            <?php echo Component::render('form_field', array(
                'id'           => 'username',
                'name'         => 'username',
                'label'        => 'Имя пользователя или email',
                'autocomplete' => 'username',
                'required'     => TRUE,
            )); ?>

            <?php echo Component::render('form_field', array(
                'id'           => 'password',
                'name'         => 'password',
                'label'        => 'Пароль',
                'type'         => 'password',
                'autocomplete' => 'current-password',
                'required'     => TRUE,
            )); ?>

            <div class="flex items-center gap-2">
                <input id="remember" name="remember" type="checkbox"
                       class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700">
                <label for="remember" class="text-sm text-gray-700 dark:text-gray-200">Запомнить меня</label>
            </div>

            <div class="space-y-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                <button type="submit" class="<?php echo $button_primary; ?>">
                    <i class="fas fa-right-to-bracket text-xs" aria-hidden="true"></i> Войти
                </button>

                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <a href="/auth/register" class="text-blue-600 hover:underline dark:text-blue-400">Создать аккаунт</a>
                    <a href="/auth/forgot_password" class="text-gray-600 hover:underline dark:text-gray-300">Забыли пароль?</a>
                </div>
            </div>
        </form>
    <?php Component::end(); ?>

    <?php Component::begin('card', array('title' => 'Без регистрации', 'icon' => 'fas fa-flask', 'id' => 'login-demo')); ?>
        <p class="text-sm text-gray-600 dark:text-gray-300">
            Демонстрационный стенд открывают одним нажатием: данные вымышленные, запись отключена.
        </p>
        <a href="/demo" class="mt-3 inline-flex min-h-10 items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <i class="fas fa-play text-xs" aria-hidden="true"></i> Посмотреть демонстрацию
        </a>
    <?php Component::end(); ?>

</div>
