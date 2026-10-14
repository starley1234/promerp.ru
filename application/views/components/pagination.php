<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Постраничный переход.
 *
 * Компонент: components/pagination
 * Переменные:
 *   page      — текущая страница (с 1)
 *   pages     — всего страниц
 *   url       — callable($page): адрес страницы (сохраняет текущие фильтры)
 *   shown     — сколько ссылок показывать вокруг текущей (по умолчанию 2)
 *   total     — сколько всего записей (попадёт в подпись для скринридера)
 *
 * Зачем: длинный список должен листаться, а не расти до бесконечности —
 * страница остатков с тысячей строк перестаёт открываться.
 */
$page  = isset($page) ? max(1, (int) $page) : 1;
$pages = isset($pages) ? (int) $pages : 0;
$url   = isset($url) && is_callable($url) ? $url : NULL;
$shown = isset($shown) ? max(1, (int) $shown) : 2;
$total = isset($total) ? (int) $total : NULL;

if ($pages < 2 OR ! $url)
{
    return;
}

$from = max(1, $page - $shown);
$to   = min($pages, $page + $shown);

$link = function ($p, $label, $current = FALSE, $disabled = FALSE) use ($url) {
    $base = 'inline-flex min-h-10 items-center justify-center px-3 rounded border text-sm ';

    if ($current)
    {
        return '<span class="'.$base.'bg-blue-600 text-white border-blue-600" aria-current="page">'.$label.'</span>';
    }

    if ($disabled)
    {
        return '<span class="'.$base.'text-gray-400 border-gray-200 dark:border-gray-700">'.$label.'</span>';
    }

    return '<a href="'.HTML::chars(call_user_func($url, $p)).'" class="'.$base.'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">'.$label.'</a>';
};
?>
<?php if ($pages > 1): ?>
    <nav class="mt-4 flex flex-wrap items-center justify-center gap-1" aria-label="Постраничный переход">
        <?php echo $link(max(1, $page - 1), '<i class="fas fa-chevron-left" aria-hidden="true"></i><span class="sr-only">Предыдущая страница</span>', FALSE, $page <= 1); ?>

        <?php if ($from > 1): ?>
            <?php echo $link(1, '1'); ?>
            <?php if ($from > 2): ?><span class="px-2 text-gray-400" aria-hidden="true">…</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($p = $from; $p <= $to; $p++): ?>
            <?php echo $link($p, (string) $p, $p === $page); ?>
        <?php endfor; ?>

        <?php if ($to < $pages): ?>
            <?php if ($to < $pages - 1): ?><span class="px-2 text-gray-400" aria-hidden="true">…</span><?php endif; ?>
            <?php echo $link($pages, (string) $pages); ?>
        <?php endif; ?>

        <?php echo $link(min($pages, $page + 1), '<i class="fas fa-chevron-right" aria-hidden="true"></i><span class="sr-only">Следующая страница</span>', FALSE, $page >= $pages); ?>
    </nav>
<?php endif; ?>
