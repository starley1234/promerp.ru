<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Страница «Не найдено» (404).
 *
 * Тот же каркас, что у остальных страниц: шапка `page_header`, панель `card`,
 * общие кнопки. Прежняя версия версталась сама: `text-3xl`, круглые иконки и
 * свои отступы поверх отступов `<main>`.
 *
 * Переменная: `message` — детали (готовая строка из контроллера).
 */
$message = isset($message) ? (string) $message : '';

$button_primary   = 'inline-flex min-h-10 items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500';
$button_secondary = 'inline-flex min-h-10 items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500';
?>

<div class="mx-auto max-w-3xl space-y-6">

    <?php echo Component::render('page_header', array(
        'title'  => 'Страница не найдена',
        'lead'   => 'Такого адреса в системе нет: страницу удалили, или в ссылке опечатка.',
        'crumbs' => array(array('Главная', '/'), array('Не найдено', NULL)),
    )); ?>

    <?php if ($message): ?>
        <?php Component::begin('card', array('title' => 'Детали', 'icon' => 'fas fa-circle-info', 'state' => 'info', 'id' => 'error-details')); ?>
            <p class="text-sm text-gray-700 dark:text-gray-200"><?php echo HTML::chars($message); ?></p>
        <?php Component::end(); ?>
    <?php endif; ?>

    <?php Component::begin('card', array('title' => 'Куда дальше', 'icon' => 'fas fa-compass', 'id' => 'error-actions')); ?>
        <ul class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
            <li class="flex items-start gap-2">
                <i class="fas fa-house mt-0.5 text-blue-600 dark:text-blue-400" aria-hidden="true"></i>
                <span>С главной видно всё, что открыто без входа.</span>
            </li>
            <li class="flex items-start gap-2">
                <i class="fas fa-book-open mt-0.5 text-blue-600 dark:text-blue-400" aria-hidden="true"></i>
                <span>В документации — схема системы, процессы и каталог модулей.</span>
            </li>
            <li class="flex items-start gap-2">
                <i class="fas fa-flask mt-0.5 text-blue-600 dark:text-blue-400" aria-hidden="true"></i>
                <span>Демонстрационный стенд показывает систему в деле, без регистрации.</span>
            </li>
        </ul>

        <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-gray-200 pt-4 dark:border-gray-700">
            <a href="<?php echo HTML::chars(URL::base()); ?>" class="<?php echo $button_primary; ?>">
                <i class="fas fa-house text-xs" aria-hidden="true"></i> На главную
            </a>
            <button type="button" data-action="back" class="<?php echo $button_secondary; ?>">
                <i class="fas fa-arrow-left text-xs" aria-hidden="true"></i> Вернуться назад
            </button>
            <a href="/docs" class="<?php echo $button_secondary; ?>">
                <i class="fas fa-book-open text-xs" aria-hidden="true"></i> Документация
            </a>
            <a href="/contact" class="<?php echo $button_secondary; ?>">
                <i class="fas fa-envelope text-xs" aria-hidden="true"></i> Сообщить об ошибке
            </a>
        </div>
    <?php Component::end(); ?>

    <p class="text-xs text-gray-500 dark:text-gray-400">Код ответа: 404 Not Found</p>

</div>
