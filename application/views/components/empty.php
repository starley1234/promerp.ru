<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Пустое состояние.
 *
 * Компонент: components/empty
 * Переменные:
 *   title       — что произошло (обязательно): «По заданным условиям позиций нет»
 *   hint        — почему так и что сделать, чтобы данные появились
 *   action_html — кнопка или ссылка, которая закрывает пустоту
 *   icon        — класс иконки
 *
 * Зачем: «пусто» — это тоже ответ системы, и его нужно объяснять, а не
 * оставлять пользователя с пустым прямоугольником.
 */
$title       = isset($title) ? $title : 'Данных нет';
$hint        = isset($hint) ? $hint : NULL;
$action      = isset($action_html) ? $action_html : NULL;
$icon        = isset($icon) ? $icon : 'fas fa-inbox';
?>
<div class="px-5 py-10 text-center">
    <i class="<?php echo HTML::chars($icon); ?> text-3xl text-gray-300 dark:text-gray-600" aria-hidden="true"></i>
    <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-200"><?php echo HTML::chars($title); ?></p>
    <?php if ($hint): ?>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-lg mx-auto"><?php echo HTML::chars($hint); ?></p>
    <?php endif; ?>
    <?php if ($action): ?>
        <div class="mt-4"><?php echo $action; ?></div>
    <?php endif; ?>
</div>
