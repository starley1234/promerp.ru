<div class="p-4 sm:p-6 lg:p-8 bg-white dark:bg-gray-800 rounded-lg shadow-md">
    <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100 mb-6">Управление ролями для <?php echo htmlspecialchars($user->username); ?></h2>

    <?php if (isset($errors) && !empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 dark:bg-red-900 dark:border-red-700 dark:text-red-200" role="alert">
            <strong class="font-bold">Ошибка!</strong>
            <ul class="mt-2 list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="" method="post" class="mt-4 space-y-6">
        <div>
            <label class="block text-lg font-medium text-gray-700 dark:text-gray-300 mb-3">Выберите роли:</label>
            <div class="space-y-2">
                <?php foreach ($all_roles as $role): ?>
                    <div class="flex items-center">
                        <input type="checkbox" name="roles[]" value="<?php echo htmlspecialchars($role->name); ?>" id="role_<?php echo htmlspecialchars($role->id); ?>"
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded dark:bg-gray-700 dark:border-gray-600 dark:focus:ring-blue-600 dark:ring-offset-gray-800"
                            <?php if (in_array($role->name, $user_roles)): ?>checked<?php endif; ?>>
                        <label for="role_<?php echo htmlspecialchars($role->id); ?>" class="ml-2 text-gray-700 dark:text-gray-300 cursor-pointer">
                            <?php echo htmlspecialchars($role->name); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flex items-center space-x-4">
            <button type="submit" name="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-blue-700 dark:hover:bg-blue-800 dark:focus:ring-offset-gray-800">
                Обновить роли
            </button>
            <a href="<?php echo htmlspecialchars(URL::site('auth/view/' . $user->id)); ?>" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600 dark:focus:ring-offset-gray-800">
                Отмена
            </a>
        </div>
    </form>
</div>