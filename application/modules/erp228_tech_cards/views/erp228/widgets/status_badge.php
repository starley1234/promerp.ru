<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
$color = isset(Model_Erp228_TechCard::$status_colors[$card->status])
    ? Model_Erp228_TechCard::$status_colors[$card->status]
    : 'gray';
?>

<a href="/tech_cards/view/<?php echo (int) $card->id; ?>"
   class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-<?php echo $color; ?>-100 text-<?php echo $color; ?>-800 dark:bg-<?php echo $color; ?>-900/30 dark:text-<?php echo $color; ?>-300 hover:underline"
   title="Технологическая карта #<?php echo (int) $card->id; ?>">
    <i class="fas fa-layer-group"></i>
    <?php echo HTML::chars($card->status_label()); ?>
</a>
