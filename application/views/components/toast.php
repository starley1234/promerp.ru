<?php defined('SYSPATH') or die('No direct script access.');
$message = isset($message) ? $message : '';
$type = isset($type) ? $type : 'info';
$icons = array('success' => 'fa-check-circle', 'error' => 'fa-exclamation-circle', 'warning' => 'fa-triangle-exclamation', 'info' => 'fa-info-circle');
$classes = array('success' => 'notification success', 'error' => 'notification error', 'warning' => 'notification warning', 'info' => 'notification info');
?>
<div class="<?php echo HTML::chars(isset($classes[$type]) ? $classes[$type] : $classes['info']); ?>" role="status" aria-live="polite"><i class="fas <?php echo HTML::chars(isset($icons[$type]) ? $icons[$type] : $icons['info']); ?> mr-3" aria-hidden="true"></i><span class="flex-1 text-sm font-medium"><?php echo HTML::chars($message); ?></span></div>
