<nav class="flex items-center justify-center space-x-1 my-8" aria-label="Pagination">
    <?php if ($first_page !== FALSE): ?>
        <a href="<?php echo HTML::chars($page->url($first_page)) ?>" class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" title="<?php echo __('First') ?>">
            <i class="fas fa-angles-left"></i>
        </a>
    <?php endif ?>

    <?php if ($previous_page !== FALSE): ?>
        <a href="<?php echo HTML::chars($page->url($previous_page)) ?>" class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" rel="prev">
            <i class="fas fa-chevron-left"></i>
        </a>
    <?php else: ?>
        <span class="px-3 py-2 rounded-md text-sm font-medium text-gray-400 dark:text-gray-600 cursor-not-allowed">
            <i class="fas fa-chevron-left"></i>
        </span>
    <?php endif ?>

    <div class="hidden sm:flex space-x-1">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php if ($i == $current_page): ?>
                <span class="px-4 py-2 rounded-md text-sm font-bold bg-blue-600 text-white shadow-sm">
                    <?php echo $i ?>
                </span>
            <?php else: ?>
                <?php if ($i <= 3 || $i > $total_pages - 3 || ($i >= $current_page - 1 && $i <= $current_page + 1)): ?>
                    <a href="<?php echo HTML::chars($page->url($i)) ?>" class="px-4 py-2 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        <?php echo $i ?>
                    </a>
                <?php elseif ($i == 4 || $i == $total_pages - 3): ?>
                    <span class="px-4 py-2 text-gray-400">...</span>
                <?php endif ?>
            <?php endif ?>
        <?php endfor ?>
    </div>

    <div class="sm:hidden px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300">
        <?php echo $current_page ?> / <?php echo $total_pages ?>
    </div>

    <?php if ($next_page !== FALSE): ?>
        <a href="<?php echo HTML::chars($page->url($next_page)) ?>" class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" rel="next">
            <i class="fas fa-chevron-right"></i>
        </a>
    <?php else: ?>
        <span class="px-3 py-2 rounded-md text-sm font-medium text-gray-400 dark:text-gray-600 cursor-not-allowed">
            <i class="fas fa-chevron-right"></i>
        </span>
    <?php endif ?>

    <?php if ($last_page !== FALSE): ?>
        <a href="<?php echo HTML::chars($page->url($last_page)) ?>" class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" title="<?php echo __('Last') ?>">
            <i class="fas fa-angles-right"></i>
        </a>
    <?php endif ?>
</nav>