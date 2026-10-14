<?php defined('SYSPATH') or die('No direct script access.'); ?>
<?php
/**
 * Постраничная навигация.
 *
 * Ожидает: $total, $page, $page_size, $base_url (ссылка с уже подготовленными
 * фильтрами без параметра page).
 */
$pages = (int) ceil($total / max(1, $page_size));

if ($pages > 1):
?>
    <div class="flex items-center justify-between px-5 py-4 border-t border-gray-200 dark:border-gray-700 text-sm">
        <div class="text-gray-500 dark:text-gray-400">
            Показано <?php echo (($page - 1) * $page_size) + 1; ?>—<?php echo min($page * $page_size, $total); ?> из <?php echo (int) $total; ?>
        </div>
        <div class="flex items-center space-x-1">
            <?php
            $link = function ($p) use ($base_url) {
                return $base_url.($p > 1 ? 'page='.$p : '');
            };
            ?>
            <?php if ($page > 1): ?>
                <a href="<?php echo HTML::chars($link($page - 1)); ?>"
                   class="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">←</a>
            <?php endif; ?>
            <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
                <a href="<?php echo HTML::chars($link($p)); ?>"
                   class="px-3 py-1 rounded border <?php echo $p === $page
                       ? 'border-blue-600 bg-blue-600 text-white'
                       : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700'; ?>">
                    <?php echo $p; ?>
                </a>
            <?php endfor; ?>
            <?php if ($page < $pages): ?>
                <a href="<?php echo HTML::chars($link($page + 1)); ?>"
                   class="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">→</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
