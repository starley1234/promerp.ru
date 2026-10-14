<?php defined('SYSPATH') or die('No direct script access.'); ?>
<?php if ( ! empty($messages)): ?>
    <div class="mb-4 space-y-2" role="status" aria-live="polite">
        <?php foreach ($messages as $message): ?>
            <?php
            $type = isset($message['type']) ? $message['type'] : 'info';
            $states = array('success' => 'ok', 'error' => 'bad', 'warning' => 'warn', 'info' => 'info');
            $state = isset($states[$type]) ? $states[$type] : 'info';
            ?>
            <div class="border rounded-md px-4 py-3 text-sm <?php echo Component::state_class($state, 'bg').' '.Component::state_class($state, 'border').' '.Component::state_class($state, 'text'); ?>">
                <?php echo HTML::chars($message['message']); ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
