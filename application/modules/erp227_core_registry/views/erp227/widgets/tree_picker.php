<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="tree-picker-container bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm" 
     id="tree-picker-<?php echo $id; ?>" 
     data-input-name="<?php echo HTML::chars($input_name); ?>">
    
    <!-- Header / Search -->
    <div class="p-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
        <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                <i class="fas fa-search text-sm"></i>
            </span>
            <input type="text" 
                   class="tree-search-input block w-full pl-9 pr-3 py-2 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" 
                   placeholder="Поиск по коду или названию...">
        </div>
    </div>

    <!-- Tree Content -->
    <div class="tree-viewport overflow-y-auto max-h-[400px] p-2 custom-scrollbar">
        <?php if (!empty($items)): ?>
            <ul class="tree-root space-y-1">
                <?php foreach ($items as $item): ?>
                    <?php echo View::factory('erp227/widgets/tree_picker_item')
                        ->set('item', $item)
                        ->set('level', 0); ?>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                <i class="fas fa-folder-open block text-3xl mb-2 opacity-20"></i>
                <p class="text-sm">Объекты не найдены</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer / Selected Info -->
    <div class="p-3 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
        <div class="selected-label text-xs font-medium text-gray-500 dark:text-gray-400 truncate mr-4">
            <?php if (isset($selected_text)): ?>
                Выбрано: <span class="text-blue-600 dark:text-blue-400"><?php echo HTML::chars($selected_text); ?></span>
            <?php else: ?>
                Выберите объект из списка
            <?php endif; ?>
        </div>
        <input type="hidden" name="<?php echo HTML::chars($input_name); ?>" value="<?php echo HTML::chars($selected_id ?? ''); ?>" class="tree-hidden-input">
        
        <button type="button" class="tree-reset-btn text-xs text-gray-400 hover:text-red-500 transition-colors <?php echo empty($selected_id) ? 'hidden' : ''; ?>">
            <i class="fas fa-times-circle mr-1"></i> Сбросить
        </button>
    </div>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; }
    
    .tree-node-toggle.is-empty { visibility: hidden; }
    .tree-node-toggle.is-open i { transform: rotate(90deg); }
    .tree-node-toggle i { transition: transform 0.2s ease; }
    
    .tree-item-row.is-selected {
        background-color: rgba(59, 130, 246, 0.1);
        border-color: rgba(59, 130, 246, 0.3);
    }
</style>

<script>
$(document).ready(function() {
    const $container = $('#tree-picker-<?php echo $id; ?>');
    
    // Toggle nodes
    $container.on('click', '.tree-node-toggle', function(e) {
        e.stopPropagation();
        const $btn = $(this);
        const $childList = $btn.closest('li').find('> ul');
        
        $btn.toggleClass('is-open');
        $childList.toggleClass('hidden');
    });

    // Select item
    $container.on('click', '.tree-item-row', function() {
        const $row = $(this);
        const id = $row.data('id');
        const label = $row.data('label');
        
        $container.find('.tree-item-row').removeClass('is-selected bg-blue-50 dark:bg-blue-900/20');
        $row.addClass('is-selected bg-blue-50 dark:bg-blue-900/20');
        
        $container.find('.tree-hidden-input').val(id);
        $container.find('.selected-label').html('Выбрано: <span class="text-blue-600 dark:text-blue-400">' + label + '</span>');
        $container.find('.tree-reset-btn').removeClass('hidden');
    });

    // Reset
    $container.find('.tree-reset-btn').on('click', function() {
        $container.find('.tree-item-row').removeClass('is-selected bg-blue-50 dark:bg-blue-900/20');
        $container.find('.tree-hidden-input').val('');
        $container.find('.selected-label').text('Выберите объект из списка');
        $(this).addClass('hidden');
    });

    // Simple Search Filter
    $container.find('.tree-search-input').on('input', function() {
        const val = $(this).val().toLowerCase();
        if (val.length < 2) {
            $container.find('li').show();
            return;
        }
        
        $container.find('.tree-item-row').each(function() {
            const text = $(this).text().toLowerCase();
            const $li = $(this).closest('li');
            if (text.indexOf(val) > -1) {
                $li.show().parents('li').show();
            } else {
                $li.hide();
            }
        });
    });
});
</script>