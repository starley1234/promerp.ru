<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php if ( ! empty($messages)): ?>
    <div class="mb-4 space-y-2">
        <?php foreach ($messages as $message): ?>
            <?php
            $type  = isset($message['type']) ? $message['type'] : 'info';
            $tones = array(
                'success' => 'border-green-200 bg-green-50 text-green-800 dark:bg-green-900/30 dark:border-green-700 dark:text-green-200',
                'error'   => 'border-red-200 bg-red-50 text-red-800 dark:bg-red-900/30 dark:border-red-700 dark:text-red-200',
                'warning' => 'border-amber-200 bg-amber-50 text-amber-800 dark:bg-amber-900/30 dark:border-amber-700 dark:text-amber-200',
                'info'    => 'border-blue-200 bg-blue-50 text-blue-800 dark:bg-blue-900/30 dark:border-blue-700 dark:text-blue-200',
            );
            $icons = array(
                'success' => 'fas fa-check-circle',
                'error'   => 'fas fa-exclamation-circle',
                'warning' => 'fas fa-exclamation-triangle',
                'info'    => 'fas fa-info-circle',
            );
            ?>
            <div class="flex items-start gap-3 rounded-lg border px-4 py-3 text-sm <?php echo $tones[$type]; ?>">
                <i class="<?php echo $icons[$type]; ?> mt-0.5"></i>
                <span><?php echo HTML::chars($message['message']); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
