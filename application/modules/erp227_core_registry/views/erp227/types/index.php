<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <p class="text-gray-500 dark:text-gray-400 text-sm">
                Настройка метаданных и схем атрибутов для универсального реестра
            </p>
        </div>
        <div class="flex items-center gap-2">
            <button data-call="openTypeModal" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center">
                <i class="fas fa-plus mr-2"></i> Добавить тип
            </button>
        </div>
    </div>

    <!-- Фильтры и поиск -->
    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 mb-6 border border-gray-200 dark:border-gray-700 card-shadow">
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                    <i class="fas fa-tag"></i>
                </span>
                <input type="text" name="name" value="<?php echo HTML::chars($filters['name'] ?? ''); ?>" placeholder="Наименование..." 
                       class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-blue-500 sm:text-sm">
            </div>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                    <i class="fas fa-code"></i>
                </span>
                <input type="text" name="code" value="<?php echo HTML::chars($filters['code'] ?? ''); ?>" placeholder="Код типа..." 
                       class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-blue-500 sm:text-sm">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 px-4 py-2 rounded-md text-sm font-medium transition-colors">
                    Применить
                </button>
                <a href="/erp227_types" class="flex items-center justify-center px-4 py-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Таблица типов -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden card-shadow">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <caption class="sr-only">Таблица данных</caption>
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">ID / Код</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Наименование</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Схема атрибутов</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Объектов</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Действия</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php if (isset($types) && count($types) > 0): ?>
                        <?php foreach ($types as $type): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-mono text-blue-600 dark:text-blue-400"><?php echo HTML::chars($type->code); ?></div>
                                    <div class="text-xs text-gray-400">ID: <?php echo (int)$type->id; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo HTML::chars($type->name); ?></div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($type->description ?? 'Нет описания'); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($type->schema) && $type->schema !== '[]'): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                            <i class="fas fa-code-json mr-1"></i> Схема активна
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400">
                                            Стандартная
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo (int)($type->objects_count ?? 0); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <a href="/erp227_types/edit/<?php echo $type->id; ?>" class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors" title="Редактировать">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="/erp227_types/schema/<?php echo $type->id; ?>" class="p-2 text-purple-600 hover:bg-purple-50 dark:hover:bg-purple-900/20 rounded-lg transition-colors" title="Конструктор атрибутов">
                                            <i class="fas fa-project-diagram"></i>
                                        </a>
                                        <button data-call="confirmDelete" data-call-args="[<?php echo $type->id; ?>]" class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors" title="Удалить">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <i class="fas fa-layer-group text-4xl mb-3 block opacity-20"></i>
                                Типы объектов не найдены
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (isset($pagination)): ?>
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700">
                <?php echo $pagination->render(); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function confirmDelete(id) {
        if (confirm('Вы уверены, что хотите удалить этот тип? Это может повлиять на связанные объекты.')) {
            fetch('/erp227_types/delete/' + id, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(response => response.json())
              .then(data => {
                  if (data.status === 'ok') {
                      window.location.reload();
                  } else {
                      alert(data.message || 'Ошибка при удалении');
                  }
              });
        }
    }

    function openTypeModal() {
        window.location.href = '/erp227_types/create';
    }
</script>