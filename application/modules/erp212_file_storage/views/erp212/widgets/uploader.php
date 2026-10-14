<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="uploader-widget" 
     data-upload-url="<?php echo HTML::chars($upload_url ?? ''); ?>"
     data-delete-url="<?php echo HTML::chars($delete_url ?? ''); ?>"
     data-field-name="<?php echo HTML::chars($field_name ?? 'file'); ?>"
     <?php if (isset($allowed_types) && is_array($allowed_types)): ?>data-allowed-types="<?php echo htmlspecialchars(json_encode($allowed_types)); ?>"<?php endif; ?>
     <?php if (isset($max_size)): ?>data-max-size="<?php echo (int)$max_size; ?>"<?php endif; ?>
     <?php if (isset($multiple) && $multiple): ?>data-multiple="true"<?php endif; ?>>
    
    <div class="drop-zone border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center cursor-pointer hover:border-blue-500 dark:hover:border-blue-400 transition-colors bg-gray-50 dark:bg-gray-800/50">
        <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 dark:text-gray-500 mb-3"></i>
        <p class="text-sm text-gray-600 dark:text-gray-300 mb-1">Перетащите файлы сюда</p>
        <p class="text-xs text-gray-500 dark:text-gray-400">или</p>
        <button type="button" class="mt-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors">
            Выбрать файлы
        </button>
        <input type="file" <?php echo (isset($multiple) && $multiple) ? 'multiple' : ''; ?> class="hidden">
        <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">
            <?php if (isset($allowed_types) && is_array($allowed_types)): ?>
                Поддерживаемые форматы: <?php echo implode(', ', $allowed_types); ?>
            <?php endif; ?>
            <?php if (isset($max_size)): ?>
                (макс. <?php echo round($max_size / 1024 / 1024, 2); ?> МБ)
            <?php endif; ?>
        </p>
    </div>
    
    <div class="file-list mt-4 space-y-2">
        <?php if (isset($existing_files) && is_array($existing_files)): ?>
            <?php foreach ($existing_files as $file): ?>
                <div class="file-item flex items-center justify-between p-2 bg-gray-100 dark:bg-gray-700 rounded" data-file-id="<?php echo HTML::chars($file['id'] ?? ''); ?>">
                    <div class="file-info flex items-center flex-1 min-w-0">
                        <?php
                        $icon_class = 'fa-file';
                        $type = $file['type'] ?? '';
                        if (strpos($type, 'image/') === 0) $icon_class = 'fa-file-image';
                        elseif (strpos($type, 'video/') === 0) $icon_class = 'fa-file-video';
                        elseif (strpos($type, 'audio/') === 0) $icon_class = 'fa-file-audio';
                        elseif (strpos($type, 'pdf') !== false) $icon_class = 'fa-file-pdf';
                        ?>
                        <i class="fas <?php echo $icon_class; ?> mr-2 text-gray-500 dark:text-gray-400"></i>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate"><?php echo HTML::chars($file['name']); ?></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                <?php 
                                $size = $file['size'] ?? 0;
                                $units = ['B', 'KB', 'MB', 'GB'];
                                $factor = floor((strlen($size) - 1) / 3);
                                echo sprintf("%.2f", $size / pow(1024, $factor)) . ' ' . $units[$factor] ?? 'B';
                                ?>
                            </p>
                        </div>
                    </div>
                    <?php if (isset($delete_url)): ?>
                        <button class="delete-btn ml-2 p-1 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition-colors" title="Удалить">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    <?php endif; ?>
                </div>
                <input type="hidden" 
                       name="<?php echo HTML::chars($field_name); ?><?php echo (isset($multiple) && $multiple) ? '[]' : ''; ?>" 
                       value="<?php echo HTML::chars($file['id']); ?>">
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div class="hidden-fields"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    $('.uploader-widget').each(function() {
        const $widget = $(this);
        const uploadUrl = $widget.data('upload-url');
        const deleteUrl = $widget.data('delete-url');
        const fieldName = $widget.data('field-name');
        const allowedTypes = $widget.data('allowed-types') || [];
        const maxSize = $widget.data('max-size') || 0;
        const isMultiple = $widget.data('multiple') || false;
        
        const $dropZone = $widget.find('.drop-zone');
        const $fileInput = $dropZone.find('input[type="file"]');
        const $fileList = $widget.find('.file-list');
        const $hiddenFields = $widget.find('.hidden-fields');
        
        if (!uploadUrl) {
            console.warn('Upload URL не указан для виджета загрузки');
            return;
        }
        
        $dropZone.on('click', function(e) {
            if (e.target === $dropZone[0] || $(e.target).hasClass('fa-cloud-upload-alt') || $(e.target).hasClass('text-gray-400')) {
                $fileInput.click();
            }
        });
        
        $dropZone.on('dragover', function(e) {
            e.preventDefault();
            $dropZone.addClass('border-blue-500', 'bg-blue-50', 'dark:bg-blue-900/10');
        });
        
        $dropZone.on('dragleave', function(e) {
            e.preventDefault();
            $dropZone.removeClass('border-blue-500', 'bg-blue-50', 'dark:bg-blue-900/10');
        });
        
        $dropZone.on('drop', function(e) {
            e.preventDefault();
            $dropZone.removeClass('border-blue-500', 'bg-blue-50', 'dark:bg-blue-900/10');
            const files = e.originalEvent.dataTransfer.files;
            handleFiles(files);
        });
        
        $fileInput.on('change', function() {
            handleFiles(this.files);
        });
        
        function handleFiles(files) {
            const fileArray = Array.from(files);
            fileArray.forEach(file => {
                if (allowedTypes.length && file.type && allowedTypes.indexOf(file.type) === -1) {
                    if (window.showNotification) {
                        window.showNotification(`Тип файла ${file.name} не разрешен`, 'error');
                    } else {
                        alert(`Тип файла ${file.name} не разрешен`);
                    }
                    return;
                }
                
                if (maxSize && file.size > maxSize) {
                    if (window.showNotification) {
                        window.showNotification(`Файл ${file.name} превышает максимальный размер`, 'error');
                    } else {
                        alert(`Файл ${file.name} превышает максимальный размер`);
                    }
                    return;
                }
                
                uploadFile(file);
            });
        }
        
        function uploadFile(file) {
            const formData = new FormData();
            formData.append('file', file);
            
            $.ajax({
                url: uploadUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success && response.file) {
                        addFileToWidget(response.file);
                        if (window.showNotification) {
                            window.showNotification('Файл успешно загружен', 'success');
                        }
                    } else {
                        const message = response.message || 'Ошибка загрузки файла';
                        if (window.showNotification) {
                            window.showNotification(message, 'error');
                        } else {
                            alert(message);
                        }
                    }
                },
                error: function(xhr) {
                    let message = 'Ошибка загрузки файла';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        message = response.message || message;
                    } catch (e) {}
                    
                    if (window.showNotification) {
                        window.showNotification(message, 'error');
                    } else {
                        alert(message);
                    }
                }
            });
        }
        
        function addFileToWidget(file) {
            const fileId = file.id || Date.now() + Math.random();
            const iconClass = getFileIcon(file.type);
            const sizeFormatted = formatBytes(file.size);
            
            const $fileItem = $(`
                <div class="file-item flex items-center justify-between p-2 bg-gray-100 dark:bg-gray-700 rounded" data-file-id="${fileId}">
                    <div class="file-info flex items-center flex-1 min-w-0">
                        <i class="fas ${iconClass} mr-2 text-gray-500 dark:text-gray-400"></i>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">${escapeHtml(file.name)}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">${sizeFormatted}</p>
                        </div>
                    </div>
                    ${deleteUrl ? `
                        <button class="delete-btn ml-2 p-1 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition-colors" title="Удалить">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    ` : ''}
                </div>
            `);
            
            $fileList.append($fileItem);
            
            const $hiddenField = $(`
                <input type="hidden" 
                      name="${escapeHtml(fieldName)}${isMultiple ? '[]' : ''}" 
                      value="${escapeHtml(fileId)}">
            `);
            $hiddenFields.append($hiddenField);
        }
        
        function getFileIcon(type) {
            if (!type) return 'fa-file';
            if (type.startsWith('image/')) return 'fa-file-image';
            if (type.startsWith('video/')) return 'fa-file-video';
            if (type.startsWith('audio/')) return 'fa-file-audio';
            if (type.includes('pdf')) return 'fa-file-pdf';
            if (type.includes('word') || type.includes('document')) return 'fa-file-word';
            if (type.includes('excel') || type.includes('spreadsheet')) return 'fa-file-excel';
            if (type.includes('zip') || type.includes('compressed')) return 'fa-file-zipper';
            return 'fa-file';
        }
        
        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        if (deleteUrl) {
            $widget.on('click', '.delete-btn', function() {
                const $fileItem = $(this).closest('.file-item');
                const fileId = $fileItem.data('file-id');
                const $hiddenField = $hiddenFields.find(`input[value="${fileId}"]`);
                
                if (confirm('Вы уверены, что хотите удалить этот файл?')) {
                    $.ajax({
                        url: deleteUrl,
                        type: 'DELETE',
                        data: {file_id: fileId},
                        success: function() {
                            $fileItem.remove();
                            $hiddenField.remove();
                            if (window.showNotification) {
                                window.showNotification('Файл удален', 'success');
                            }
                        },
                        error: function(xhr) {
                            let message = 'Ошибка удаления файла';
                            try {
                                const response = JSON.parse(xhr.responseText);
                                message = response.message || message;
                            } catch (e) {}
                            
                            if (window.showNotification) {
                                window.showNotification(message, 'error');
                            } else {
                                alert(message);
                            }
                        }
                    });
                }
            });
        }
    });
});
</script>