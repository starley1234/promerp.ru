<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Сообщения после действия: показать один раз и убрать.
 *
 * Переменные: messages — список ['type' => success|error|warning|info, 'message' => …].
 * Раскладку делает общий компонент toast — один вид сообщений на всю систему
 * (контроллеры модуля кладут их через _flash()/_flash_messages()).
 */
foreach ((array) $messages as $message)
{
    echo Component::render('toast', array(
        'type'    => $message['type'],
        'message' => $message['message'],
    ));
}
