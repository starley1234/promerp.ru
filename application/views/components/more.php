<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * «Ещё» — второстепенное, свёрнутое в простом режиме (Ui_Mode).
 *
 * Компонент: components/more
 * Переменные:
 *   summary — подпись раскрывашки (по умолчанию «Дополнительные настройки»).
 *             Не `title`: у каждого представления есть глобальная переменная
 *             `title` — заголовок страницы, и она подставилась бы сама
 *   icon  — класс иконки перед подписью
 *   open  — TRUE: в простом режиме раскрыта сразу (например, фильтр уже применён)
 *   plain — TRUE: раскрытое содержимое без своей рамки (когда внутри уже карточка)
 *   body  — содержимое (готовый HTML или Component::begin/end)
 *
 * В профессиональном режиме обёртки нет: содержимое идёт как есть. Так форма
 * в простом режиме коротка, а в профессиональном остаётся полной — без `if` в
 * представлении.
 */
$summary = isset($summary) && $summary !== '' ? $summary : 'Дополнительные настройки';
$icon  = isset($icon) ? $icon : 'fas fa-sliders';
$open  = ! empty($open);
$plain = ! empty($plain);
$body  = isset($body) ? $body : '';
?>
<?php if (Ui_Mode::pro()): ?>
    <?php echo $body; ?>
<?php else: ?>
    <details class="mb-4 <?php echo $plain ? '' : 'rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800'; ?>"<?php echo $open ? ' open' : ''; ?>>
        <summary class="flex cursor-pointer select-none items-center gap-2 px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-200">
            <i class="<?php echo HTML::chars($icon); ?> text-blue-600 dark:text-blue-400" aria-hidden="true"></i>
            <?php echo HTML::chars($summary); ?>
        </summary>
        <div class="<?php echo $plain ? 'pt-2' : 'px-4 pb-4'; ?>"><?php echo $body; ?></div>
    </details>
<?php endif; ?>
