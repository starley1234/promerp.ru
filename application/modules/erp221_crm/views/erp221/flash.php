<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Сообщения после действия: показать один раз и убрать.
 *
 * Переменные: messages — список ['type' => success|error|warning|info, 'message' => …].
 * Тип и текст приходят от сервера; представление их только раскладывает.
 */
foreach ((array) $messages as $message)
{
    echo Component::render('toast', array(
        'type'    => $message['type'],
        'message' => $message['message'],
    ));
}
