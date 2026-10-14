<div> 
    <div id="alert-messages" class="mb-6"></div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Ваши ссылки</h2>
        </div>
        
        <ul class="divide-y divide-gray-200 dark:divide-gray-700" id="sortable-links">
            <?php if (count($links) > 0): ?>
                <?php foreach ($links as $link): ?>
                    <li class="flex items-center p-4 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors link-item-draggable group"
                        data-id="<?php echo $link->id; ?>"
                        data-group="<?php echo HTML::chars($link->group_name); ?>"
                        data-is-menu-item="<?php echo $link->is_menu_item; ?>">
                        
                        <div class="handle cursor-grab active:cursor-grabbing p-2 mr-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                            <i class="fas fa-grip-vertical"></i>
                        </div>

                        <div class="flex-shrink-0 w-10 h-10 flex items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-700 mr-4">
                            <i class="fa-lg <?php echo HTML::chars($link->icon_class); ?>" data-ui-color="<?php echo HTML::chars($link->color); ?>" data-color-mode="color"></i>
                        </div>

                        <div class="flex-grow min-w-0 mr-4">
                            <div class="flex items-center flex-wrap gap-2">
                                <a href="<?php echo HTML::chars($link->url); ?>" target="_blank" class="text-sm font-medium hover:underline truncate" data-ui-color="<?php echo HTML::chars($link->color); ?>" data-color-mode="color">
                                    <span class="link-title"><?php echo HTML::chars($link->title); ?></span>
                                </a>
                                <?php if (!empty($link->group_name)): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 link-group-display">
                                        <?php echo HTML::chars($link->group_name); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($link->is_menu_item): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300 link-menu-badge">
                                        <i class="fas fa-bars mr-1 text-[10px]"></i> Меню
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($link->description)): ?>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate link-description-display">
                                    <?php echo HTML::chars($link->description); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="flex items-center space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button class="p-2 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 edit-link-btn"
                                    data-id="<?php echo $link->id; ?>"
                                    data-title="<?php echo HTML::chars($link->title); ?>"
                                    data-description="<?php echo HTML::chars($link->description); ?>"
                                    data-color="<?php echo HTML::chars($link->color); ?>"
                                    data-icon-class="<?php echo HTML::chars($link->icon_class); ?>"
                                    data-group-name="<?php echo HTML::chars($link->group_name); ?>"
                                    data-is-menu-item="<?php echo $link->is_menu_item; ?>">
                                <i class="fas fa-pencil-alt"></i>
                            </button>
                            <button class="p-2 text-gray-400 hover:text-red-600 dark:hover:text-red-400 delete-link-btn" data-id="<?php echo $link->id; ?>">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li class="p-8 text-center text-gray-500 dark:text-gray-400 empty-list-message">
                    <i class="fas fa-link-slash block text-3xl mb-3 opacity-20"></i>
                    У вас пока нет сохраненных ссылок.
                </li>
            <?php endif; ?>
        </ul>
    </div>
</div>

<!-- Tailwind Modal -->
<div id="editLinkModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity dark:bg-gray-900 dark:bg-opacity-80" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Редактировать ссылку</h3>
                <button type="button" class="close-modal text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="editLinkForm" class="p-6 space-y-4">
                <input type="hidden" id="edit-link-id">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Заголовок</label>
                    <input type="text" id="edit-link-title" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Описание</label>
                    <textarea id="edit-link-description" rows="2" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Цвет</label>
                        <input type="color" id="edit-link-color" class="h-10 w-full p-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Класс иконки (FA)</label>
                        <input type="text" id="edit-link-icon-class" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Группа</label>
                    <input type="text" id="edit-link-group" list="group-suggestions" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none">
                    <datalist id="group-suggestions">
                        <?php foreach ($groups as $group_name): ?>
                            <option value="<?php echo HTML::chars($group_name); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" id="edit-link-is-menu" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600">
                    <label for="edit-link-is-menu" class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">Отображать в боковом меню</label>
                </div>
            </form>

            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700 flex justify-end space-x-3">
                <button type="button" class="close-modal px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">Отмена</button>
                <button type="button" id="save-edited-link-btn" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition-colors">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
