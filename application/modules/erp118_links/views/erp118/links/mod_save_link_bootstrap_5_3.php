<?php if (isset($current_user) AND ($current_user)): ?>
<!-- Кнопка "Сохранить ссылку" (закладка) -->
<li class="nav-item list-none">
	<span class="nav-link link bookmark-widget cursor-pointer" id="toggle-save-link-btn" title="Сохранить/Удалить текущую страницу">
		<i class="bi bi-bookmark" id="bookmark-icon"></i>
	</span>
</li>

<script>
	$(document).ready(function() {
		const currentUrl = window.location.href;
		const currentTitle = document.title;
		const currentDescription = $('meta[name="description"]').attr('content') || '';
		const $bookmarkIcon = $('#bookmark-icon');
		const $toggleBtn = $('#toggle-save-link-btn');

		// Функция для уведомлений (используем глобальную функцию из шаблона, если она есть)
		function notify(message, type) {
			if (typeof window.showNotification === 'function') {
				window.showNotification(message, type);
			}
		}

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
							$bookmarkIcon.removeClass('bi-bookmark').addClass('bi-bookmark-fill text-primary');
							$toggleBtn.attr('title', 'Удалить из закладок');
						} else {
							$bookmarkIcon.removeClass('bi-bookmark-fill text-primary').addClass('bi-bookmark');
							$toggleBtn.attr('title', 'Сохранить в закладки');
						}
					} else {
						// Если пользователь не авторизован, скрываем или деактивируем виджет
						$toggleBtn.addClass('opacity-50').attr('title', 'Войдите, чтобы сохранять закладки');
					}
				},
				error: function() {
					$toggleBtn.hide();
				}
			});
		}

		// Проверяем статус при загрузке
		checkBookmarkStatus();

		// Обработчик клика
		$toggleBtn.on('click', function() {
			if ($(this).hasClass('opacity-50')) {
				notify('Пожалуйста, войдите в систему', 'info');
				return;
			}

			// Визуальный отклик до завершения запроса
			$bookmarkIcon.addClass('opacity-50');

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
					$bookmarkIcon.removeClass('opacity-50');
					if (response.status === 'success') {
						if (response.action === 'saved') {
							$bookmarkIcon.removeClass('bi-bookmark').addClass('bi-bookmark-fill text-primary');
							$toggleBtn.attr('title', 'Удалить из закладок');
							// notify('Страница сохранена в закладки', 'success');
						} else {
							$bookmarkIcon.removeClass('bi-bookmark-fill text-primary').addClass('bi-bookmark');
							$toggleBtn.attr('title', 'Сохранить в закладки');
							// notify('Страница удалена из закладок', 'info');
						}
					} else {
						notify(response.message || 'Ошибка при выполнении операции', 'error');
					}
				},
				error: function() {
					$bookmarkIcon.removeClass('opacity-50');
					notify('Ошибка сетевого соединения', 'error');
				}
			});
		});
	});
</script>
<?php endif; ?>