<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php if ( ! empty($errors)): ?>
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:bg-red-900/30 dark:border-red-700 dark:text-red-200">
        <p class="font-medium mb-1">Проверьте заполнение полей:</p>
        <ul class="list-disc list-inside space-y-0.5">
            <?php foreach ($errors as $field => $message): ?>
                <li><?php echo HTML::chars(is_array($message) ? reset($message) : $message); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