$(document).ready(function() {
    function showAlert(message, type) {
        if (window.showNotification) {
            window.showNotification(message, type === 'danger' ? 'error' : type);
        } else {
            const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
            const alertHtml = `<div class="${bgColor} text-white px-4 py-2 rounded-lg mb-4 shadow-md transition-all">${message}</div>`;
            $('#alert-messages').html(alertHtml);
            setTimeout(() => $('#alert-messages').empty(), 5000);
        }
    }

    function updateGroupHeaders() {
        $('.group-header').remove();
        let currentGroup = null;
        const $list = $('#sortable-links');
        let linksExist = false;

        $list.find('li.link-item-draggable').each(function() {
            linksExist = true;
            const linkGroup = $(this).data('group');
            const displayGroup = (!linkGroup || linkGroup === 'null') ? 'Без группы' : linkGroup;

            if (displayGroup !== currentGroup) {
                currentGroup = displayGroup;
                $(`<li class="group-header bg-gray-50 dark:bg-gray-900/50 px-6 py-2 text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-y border-gray-200 dark:border-gray-700" data-group-name="${linkGroup}">${currentGroup}</li>`).insertBefore($(this));
            }
        });

        if (!linksExist && $list.find('.empty-list-message').length === 0) {
            $list.html('<li class="p-8 text-center text-gray-500 dark:text-gray-400 empty-list-message">У вас пока нет сохраненных ссылок.</li>');
        } else if (linksExist) {
            $list.find('.empty-list-message').remove();
        }
    }

    const sortableEl = document.getElementById('sortable-links');
    if (sortableEl) {
        Sortable.create(sortableEl, {
            animation: 150,
            handle: '.handle',
            filter: '.group-header, .empty-list-message',
            onEnd: function() {
                const order = [];
                $('#sortable-links li.link-item-draggable').each(function(index) {
                    order.push({ id: $(this).data('id'), order: index });
                });

                $.ajax({
                    url: '<?php echo URL::site('erp118_links/reorder'); ?>',
                    method: 'POST',
                    data: { order: order },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            showAlert('Порядок обновлен', 'success');
                            updateGroupHeaders();
                        }
                    }
                });
            }
        });
    }

    const $modal = $('#editLinkModal');
    const closeModal = () => {
        $modal.addClass('hidden');
        $('body').removeClass('overflow-hidden');
    };

    $(document).on('click', '.edit-link-btn', function() {
        const d = $(this).data();
        $('#edit-link-id').val(d.id);
        $('#edit-link-title').val(d.title);
        $('#edit-link-description').val(d.description);
        $('#edit-link-color').val(d.color);
        $('#edit-link-icon-class').val(d.iconClass);
        $('#edit-link-group').val((d.groupName === 'null' || !d.groupName) ? '' : d.groupName);
        $('#edit-link-is-menu').prop('checked', parseInt(d.isMenuItem) === 1);
        
        $modal.removeClass('hidden');
        $('body').addClass('overflow-hidden');
    });

    $('.close-modal, .bg-opacity-75').on('click', closeModal);

    $('#save-edited-link-btn').on('click', function() {
        const data = {
            id: $('#edit-link-id').val(),
            title: $('#edit-link-title').val(),
            description: $('#edit-link-description').val(),
            color: $('#edit-link-color').val(),
            icon_class: $('#edit-link-icon-class').val(),
            group_name: $('#edit-link-group').val(),
            is_menu_item: $('#edit-link-is-menu').is(':checked') ? 1 : 0
        };

        $.ajax({
            url: '<?php echo URL::site('erp118_links/update'); ?>',
            method: 'POST',
            data: data,
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    showAlert('Обновлено', 'success');
                    const $li = $(`li[data-id="${data.id}"]`);
                    $li.find('.link-title').text(data.title);
                    $li.find('a').css('color', data.color);
                    $li.find('.fa-lg').removeClass().addClass(`fa-lg ${data.icon_class}`).css('color', data.color);
                    $li.data('group', data.group_name);
                    $li.data('is-menu-item', data.is_menu_item);
                    
                    // Update description
                    let $desc = $li.find('.link-description-display');
                    if (data.description) {
                        if ($desc.length) $desc.text(data.description);
                        else $li.find('.min-w-0').append(`<p class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate link-description-display">${data.description}</p>`);
                    } else $desc.remove();

                    // Update group badge
                    let $badge = $li.find('.link-group-display');
                    if (data.group_name) {
                        if ($badge.length) $badge.text(data.group_name);
                        else $li.find('.link-title').parent().append(`<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 link-group-display">${data.group_name}</span>`);
                    } else $badge.remove();

                    // Update menu badge
                    let $menuBadge = $li.find('.link-menu-badge');
                    if (data.is_menu_item) {
                        if ($menuBadge.length === 0) {
                            $li.find('.link-title').parent().append(`<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-blue-300 link-menu-badge"><i class="fas fa-bars mr-1 text-[10px]"></i> Меню</span>`);
                        }
                    } else {
                        $menuBadge.remove();
                    }

                    $li.find('.edit-link-btn').data({
                        title: data.title,
                        description: data.description,
                        color: data.color,
                        iconClass: data.icon_class,
                        groupName: data.group_name,
                        isMenuItem: data.is_menu_item
                    });

                    closeModal();
                    updateGroupHeaders();
                }
            }
        });
    });

    $(document).on('click', '.delete-link-btn', function() {
        if (confirm('Удалить эту ссылку?')) {
            const id = $(this).data('id');
            const $li = $(this).closest('li');
            $.ajax({
                url: '<?php echo URL::site('erp118_links/delete'); ?>',
                method: 'POST',
                data: { id: id },
                success: function() {
                    $li.remove();
                    updateGroupHeaders();
                    showAlert('Удалено', 'success');
                }
            });
        }
    });

    updateGroupHeaders();
});
</script>