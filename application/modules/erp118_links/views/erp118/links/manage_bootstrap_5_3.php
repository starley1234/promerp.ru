<div class="container mt-4">

    <h1>Управление сохраненными ссылками</h1>

    <div id="alert-messages" class="mt-3"></div>

    <div class="card">
        <div class="card-header">
            Ваши ссылки
        </div>
        <ul class="list-group list-group-flush" id="sortable-links">
            <?php if (count($links) > 0): ?>
                <?php foreach ($links as $link): ?>
                    <li class="list-group-item d-flex align-items-center link-item-draggable"
                        data-id="<?php echo $link->id; ?>"
                        data-group="<?php echo HTML::chars($link->group_name); ?>">
                        <!-- Иконка для перетаскивания: bi-grip-vertical -->
                        <i class="bi bi-grip-vertical me-2 text-muted handle"></i>
                        <i class="bi <?php echo HTML::chars($link->icon_class); ?> me-2 fs-5" data-ui-color="<?php echo HTML::chars($link->color); ?>"></i>
                        <a href="<?php echo HTML::chars($link->url); ?>" target="_blank" class="flex-grow-1 me-2 text-decoration-none" data-ui-color="<?php echo HTML::chars($link->color); ?>">
                            <span class="link-title"><?php echo HTML::chars($link->title); ?></span>
                            <?php if (!empty($link->description)): ?>
                                <small class="text-muted ms-2 link-description-display">(<?php echo HTML::chars($link->description); ?>)</small>
                            <?php endif; ?>
                            <?php if (!empty($link->group_name)): ?>
                                <small class="text-muted ms-2 link-group-display">(Группа: <?php echo HTML::chars($link->group_name); ?>)</small>
                            <?php endif; ?>
                        </a>
                        <button class="btn btn-sm btn-outline-secondary me-2 edit-link-btn"
                                data-id="<?php echo $link->id; ?>"
                                data-title="<?php echo HTML::chars($link->title); ?>"
                                data-description="<?php echo HTML::chars($link->description); ?>"
                                data-color="<?php echo HTML::chars($link->color); ?>"
                                data-icon-class="<?php echo HTML::chars($link->icon_class); ?>"
                                data-group-name="<?php echo HTML::chars($link->group_name); ?>">
                            <!-- Иконка редактирования: bi-pencil -->
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger delete-link-btn" data-id="<?php echo $link->id; ?>">
                            <!-- Иконка удаления: bi-trash -->
                            <i class="bi bi-trash"></i>
                        </button>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li class="list-group-item text-muted empty-list-message">У вас пока нет сохраненных ссылок.</li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Модальное окно для редактирования ссылки -->
    <div class="modal fade" id="editLinkModal" tabindex="-1" aria-labelledby="editLinkModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editLinkModalLabel">Редактировать ссылку</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editLinkForm">
                        <input type="hidden" id="edit-link-id">
                        <div class="mb-3">
                            <label for="edit-link-title" class="form-label">Заголовок</label>
                            <input type="text" class="form-control" id="edit-link-title" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit-link-description" class="form-label">Описание</label>
                            <textarea class="form-control" id="edit-link-description" rows="3"></textarea>
                            <div class="form-text">Краткое описание ссылки (необязательно).</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit-link-color" class="form-label">Цвет</label>
                            <input type="color" class="form-control form-control-color" id="edit-link-color" value="#007bff" title="Выберите цвет">
                        </div>
                        <div class="mb-3">
                            <label for="edit-link-icon-class" class="form-label">Класс иконки</label>
                            <input type="text" class="form-control" id="edit-link-icon-class" maxlength="50" value="bi-link-45deg">
                            <div class="form-text">Например: bi-link-45deg, bi-star, bi-house. Используются иконки Bootstrap Icons.</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit-link-group" class="form-label">Группа</label>
                            <input type="text" class="form-control" id="edit-link-group" list="group-suggestions" placeholder="Введите или выберите группу">
                            <datalist id="group-suggestions">
                                <?php foreach ($groups as $group_name): ?>
                                    <option value="<?php echo HTML::chars($group_name); ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <div class="form-text">Оставьте пустым для "Без группы".</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" id="save-edited-link-btn">Сохранить изменения</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Функция для отображения уведомлений
        function showAlert(message, type) {
            var alertHtml = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
                                ${message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                             </div>`;
            $('#alert-messages').html(alertHtml);
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000); // Закрыть через 5 секунд
        }

        // Функция для обновления заголовков групп
        function updateGroupHeaders() {
            $('.group-header').remove(); // Удаляем все существующие заголовки групп
            var currentGroup = null;
            var $sortableList = $('#sortable-links');
            var linksExist = false;

            $sortableList.find('li.link-item-draggable').each(function() {
                linksExist = true;
                var linkGroup = $(this).data('group');
                // Преобразуем 'null' или пустую строку в 'Без группы' для отображения
                var displayGroup = (linkGroup === '' || linkGroup === 'null' || linkGroup === undefined) ? 'Без группы' : linkGroup;

                if (displayGroup !== currentGroup) {
                    currentGroup = displayGroup;
                    // Вставляем заголовок группы перед текущей ссылкой
                    $(`<li class="list-group-item list-group-item-secondary group-header" data-group-name="${linkGroup}"><strong>${currentGroup}</strong></li>`).insertBefore($(this));
                }
            });

            // Если список пуст, показываем сообщение
            if (!linksExist) {
                if ($sortableList.find('.empty-list-message').length === 0) {
                    $sortableList.html('<li class="list-group-item text-muted empty-list-message">У вас пока нет сохраненных ссылок.</li>');
                }
            } else {
                // Удаляем сообщение, если ссылки появились
                $sortableList.find('.empty-list-message').remove();
            }
        }

        // Инициализация SortableJS для перетаскивания
        var sortableList = document.getElementById('sortable-links');
        if (sortableList) {
            var sortable = Sortable.create(sortableList, {
                animation: 150,
                handle: '.handle', // Элемент, за который можно перетаскивать
                filter: '.group-header, .empty-list-message', // Игнорировать заголовки групп и сообщение о пустом списке
                onEnd: function (evt) {
                    var order = [];
                    // Проходим по всем элементам, которые можно перетаскивать
                    $('#sortable-links li.link-item-draggable').each(function(index) {
                        order.push({
                            id: $(this).data('id'),
                            order: index
                        });
                    });

                    $.ajax({
                        url: '<?php echo URL::site('erp118_links/reorder'); ?>',
                        method: 'POST',
                        data: { order: order },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                showAlert('Порядок ссылок обновлен.', 'success');
                                // После успешного обновления порядка, перерисовываем заголовки групп
                                updateGroupHeaders();
                            } else {
                                showAlert('Ошибка при обновлении порядка: ' + response.message, 'danger');
                            }
                        },
                        error: function(xhr, status, error) {
                            showAlert('Произошла ошибка при обновлении порядка.', 'danger');
                            console.error('AJAX Error reordering links:', status, error, xhr.responseText);
                        }
                    });
                }
            });
        }

        // Показ модального окна редактирования
        $(document).on('click', '.edit-link-btn', function() {
            var linkId = $(this).data('id');
            var title = $(this).data('title');
            var description = $(this).data('description'); // Получаем описание
            var color = $(this).data('color');
            var iconClass = $(this).data('icon-class');
            var groupName = $(this).data('group-name');

            $('#edit-link-id').val(linkId);
            $('#edit-link-title').val(title);
            $('#edit-link-description').val(description); // Устанавливаем описание
            $('#edit-link-color').val(color);
            $('#edit-link-icon-class').val(iconClass);
            // Если groupName 'null' (из PHP NULL) или undefined, устанавливаем пустую строку
            $('#edit-link-group').val((groupName === 'null' || groupName === undefined) ? '' : groupName);

            var editLinkModal = new bootstrap.Modal(document.getElementById('editLinkModal'));
            editLinkModal.show();
        });

        // Сохранение изменений ссылки
        $('#save-edited-link-btn').on('click', function() {
            var linkId = $('#edit-link-id').val();
            var title = $('#edit-link-title').val();
            var description = $('#edit-link-description').val(); // Получаем описание
            var color = $('#edit-link-color').val();
            var iconClass = $('#edit-link-icon-class').val();
            var groupName = $('#edit-link-group').val();

            $.ajax({
                url: '<?php echo URL::site('erp118_links/update'); ?>',
                method: 'POST',
                data: {
                    id: linkId,
                    title: title,
                    description: description, // Отправляем описание
                    color: color,
                    icon_class: iconClass,
                    group_name: groupName
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        showAlert('Ссылка успешно обновлена!', 'success');
                        // Обновляем отображение ссылки на странице без перезагрузки
                        var listItem = $(`#sortable-links li[data-id="${linkId}"]`);
                        listItem.find('.link-title').text(title);
                        listItem.find('a').css('color', color);
                        listItem.find('.bi').not('.handle').removeClass().addClass(`bi ${iconClass} me-2`).css('color', color);
                        // Обновляем data-атрибуты кнопки для следующего редактирования
                        listItem.find('.edit-link-btn').data({
                            'title': title,
                            'description': description, // Обновляем data-атрибут описания
                            'color': color,
                            'icon-class': iconClass,
                            'group-name': groupName
                        });
                        // Обновляем data-атрибут группы для элемента списка
                        listItem.data('group', groupName);

                        // Обновляем отображение описания
                        var descriptionTextElement = listItem.find('.link-description-display');
                        if (description) {
                            if (descriptionTextElement.length) {
                                descriptionTextElement.text(`(${description})`);
                            } else {
                                // Вставляем описание после заголовка, но перед группой
                                listItem.find('.link-title').after(`<small class="text-muted ms-2 link-description-display">(${description})</small>`);
                            }
                        } else {
                            descriptionTextElement.remove();
                        }

                        // Обновляем отображение группы рядом с заголовком
                        var groupTextElement = listItem.find('.link-group-display');
                        if (groupName) {
                            if (groupTextElement.length) {
                                groupTextElement.text(`(Группа: ${groupName})`);
                            } else {
                                // Если есть описание, вставляем после него, иначе после заголовка
                                if (descriptionTextElement.length) {
                                    descriptionTextElement.after(`<small class="text-muted ms-2 link-group-display">(Группа: ${groupName})</small>`);
                                } else {
                                    listItem.find('.link-title').after(`<small class="text-muted ms-2 link-group-display">(Группа: ${groupName})</small>`);
                                }
                            }
                        } else {
                            groupTextElement.remove();
                        }

                        bootstrap.Modal.getInstance(document.getElementById('editLinkModal')).hide();
                        updateGroupHeaders(); // Обновляем заголовки групп после изменения группы ссылки
                    } else {
                        showAlert('Ошибка при обновлении ссылки: ' + response.message, 'danger');
                        console.error('Error updating link:', response.errors || response.message);
                    }
                },
                error: function(xhr, status, error) {
                    showAlert('Произошла ошибка при обновлении ссылки.', 'danger');
                    console.error('AJAX Error updating link:', status, error, xhr.responseText);
                }
            });
        });

        // Удаление ссылки
        $(document).on('click', '.delete-link-btn', function() {
            if (confirm('Вы уверены, что хотите удалить эту ссылку?')) {
                var linkId = $(this).data('id');
                var $thisButton = $(this);

                $.ajax({
                    url: '<?php echo URL::site('erp118_links/delete'); ?>',
                    method: 'POST',
                    data: { id: linkId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            showAlert('Ссылка успешно удалена!', 'success');
                            $thisButton.closest('li.link-item-draggable').remove(); // Удаляем элемент из списка
                            updateGroupHeaders(); // Обновляем заголовки групп после удаления
                        } else {
                            showAlert('Ошибка при удалении ссылки: ' + response.message, 'danger');
                        }
                    },
                    error: function(xhr, status, error) {
                        showAlert('Произошла ошибка при удалении ссылки.', 'danger');
                        console.error('AJAX Error deleting link:', status, error, xhr.responseText);
                    }
                });
            }
        });

        // Инициализация заголовков групп при загрузке страницы
        updateGroupHeaders();
    });
</script>