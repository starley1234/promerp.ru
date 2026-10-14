<?php if (isset($current_user) AND ($current_user)): ?>
<!-- Кнопка "Сохранить ссылку" (закладка) в стиле Tailwind CSS -->
<button id="toggle-save-link-btn" class="p-2 rounded-full text-gray-500 hover:text-blue-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-blue-400 dark:hover:bg-gray-700 transition-colors focus:outline-none" title="Сохранить/Удалить текущую страницу">
    <i class="fa-regular fa-bookmark text-xl" id="bookmark-icon"></i>
</button>

<script>
    $(document).ready(function() {
        var currentUrl = window.location.href;
        var currentTitle = document.title;
        var currentDescription = $('meta[name="description"]').attr('content');
        var bookmarkIcon = $('#bookmark-icon');
        var toggleSaveLinkBtn = $('#toggle-save-link-btn');

        // Функция для проверки статуса закладки
        function checkBookmarkStatus() {
            $.ajax({
                url: '<?php echo URL::site('erp118_links/check_link_status'); ?>',
                method: 'GET',
                data: { url: currentUrl },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        if (response.is_saved) {
                            bookmarkIcon.removeClass('fa-regular').addClass('fa-solid text-blue-600 dark:text-blue-400');
                            toggleSaveLinkBtn.attr('title', 'Удалить текущую страницу из закладок');
                        } else {
                            bookmarkIcon.removeClass('fa-solid text-blue-600 dark:text-blue-400').addClass('fa-regular');
                            toggleSaveLinkBtn.attr('title', 'Сохранить текущую страницу в закладки');
                        }
                    } else {
                        console.error('Error checking link status:', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error checking link status:', status, error);
                }
            });
        }

        // Проверяем статус закладки при загрузке страницы
        checkBookmarkStatus();

        // Обработчик кнопки
        toggleSaveLinkBtn.on('click', function() {
            $.ajax({
                url: '<?php echo URL::site('erp118_links/toggle_save'); ?>',
                method: 'POST',
                data: {
                    url: currentUrl,
                    title: currentTitle,
                    description: currentDescription
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        if (response.action === 'saved') {
                            bookmarkIcon.removeClass('fa-regular').addClass('fa-solid text-blue-600 dark:text-blue-400');
                            toggleSaveLinkBtn.attr('title', 'Удалить текущую страницу из закладок');
                        } else if (response.action === 'deleted') {
                            bookmarkIcon.removeClass('fa-solid text-blue-600 dark:text-blue-400').addClass('fa-regular');
                            toggleSaveLinkBtn.attr('title', 'Сохранить текущую страницу в закладки');
                        }
                    } else {
                        console.error('Error toggling link:', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error toggling link:', status, error);
                }
            });
        });
    });
</script>
<?php endif; ?>