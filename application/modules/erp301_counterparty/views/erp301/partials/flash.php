<?php defined('SYSPATH') or die('No direct script access.'); ?>
<?php if ( ! empty($messages)): ?>
    <div class="mb-4 space-y-2">
        <?php foreach ($messages as $message): ?>
            <?php
            $type    = isset($message['type']) ? $message['type'] : 'info';
            $classes = array(
                'success' => 'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/30 dark:border-green-700 dark:text-green-200',
                'error'   => 'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/30 dark:border-red-700 dark:text-red-200',
                'warning' => 'bg-yellow-50 border-yellow-200 text-yellow-800 dark:bg-yellow-900/30 dark:border-yellow-700 dark:text-yellow-200',
                'info'    => 'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-900/30 dark:border-blue-700 dark:text-blue-200',
            );
            $class = isset($classes[$type]) ? $classes[$type] : $classes['info'];
            ?>
            <div class="border rounded-md px-4 py-3 text-sm <?php echo $class; ?>">
                <?php echo HTML::chars(isset($message['message']) ? $message['message'] : ''); ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
