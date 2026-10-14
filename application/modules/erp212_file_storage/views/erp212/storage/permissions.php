<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="mb-6">
        <nav class="flex mb-2" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="<?php echo Route::url('default', ['controller' => 'erp212_storage', 'action' => 'index']); ?>" class="text-sm text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-white">
                        <i class="fas fa-folder-open mr-2"></i>Хранилище
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 text-xs mx-2"></i>
                        <a href="<?php echo Route::url('default', ['controller' => 'erp212_storage', 'action' => 'file', 'id' => $file->id]); ?>" class="text-sm text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-white">
                            <?php echo HTML::chars($file->filename); ?>
                        </a>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 text-xs mx-2"></i>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Права доступа</span>
                    </div>
                </li>
            </ol>
        </nav>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            Управление доступом: <?php echo HTML::chars($file->filename); ?>
        </h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Форма добавления прав -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/20">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Добавить пользователя</h2>
                </div>
                <form method="post" class="p-6 space-y-4">
                    <div>
                        <label for="user_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Выберите пользователя</label>
                        <select name="user_id" id="user_id" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user->id; ?>"><?php echo HTML::chars($user->username); ?> (<?php echo HTML::chars($user->email); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="space-y-3 pt-2">
                        <label class="flex items-center">
                            <input type="checkbox" name="can_read" value="1" checked class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Разрешить чтение</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="can_write" value="1" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Разрешить запись</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="can_delete" value="1" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Разрешить удаление</span>
                        </label>
                    </div>

                    <button type="submit" class="w-full mt-4 inline-flex justify-center items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
                        <i class="fas fa-user-plus mr-2"></i> Сохранить права
                    </button>
                </form>
            </div>
        </div>

        <!-- Список текущих прав -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/20">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Действующие разрешения</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                    <caption class="sr-only">Таблица данных</caption>
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-900/40 text-xs uppercase text-gray-500 dark:text-gray-400">
                                <th scope="col" class="px-6 py-3 font-medium">Пользователь</th>
                                <th scope="col" class="px-6 py-3 font-medium text-center">Чтение</th>
                                <th scope="col" class="px-6 py-3 font-medium text-center">Запись</th>
                                <th scope="col" class="px-6 py-3 font-medium text-center">Удаление</th>
                                <th scope="col" class="px-6 py-3 font-medium text-right">Действия</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php if (count($permissions) > 0): ?>
                                <?php foreach ($permissions as $perm): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xs font-bold text-gray-600 dark:text-gray-300 mr-3">
                                                    <?php echo strtoupper(substr($perm->user->username, 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo HTML::chars($perm->user->username); ?></div>
                                                    <div class="text-xs text-gray-500"><?php echo HTML::chars($perm->user->email); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <i class="fas <?php echo $perm->can_read ? 'fa-check-circle text-green-500' : 'fa-times-circle text-gray-300'; ?>"></i>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <i class="fas <?php echo $perm->can_write ? 'fa-check-circle text-green-500' : 'fa-times-circle text-gray-300'; ?>"></i>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <i class="fas <?php echo $perm->can_delete ? 'fa-check-circle text-green-500' : 'fa-times-circle text-gray-300'; ?>"></i>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <button data-call="deletePermission" data-call-args="[<?php echo $perm->id; ?>]" class="text-red-500 hover:text-red-700 transition-colors">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400 italic">
                                        Индивидуальные права доступа не настроены. Доступ определяется владельцем и системными ролями.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function deletePermission(id) {
    if (confirm('Вы уверены, что хотите отозвать права у этого пользователя?')) {
        // В реальном приложении здесь должен быть вызов API или отправка формы
        console.log('Удаление разрешения:', id);
        // window.location.href = '/erp212_storage/revoke_permission/' + id;
    }
}
</script>