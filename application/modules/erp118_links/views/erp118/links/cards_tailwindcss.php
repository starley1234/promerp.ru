<div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php if (count($user_links) > 0): ?>
            <?php foreach ($user_links as $link): ?>
                <div class="group relative bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-all duration-200 flex flex-col h-full">
                    <button type="button" 
                            class="absolute top-2 right-2 p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors opacity-0 group-hover:opacity-100 delete-link-btn" 
                            aria-label="Удалить" 
                            data-link-id="<?php echo $link->id; ?>">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                    
                    <div class="p-5 flex flex-col h-full">
                        <div class="flex items-start mb-3">
                            <div class="flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center mr-3" data-ui-color="<?php echo HTML::chars($link->color); ?>" data-color-mode="soft-background">
                                <i class="<?php echo HTML::chars($link->icon_class); ?> text-xl" data-ui-color="<?php echo HTML::chars($link->color); ?>" data-color-mode="color"></i>
                            </div>
                            <h5 class="text-lg font-bold leading-tight pt-1">
                                <a href="<?php echo HTML::chars($link->url); ?>" class="text-gray-900 dark:text-white hover:underline decoration-2 underline-offset-4" data-ui-color="<?php echo HTML::chars($link->color); ?>" data-color-mode="color">
                                    <?php echo HTML::chars($link->title); ?>
                                </a>
                            </h5>
                        </div>

                        <?php if (!empty($link->description)): ?>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4 flex-grow line-clamp-3">
                                <?php echo HTML::chars($link->description); ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($link->group_name)): ?>
                            <div class="mt-auto pt-3 flex justify-end">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-600">
                                    <i class="fas fa-folder mr-1.5 opacity-50"></i>
                                    <?php echo HTML::chars($link->group_name); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="mt-8 flex justify-center">
        <a href="/erp118_links/manage" class="text-xs text-gray-400 dark:text-gray-500 hover:text-blue-500 dark:hover:text-blue-400 transition-colors flex items-center gap-1.5 opacity-60 hover:opacity-100">
            <i class="fas fa-cog"></i>
            Управление ссылками
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.delete-link-btn').forEach(button => {
        button.addEventListener('click', function() {
            const linkId = this.dataset.linkId;
            const cardContainer = this.closest('.group');
            
            if (confirm('Вы уверены, что хотите удалить эту ссылку?')) {
                fetch('/erp118_links/delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `id=${linkId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        cardContainer.style.opacity = '0';
                        cardContainer.style.transform = 'scale(0.95)';
                        setTimeout(() => cardContainer.remove(), 200);
                        if (window.showNotification) {
                            showNotification('Ссылка успешно удалена', 'success');
                        }
                    } else {
                        if (window.showNotification) {
                            showNotification('Ошибка: ' + data.message, 'error');
                        } else {
                            alert('Ошибка при удалении ссылки: ' + data.message);
                        }
                    }
                })
                .catch(error => {
                    console.error('Ошибка:', error);
                    if (window.showNotification) {
                        showNotification('Произошла ошибка при удалении', 'error');
                    }
                });
            }
        });
    });
});
</script>