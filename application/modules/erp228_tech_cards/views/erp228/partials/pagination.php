<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php if (isset($pages) AND $pages > 1): ?>
    <?php
    $query = Arr::get($_GET, 'term', NULL);
    $link  = function ($page_number) use ($query, $filters) {
        $params = array('page' => $page_number);

        foreach (array('term', 'status', 'production_type') as $key)
        {
            if ( ! empty($filters[$key]))
            {
                $params[$key] = $filters[$key];
            }
        }

        return '/tech_cards/list?'.http_build_query($params);
    };
    ?>
    <div class="flex items-center justify-between border-t border-gray-200 dark:border-gray-700 px-4 py-3">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Страница <?php echo (int) $page; ?> из <?php echo (int) $pages; ?>
            (всего <?php echo (int) $total; ?>)
        </p>
        <div class="flex gap-2">
            <?php if ($page > 1): ?>
                <a href="<?php echo HTML::chars($link($page - 1)); ?>"
                   class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">Назад</a>
            <?php endif; ?>
            <?php if ($page < $pages): ?>
                <a href="<?php echo HTML::chars($link($page + 1)); ?>"
                   class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">Вперёд</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
