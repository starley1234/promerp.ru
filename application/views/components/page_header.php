<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Шапка страницы — заголовок, подпись и главные действия.
 *
 * Компонент: components/page_header
 * Переменные:
 *   title        — заголовок h1 (обязательно, экранируется)
 *   lead         — пояснение под заголовком: о чём страница (экранируется)
 *   eyebrow      — короткая надпись над заголовком (экранируется)
 *   crumbs       — «хлебные крошки»: список array(подпись, адрес); у последнего
 *                  адрес NULL — это текущая страница
 *   meta         — мелкая строка под подписью: дата, срок, источник (экранируется)
 *   actions_html — кнопки справа (готовый HTML)
 *   level        — уровень заголовка: 1 для страницы (по умолчанию), 2 и ниже —
 *                  когда на одной странице показывают несколько шапок-примеров:
 *                  на странице остаётся один h1, примеры не ломают структуру
 *
 * Зачем: у публичных страниц (главная, о сервисе, документация, демо, условия)
 * было по своему заголовку — от градиентного баннера до карточки с тенью. Одна
 * шапка с теми же размерами, что у рабочих страниц, даёт гостю ощущение одной
 * системы, а не набора лендингов.
 */
$title   = isset($title) ? $title : '';
$lead    = isset($lead) ? $lead : NULL;
$eyebrow = isset($eyebrow) ? $eyebrow : NULL;
$crumbs  = isset($crumbs) ? (array) $crumbs : array();
$meta    = isset($meta) ? $meta : NULL;
$actions = isset($actions_html) ? $actions_html : NULL;
$level   = isset($level) ? max(1, min(6, (int) $level)) : 1;
$tag     = 'h'.$level;
$size    = $level > 1 ? 'text-xl' : 'text-2xl';
?>
<header class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
    <div>
        <?php if ($crumbs): ?>
            <nav aria-label="Навигация по разделу" class="flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400 mb-1">
                <?php foreach ($crumbs as $i => $crumb): ?>
                    <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
                    <?php if ( ! empty($crumb[1])): ?>
                        <a href="<?php echo HTML::chars($crumb[1]); ?>" class="hover:text-blue-600 dark:hover:text-blue-400"><?php echo HTML::chars($crumb[0]); ?></a>
                    <?php else: ?>
                        <span class="font-medium text-gray-800 dark:text-gray-200" aria-current="page"><?php echo HTML::chars($crumb[0]); ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <?php if ($eyebrow): ?>
            <p class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400"><?php echo HTML::chars($eyebrow); ?></p>
        <?php endif; ?>

        <<?php echo $tag; ?> class="<?php echo $size; ?> font-bold text-gray-900 dark:text-white mt-1"><?php echo HTML::chars($title); ?></<?php echo $tag; ?>>

        <?php if ($lead): ?>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-3xl"><?php echo HTML::chars($lead); ?></p>
        <?php endif; ?>

        <?php if ($meta): ?>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1"><?php echo HTML::chars($meta); ?></p>
        <?php endif; ?>
    </div>

    <?php if ($actions): ?>
        <div class="flex flex-wrap items-center gap-2 shrink-0"><?php echo $actions; ?></div>
    <?php endif; ?>
</header>
