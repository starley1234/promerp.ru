<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Обратная связь.
 *
 * Тот же вид, что у остальных страниц: шапка `page_header`, панель `card`,
 * поля — компонентом `form_field` (label, подсказка, ошибка рядом и связь
 * через ARIA). Прежняя версия была свёрстана самой (Bootstrap-классы,
 * `text-3xl`, свои отступы) и выпадала из системы.
 *
 * Переменные:
 *   errors           — array(поле => сообщение или список сообщений)
 *   values           — что ввёл человек: при отказе форма не опустошается
 *   success_message  — ответ об отправке
 *   referer          — откуда пришли (уходит скрытым полем)
 */
$errors  = isset($errors) ? (array) $errors : array();
$values  = isset($values) ? (array) $values : array();
$referer = isset($referer) ? (string) $referer : '';
$success = isset($success_message) ? (string) $success_message : '';

$value = function ($field) use ($values) {
    return isset($values[$field]) ? (string) $values[$field] : '';
};

// Ошибка поля: Validation отдаёт и строку, и список сообщений.
$error = function ($field) use ($errors) {
    if ( ! isset($errors[$field]))
    {
        return NULL;
    }

    return is_array($errors[$field]) ? (string) reset($errors[$field]) : (string) $errors[$field];
};

$button_primary   = 'inline-flex min-h-10 items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500';
$button_secondary = 'inline-flex min-h-10 items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500';

$guest = ! (isset($current_user_roles) && in_array('login', (array) $current_user_roles, TRUE));
?>

<div class="mx-auto max-w-3xl space-y-6">

    <?php echo Component::render('page_header', array(
        'title'  => 'Связаться с нами',
        'lead'   => 'Вопрос по системе, предложение или отзыв — напишите, мы ответим по электронной почте. '
            .'Если вы уже работаете в системе, письмо придёт с вашим адресом: представляться не нужно.',
        'crumbs' => array(array('Главная', '/'), array('Обратная связь', NULL)),
    )); ?>

    <?php if ($success): ?>

        <?php Component::begin('card', array('title' => 'Сообщение отправлено', 'icon' => 'fas fa-circle-check', 'state' => 'ok', 'id' => 'contact-sent')); ?>
            <p class="text-sm text-gray-700 dark:text-gray-200"><?php echo HTML::chars($success); ?></p>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <a href="/" class="<?php echo $button_primary; ?>">
                    <i class="fas fa-house text-xs" aria-hidden="true"></i> На главную
                </a>
                <a href="/docs" class="<?php echo $button_secondary; ?>">Документация</a>
            </div>
        <?php Component::end(); ?>

    <?php else: ?>

        <?php
        $general = array();

        foreach ($errors as $field => $message)
        {
            if ($field === 'database')
            {
                $general[] = is_array($message) ? (string) reset($message) : (string) $message;
            }
        }
        ?>

        <?php if ($general): ?>
            <?php Component::begin('card', array('title' => 'Сообщение не отправлено', 'icon' => 'fas fa-triangle-exclamation', 'state' => 'bad', 'id' => 'contact-error')); ?>
                <ul class="space-y-1 text-sm text-gray-700 dark:text-gray-200">
                    <?php foreach ($general as $message): ?>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-circle-exclamation mt-0.5 text-rose-600" aria-hidden="true"></i>
                            <span><?php echo HTML::chars($message); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php Component::end(); ?>
        <?php endif; ?>

        <?php Component::begin('card', array(
            'title'    => 'Сообщение',
            'icon'     => 'fas fa-envelope',
            'subtitle' => 'Отмеченные поля обязательны: без обратного адреса мы не сможем ответить.',
            'id'       => 'contact-form',
        )); ?>
            <form method="post" action="<?php echo HTML::chars(URL::site('contact/send')); ?>" class="space-y-4">

                <?php if ($guest): ?>
                    <?php echo Component::render('form_field', array(
                        'id'           => 'email',
                        'name'         => 'email',
                        'label'        => 'Ваш email',
                        'type'         => 'email',
                        'value'        => $value('email'),
                        'required'     => TRUE,
                        'autocomplete' => 'email',
                        'hint'         => 'Сюда придёт ответ',
                        'error'        => $error('email'),
                    )); ?>
                <?php else: ?>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        <i class="fas fa-circle-check text-emerald-600" aria-hidden="true"></i>
                        Ответ придёт на адрес вашей учётной записи.
                    </p>
                <?php endif; ?>

                <?php echo Component::render('form_field', array(
                    'id'       => 'message',
                    'name'     => 'message',
                    'label'    => 'Сообщение',
                    'type'     => 'textarea',
                    'value'    => $value('message'),
                    'required' => TRUE,
                    'hint'     => 'Не меньше 10 символов: «не работает» без подробностей нам не поможет',
                    'error'    => $error('message'),
                )); ?>

                <?php if ($referer !== ''): ?>
                    <input type="hidden" name="referer" value="<?php echo HTML::chars($referer); ?>">
                <?php endif; ?>

                <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                    <button type="submit" class="<?php echo $button_primary; ?>">
                        <i class="fas fa-paper-plane text-xs" aria-hidden="true"></i> Отправить
                    </button>
                    <a href="/" class="<?php echo $button_secondary; ?>">Отмена</a>
                </div>
            </form>
        <?php Component::end(); ?>

    <?php endif; ?>

</div>
