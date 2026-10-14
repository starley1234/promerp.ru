<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Сообщения об ошибках формы обратной связи.
 *
 * Без этого файла `Validation::errors('contact')` отдавал служебные английские
 * строки («message must be at least 10 characters long») — на русской странице
 * это выглядит как поломка. Формат ключей: `поле.правило`, значения правил
 * доступны как `:param1`, `:param2` (см. `Validation::errors()`).
 *
 * @package    Messages
 * @category   Contact
 */
return array(
    'email' => array(
        'not_empty'  => 'Укажите email: без него мы не сможем ответить',
        'email'      => 'В адресе опечатка — проверьте, пожалуйста',
        'max_length' => 'Слишком длинный адрес: не больше :param2 символов',
        'default'    => 'Проверьте адрес электронной почты',
    ),
    'message' => array(
        'not_empty'  => 'Напишите, в чём вопрос',
        'min_length' => 'Слишком коротко: нужно хотя бы :param2 символов, чтобы мы поняли суть',
        'max_length' => 'Слишком длинно: не больше :param2 символов',
        'default'    => 'Проверьте текст сообщения',
    ),
);
